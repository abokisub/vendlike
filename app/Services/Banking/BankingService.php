<?php

namespace App\Services\Banking;

use App\Services\Banking\Providers\PaystackProvider;
use App\Services\Banking\Providers\XixapayProvider;
use App\Services\Banking\Providers\MonnifyProvider;
use App\Services\Banking\Providers\PointWaveProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BankingService
{
    /**
     * Resolve a provider instance by slug.
     */
    public function resolveProvider(string $slug): BankingProviderInterface
    {
        switch (strtolower($slug)) {
            case 'pointwave':
                return new PointWaveProvider();
            case 'xixapay':
                return new XixapayProvider();
            case 'monnify':
                return new MonnifyProvider();
            case 'paystack':
                return new PaystackProvider();
            default:
                return new PointWaveProvider(); // Default to PointWave
        }
    }

    /**
     * Get the currently active primary transfer provider.
     * Uses PointWave as the primary provider for transfers.
     * Falls back to Xixapay if PointWave is unavailable.
     */
    public function getActiveProvider(): BankingProviderInterface
    {
        // Check settings for preferred provider (check both column names for compatibility)
        $settings = DB::table('settings')->first();
        $preferredProvider = $settings->primary_transfer_provider ?? $settings->transfer_provider ?? 'pointwave';
        
        // Allow Xixapay as alternative provider
        if ($preferredProvider === 'xixapay') {
            return new XixapayProvider();
        }
        
        // Enforce PointWave as default
        if ($preferredProvider === 'pointwave' || empty($preferredProvider)) {
            return new PointWaveProvider();
        }
        
        // Allow fallback to other providers if explicitly set
        return $this->resolveProvider($preferredProvider);
    }

    /**
     * Verify an account number.
     * Uses the active provider.
     * For PointWave: converts old bank codes to PointWave codes.
     * For Xixapay: uses xixapay bank codes directly.
     */
    public function verifyAccount(string $accountNumber, string $bankCode): array
    {
        $provider = $this->getActiveProvider();
        $providerSlug = $provider->getProviderSlug();

        try {
            $resolvedCode = $bankCode;
            
            // Only convert codes for PointWave (legacy Paystack codes -> PointWave codes)
            if ($providerSlug === 'pointwave') {
                $resolvedCode = $this->convertToPointWaveCode($bankCode);
                if ($resolvedCode !== $bankCode) {
                    Log::info("BankingService: Converted old bank code", [
                        'old_code' => $bankCode,
                        'new_code' => $resolvedCode
                    ]);
                }
            }
            
            return $provider->verifyAccount($accountNumber, $resolvedCode);

        }
        catch (\Exception $e) {
            Log::error("BankingService: Verification failed ({$providerSlug}): " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Convert old bank codes (Paystack/legacy) to PointWave codes
     */
    private function convertToPointWaveCode(string $oldCode): string
    {
        // Try to find the bank by old code and get its PointWave code
        // Only check paystack_code since xixapay_code and monnify_code don't exist in the table
        $bank = DB::table('unified_banks')
            ->where('paystack_code', $oldCode)
            ->whereNotNull('pointwave_code')
            ->where('pointwave_code', '!=', '')
            ->first();
        
        if ($bank && !empty($bank->pointwave_code)) {
            return $bank->pointwave_code;
        }
        
        // If no conversion found, return original code
        return $oldCode;
    }

    /**
     * Initiate a transfer.
     * Uses the active provider.
     * For PointWave: converts old bank codes. Looks up bank name from unified_banks.
     * For Xixapay: uses codes directly. Looks up bank name from xixapay_banks.
     */
    public function transfer(array $details): array
    {
        $provider = $this->getActiveProvider();
        $providerSlug = $provider->getProviderSlug();

        // Only convert codes for PointWave
        if ($providerSlug === 'pointwave') {
            $pointwaveCode = $this->convertToPointWaveCode($details['bank_code']);
            if ($pointwaveCode !== $details['bank_code']) {
                Log::info("BankingService: Converted old bank code for transfer", [
                    'old_code' => $details['bank_code'],
                    'new_code' => $pointwaveCode
                ]);
            }
            $details['bank_code'] = $pointwaveCode;
        }

        try {
            $result = $provider->transfer($details);
            
            // Add bank name to result if not present
            if (!isset($result['bank_name'])) {
                $bankTable = ($providerSlug === 'xixapay') ? 'xixapay_banks' : 'unified_banks';
                $bank = DB::table($bankTable)->where('code', $details['bank_code'])->first();
                $result['bank_name'] = $bank ? $bank->name : null;
            }
            
            return $result;
        }
        catch (\Exception $e) {
            Log::error("BankingService: Transfer Error ({$providerSlug}): " . $e->getMessage());
            return [
                'status' => 'fail',
                'message' => 'Transfer failed. ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get list of supported banks based on active provider.
     * PointWave -> unified_banks table
     * Xixapay -> xixapay_banks + fallback from unified_banks for banks Xixapay doesn't have
     */
    public function getSupportedBanks()
    {
        $provider = $this->getActiveProvider();
        $providerSlug = $provider->getProviderSlug();
        
        if ($providerSlug === 'xixapay') {
            // Primary: Xixapay banks
            $xixapayBanks = DB::table('xixapay_banks')
                ->where('active', true)
                ->whereNotNull('code')
                ->where('code', '!=', '')
                ->orderBy('name')
                ->get();

            // Fallback: PointWave banks not already in Xixapay (by name match)
            $xixapayNames = $xixapayBanks->pluck('name')->map(fn($n) => strtolower(trim($n)))->toArray();

            $fallbackBanks = DB::table('unified_banks')
                ->where('active', true)
                ->whereNotNull('code')
                ->where('code', '!=', '')
                ->orderBy('name')
                ->get()
                ->filter(function ($bank) use ($xixapayNames) {
                    return !in_array(strtolower(trim($bank->name)), $xixapayNames);
                })
                ->map(function ($bank) {
                    // Mark as fallback so the system knows these use PointWave codes
                    $bank->source = 'pointwave_fallback';
                    return $bank;
                });

            // Merge: Xixapay first, then PointWave extras
            return $xixapayBanks->merge($fallbackBanks)->sortBy('name')->values();
        }
        
        // PointWave / default — use unified_banks
        return DB::table('unified_banks')
            ->where('active', true)
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->orderBy('name')
            ->get();
    }

    /**
     * Sync banks from a specific provider to its database table.
     * Xixapay -> xixapay_banks table
     * Others -> unified_banks table
     */
    public function syncBanksFromProvider(string $providerSlug)
    {
        $provider = $this->resolveProvider($providerSlug);
        $banks = $provider->getBanks();

        // Xixapay has its own dedicated table
        if ($providerSlug === 'xixapay') {
            return $this->syncXixapayBanks($banks);
        }

        $count = 0;
        foreach ($banks as $bank) {
            $existing = DB::table('unified_banks')->where('code', $bank['code'])->first();

            if (!$existing) {
                DB::table('unified_banks')->insert([
                    'name' => $bank['name'],
                    'code' => $bank['code'],
                    "{$providerSlug}_code" => $bank['code'],
                    'active' => $bank['active'] ?? true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $count++;
            }
            else {
                DB::table('unified_banks')->where('id', $existing->id)->update([
                    "{$providerSlug}_code" => $bank['code'],
                    'active' => $bank['active'] ?? true,
                    'updated_at' => now()
                ]);
            }
        }
        return $count;
    }

    /**
     * Sync Xixapay banks into the dedicated xixapay_banks table.
     */
    private function syncXixapayBanks(array $banks): int
    {
        $count = 0;
        foreach ($banks as $bank) {
            $code = $bank['code'] ?? '';
            if (empty($code)) continue;
            
            $name = $bank['name'] ?? 'Unknown';
            $slug = $bank['slug'] ?? null;
            if (empty($slug)) {
                // Generate unique slug from name + code suffix
                $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($name))) . '-' . $code;
            }
            
            $existing = DB::table('xixapay_banks')->where('code', $code)->first();

            if (!$existing) {
                DB::table('xixapay_banks')->insert([
                    'name' => $name,
                    'code' => $code,
                    'slug' => $slug,
                    'active' => $bank['active'] ?? true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $count++;
            } else {
                DB::table('xixapay_banks')->where('id', $existing->id)->update([
                    'name' => $name,
                    'active' => $bank['active'] ?? true,
                    'updated_at' => now()
                ]);
            }
        }
        return $count;
    }

    /**
     * Helper to resolve generic bank code to provider specific code.
     */
    private function resolveBankCode(string $genericCode, string $providerSlug): string
    {
        $bank = DB::table('unified_banks')->where('code', $genericCode)->first();
        if ($bank && !empty($bank->{ "{$providerSlug}_code"})) {
            return $bank->{ "{$providerSlug}_code"};
        }
        return $genericCode;
    }
}