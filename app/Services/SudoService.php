<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SudoService
{
    protected $apiKey;
    protected $apiUrl;
    protected $vaultUrl;
    protected $vaultId;
    protected $environment;
    protected $fundingSourceId;
    protected $debitAccountId;
    protected $cardProgramId;
    protected $accountReference;

    public function __construct()
    {
        $this->environment = config('services.sudo.environment', 'sandbox');
        $this->apiKey = config('services.sudo.api_key');
        $this->vaultId = config('services.sudo.vault_id', 'we0dsa28svdl2xefo5');
        $this->fundingSourceId = config('services.sudo.funding_source_id');
        $this->debitAccountId = config('services.sudo.debit_account_id');
        $this->cardProgramId = config('services.sudo.card_program_id');
        $this->accountReference = config('services.sudo.account_reference', 'acc_1773999071064');

        if ($this->environment === 'production') {
            $this->apiUrl = 'https://api.sudo.africa';
            $this->vaultUrl = 'https://vault.sudo.africa/v1';
        } else {
            $this->apiUrl = 'https://api.sandbox.sudo.cards/v1';
            $this->vaultUrl = 'https://vault.sandbox.sudo.cards/v1';
        }
    }

    // ─── CUSTOMERS ───────────────────────────────────────────────

    public function createCustomer(array $data): array
    {
        try {
            // Format DOB: Sudo requires YYYY/MM/DD
            $dob = '1990/01/01';
            if (!empty($data['dob'])) {
                try {
                    $dob = \Carbon\Carbon::parse($data['dob'])->format('Y/m/d');
                } catch (\Exception $e) {
                }
            }

            $individual = [
                'firstName' => $data['first_name'],
                'lastName' => $data['last_name'],
                'dob' => $dob,
            ];
            if (!empty($data['bvn'])) {
                $individual['identity'] = ['type' => 'BVN', 'number' => $data['bvn']];
            } elseif (!empty($data['nin'])) {
                $individual['identity'] = ['type' => 'NIN', 'number' => $data['nin']];
            }

            $response = Http::timeout(30)->withToken($this->apiKey)
                ->post("{$this->apiUrl}/customers", [
                    'type' => 'individual',
                    'name' => $data['name'],
                    'phoneNumber' => $data['phone'] ?? null,
                    'emailAddress' => $data['email'] ?? null,
                    'status' => 'active',
                    'billingAddress' => [
                        'line1' => $data['address'] ?? '1 Lagos Street',
                        'city' => $data['city'] ?? 'Lagos',
                        'state' => $data['state'] ?? 'Lagos',
                        'postalCode' => $data['postal_code'] ?? '100001',
                        'country' => 'NG',
                    ],
                    'individual' => $individual,
                ]);

            if ($response->successful()) {
                $body = $response->json();
                return [
                    'status' => 'success',
                    'data' => $body['data'] ?? $body,
                    'customer_id' => $body['data']['_id'] ?? null,
                ];
            }

            Log::error('Sudo createCustomer failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['status' => 'error', 'message' => $response->json()['message'] ?? 'Failed to create customer'];
        } catch (\Exception $e) {
            Log::error('Sudo createCustomer exception: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function updateCustomer(string $customerId, array $data): array
    {
        try {
            // Format DOB
            $dob = '1990/01/01';
            if (!empty($data['dob'])) {
                try {
                    $dob = \Carbon\Carbon::parse($data['dob'])->format('Y/m/d');
                } catch (\Exception $e) {
                }
            }

            $nameParts = explode(' ', $data['name'] ?? 'User', 2);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? $nameParts[0];

            $individual = [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'dob' => $dob,
            ];
            if (!empty($data['bvn'])) {
                $individual['identity'] = ['type' => 'BVN', 'number' => $data['bvn']];
            } elseif (!empty($data['nin'])) {
                $individual['identity'] = ['type' => 'NIN', 'number' => $data['nin']];
            }

            $payload = [
                'type' => 'individual',
                'name' => $data['name'] ?? 'User',
                'status' => 'active',
                'phoneNumber' => $data['phone'] ?? null,
                'emailAddress' => $data['email'] ?? null,
                'individual' => $individual,
                'billingAddress' => [
                    'line1' => $data['address'] ?? '1 Lagos Street',
                    'city' => $data['city'] ?? 'Lagos',
                    'state' => $data['state'] ?? 'Lagos',
                    'postalCode' => $data['postal_code'] ?? '100001',
                    'country' => 'NG',
                ],
            ];

            $response = Http::timeout(30)->withToken($this->apiKey)
                ->put("{$this->apiUrl}/customers/{$customerId}", $payload);

            if ($response->successful()) {
                $body = $response->json();
                Log::info('Sudo updateCustomer success', ['customerId' => $customerId]);
                return ['status' => 'success', 'data' => $body['data'] ?? $body];
            }

            $errMsg = $response->json()['message'] ?? 'Failed to update customer';
            if (is_array($errMsg))
                $errMsg = collect($errMsg)->pluck('constraints')->flatten()->first() ?? 'Failed to update customer';
            Log::error('Sudo updateCustomer failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['status' => 'error', 'message' => $errMsg];
        } catch (\Exception $e) {
            Log::error('Sudo updateCustomer exception: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function generateCustomerDocumentUrl(string $customerId, string $fileName, string $fileType): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->put("{$this->apiUrl}/customers/{$customerId}/documents/url", [
                    'fileName' => $fileName,
                    'fileType' => $fileType,
                ]);

            if ($response->successful()) {
                $body = $response->json();
                return ['status' => 'success', 'data' => $body['data'] ?? $body];
            }

            Log::error('Sudo generateCustomerDocumentUrl failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['status' => 'error', 'message' => $response->json()['message'] ?? 'Failed to generate document URL'];
        } catch (\Exception $e) {
            Log::error('Sudo generateCustomerDocumentUrl exception: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    // ─── CARDS ────────────────────────────────────────────────────

    public function createVirtualCard(string $customerId, float $initialAmount = 5.0): array
    {
        try {
            // programId approach returns 500 on Sudo sandbox/production.
            // Use explicit fields — brand, amount (min $3) are required without programId.
            $payload = [
                'customerId' => $customerId,
                'type' => 'virtual',
                'currency' => 'USD',
                'issuerCountry' => 'USA',
                'brand' => 'Visa',
                'status' => 'active',
                'amount' => $initialAmount,
                'enable2FA' => true,
                'metadata' => (object) [],
                'debitAccountId' => $this->debitAccountId,
                'fundingSourceId' => $this->fundingSourceId,
            ];

            $response = Http::timeout(30)->withToken($this->apiKey)
                ->post("{$this->apiUrl}/cards", $payload);

            if ($response->successful()) {
                $body = $response->json();
                $card = $body['data'] ?? $body;
                return [
                    'status' => 'success',
                    'data' => $card,
                    'card_id' => $card['_id'] ?? null,
                    'masked_pan' => $card['maskedPan'] ?? null,
                    'brand' => $card['brand'] ?? null,
                    'expiry_month' => $card['expiryMonth'] ?? null,
                    'expiry_year' => $card['expiryYear'] ?? null,
                    'last4' => $card['last4'] ?? substr($card['maskedPan'] ?? '', -4),
                    'balance' => (float) ($card['balance'] ?? 0),
                ];
            }

            Log::error('Sudo createVirtualCard failed', ['status' => $response->status(), 'body' => $response->body()]);
            $errMsg = $response->json()['message'] ?? 'Failed to create card';
            if (is_array($errMsg))
                $errMsg = collect($errMsg)->pluck('constraints')->flatten()->first() ?? 'Failed to create card';
            return ['status' => 'error', 'message' => $errMsg];
        } catch (\Exception $e) {
            Log::error('Sudo createVirtualCard exception: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function getCard(string $cardId): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->get("{$this->apiUrl}/cards/{$cardId}");

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? $response->json()];
            }

            return ['success' => false, 'message' => $response->json()['message'] ?? 'Failed to get card'];
        } catch (\Exception $e) {
            Log::error('Sudo getCard exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateCardStatus(string $cardId, string $status, array $extra = []): array
    {
        try {
            $payload = ['status' => $status];

            // canceled requires cancellationReason
            if ($status === 'canceled') {
                $payload['cancellationReason'] = $extra['cancellation_reason'] ?? 'lost';
                if (!empty($extra['credit_account_id'])) {
                    $payload['creditAccountId'] = $extra['credit_account_id'];
                }
            }

            $response = Http::timeout(30)->withToken($this->apiKey)
                ->put("{$this->apiUrl}/cards/{$cardId}", $payload);

            if ($response->successful()) {
                $body = $response->json();
                return ['status' => 'success', 'data' => $body['data'] ?? $body];
            }

            return ['status' => 'error', 'message' => $response->json()['message'] ?? 'Failed to update card status'];
        } catch (\Exception $e) {
            Log::error('Sudo updateCardStatus exception: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function terminateCard(string $cardId, string $reason = 'lost'): array
    {
        return $this->updateCardStatus($cardId, 'canceled', [
            'cancellation_reason' => $reason,
            'credit_account_id' => $this->debitAccountId, // required by Sudo to refund remaining balance
        ]);
    }


    public function freezeCard(string $cardId): array
    {
        return $this->updateCardStatus($cardId, 'inactive');
    }

    public function unfreezeCard(string $cardId): array
    {
        return $this->updateCardStatus($cardId, 'active');
    }

    public function changeCardPin(string $cardId, string $oldPin, string $newPin): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->put("{$this->apiUrl}/cards/{$cardId}/pin", [
                    'oldPin' => $oldPin,
                    'newPin' => $newPin,
                ]);

            if ($response->successful()) {
                return ['status' => 'success', 'data' => $response->json()['data'] ?? $response->json()];
            }

            return ['status' => 'error', 'message' => $response->json()['message'] ?? 'Failed to change PIN'];
        } catch (\Exception $e) {
            Log::error('Sudo changeCardPin exception: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function getCardDetails(string $cardId, bool $reveal = false): array
    {
        try {
            $url = "{$this->vaultUrl}/cards/{$cardId}";
            if ($reveal) {
                $url .= '?reveal=true';
            }

            $response = Http::timeout(30)
                ->withToken($this->apiKey)
                ->withHeaders(['x-sudo-vault-id' => $this->vaultId])
                ->get($url);
            $body = $response->json();

            if ($reveal) {
                Log::info('Sudo Card Reveal Response', ['cardID' => $cardId, 'body' => $body]);
            }

            if ($response->successful()) {
                return ['status' => 'success', 'data' => $body['data'] ?? $body];
            }

            return ['status' => 'error', 'message' => $response->json()['message'] ?? 'Failed to get card details'];
        } catch (\Exception $e) {
            Log::error('Sudo getCardDetails exception: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function getCards(string $customerId, int $page = 0, int $limit = 25): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->get("{$this->apiUrl}/cards/customer/{$customerId}", [
                    'page' => $page,
                    'limit' => $limit,
                ]);

            if ($response->successful()) {
                $body = $response->json();
                return ['status' => 'success', 'data' => $body['data'] ?? []];
            }

            return ['status' => 'error', 'message' => 'Failed to fetch cards'];
        } catch (\Exception $e) {
            Log::error('Sudo getCards exception: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function getAllCards(int $page = 0, int $limit = 25): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->get("{$this->apiUrl}/cards", ['page' => $page, 'limit' => $limit]);

            if ($response->successful()) {
                return ['status' => 'success', 'data' => $response->json()['data'] ?? []];
            }

            return ['status' => 'error', 'message' => 'Failed to fetch cards'];
        } catch (\Exception $e) {
            Log::error('Sudo getAllCards exception: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    // ─── FUNDING ─────────────────────────────────────────────────

    public function fundCard(string $cardId, float $amount): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->post("{$this->apiUrl}/cards/{$cardId}/fund", [
                    'amount' => $amount,
                    'debitAccountId' => $this->debitAccountId,
                    'fundingSourceId' => $this->fundingSourceId,
                    'paymentReference' => 'DCFD_' . strtoupper(substr(md5(uniqid()), 0, 12)),
                ]);

            if ($response->successful()) {
                $body = $response->json();
                return ['status' => 'success', 'data' => $body['data'] ?? $body];
            }

            Log::error('Sudo fundCard failed', ['status' => $response->status(), 'body' => $response->body()]);
            $errMsg = $response->json()['message'] ?? 'Failed to fund card';
            if (is_array($errMsg)) {
                $errMsg = collect($errMsg)->map(fn($e) => collect($e['constraints'] ?? [])->values()->first() ?? '')->filter()->first() ?? 'Failed to fund card';
            }
            return ['status' => 'error', 'message' => $errMsg];
        } catch (\Exception $e) {
            Log::error('Sudo fundCard exception: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function withdrawFromCard(string $cardId, float $amount): array
    {
        try {
            // Sudo has no /cards/{id}/withdraw endpoint.
            // Withdrawal = internal transfer via accounts/transfer endpoint.
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->post("{$this->apiUrl}/accounts/transfer", [
                    'amount' => $amount,
                    'currency' => 'USD',
                    'debitAccountId' => $this->debitAccountId,
                    'creditAccountId' => $this->debitAccountId,
                    'beneficiaryBankCode' => 'SudoHUSVC',
                    'beneficiaryAccountNumber' => $this->accountReference,
                    'narration' => 'Card withdrawal',
                    'paymentReference' => 'DCWD_' . strtoupper(substr(md5(uniqid()), 0, 12)),
                ]);

            if ($response->successful()) {
                $body = $response->json();
                return ['status' => 'success', 'data' => $body['data'] ?? $body];
            }

            Log::error('Sudo withdrawFromCard failed', ['status' => $response->status(), 'body' => $response->body()]);
            $errMsg = $response->json()['message'] ?? 'Failed to withdraw from card';
            if (is_array($errMsg)) {
                $errMsg = collect($errMsg)->map(fn($e) => collect($e['constraints'] ?? [])->values()->first() ?? '')->filter()->first() ?? 'Failed to withdraw from card';
            }
            return ['status' => 'error', 'message' => $errMsg];
        } catch (\Exception $e) {
            Log::error('Sudo withdrawFromCard exception: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    // ─── TRANSACTIONS ────────────────────────────────────────────

    public function getAllTransactions(int $page = 0, int $limit = 25, ?string $fromDate = null, ?string $toDate = null): array
    {
        try {
            $query = array_filter(['page' => $page, 'limit' => $limit, 'fromDate' => $fromDate, 'toDate' => $toDate]);
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->get("{$this->apiUrl}/cards/transactions", $query);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? []];
            }

            return ['success' => false, 'message' => 'Failed to fetch transactions'];
        } catch (\Exception $e) {
            Log::error('Sudo getAllTransactions exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getCardTransactions(string $cardId, int $page = 0, int $limit = 25, ?string $fromDate = null, ?string $toDate = null): array
    {
        try {
            $query = array_filter(['page' => $page, 'limit' => $limit, 'fromDate' => $fromDate, 'toDate' => $toDate]);
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->get("{$this->apiUrl}/cards/{$cardId}/transactions", $query);

            if ($response->successful()) {
                $body = $response->json();
                return ['success' => true, 'data' => $body['data'] ?? [], 'pagination' => $body['pagination'] ?? null];
            }

            return ['success' => false, 'message' => 'Failed to fetch transactions'];
        } catch (\Exception $e) {
            Log::error('Sudo getCardTransactions exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getTransaction(string $transactionId): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->get("{$this->apiUrl}/cards/transactions/{$transactionId}");

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? $response->json()];
            }

            return ['success' => false, 'message' => 'Failed to fetch transaction'];
        } catch (\Exception $e) {
            Log::error('Sudo getTransaction exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateTransaction(string $transactionId, string $metadata = '{}'): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->put("{$this->apiUrl}/cards/transactions/{$transactionId}", ['metadata' => $metadata]);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? $response->json()];
            }

            return ['success' => false, 'message' => 'Failed to update transaction'];
        } catch (\Exception $e) {
            Log::error('Sudo updateTransaction exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── CARD PROGRAMS ───────────────────────────────────────────

    public function createCardProgram(array $data): array
    {
        try {
            $payload = [
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'status' => $data['status'] ?? 'active',
                'debitAccountId' => $data['debit_account_id'] ?? $this->debitAccountId,
                'fundingSourceId' => $data['funding_source_id'] ?? $this->fundingSourceId,
                'issuerCountry' => $data['issuer_country'] ?? 'NGA',
                'currency' => $data['currency'] ?? 'USD',
                'cardBrand' => $data['card_brand'] ?? 'Visa',
                'cardType' => $data['card_type'] ?? 'virtual',
            ];

            if (!empty($data['spending_controls'])) {
                $payload['spendingControls'] = $data['spending_controls'];
            }

            $response = Http::timeout(30)->withToken($this->apiKey)
                ->post("{$this->apiUrl}/card-programs", $payload);

            if ($response->successful()) {
                $body = $response->json();
                return [
                    'success' => true,
                    'data' => $body['data'] ?? $body,
                    'program_id' => $body['data']['_id'] ?? null,
                ];
            }

            Log::error('Sudo createCardProgram failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'message' => $response->json()['message'] ?? 'Failed to create card program'];
        } catch (\Exception $e) {
            Log::error('Sudo createCardProgram exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getCardPrograms(array $filters = []): array
    {
        try {
            $query = array_filter([
                'searchTerm' => $filters['search_term'] ?? null,
                'cardType' => $filters['card_type'] ?? null,
                'cardBrand' => $filters['card_brand'] ?? null,
                'currency' => $filters['currency'] ?? null,
                'status' => $filters['status'] ?? null,
                'fromDate' => $filters['from_date'] ?? null,
                'toDate' => $filters['to_date'] ?? null,
                'limit' => $filters['limit'] ?? null,
                'page' => $filters['page'] ?? null,
            ]);

            $response = Http::timeout(30)->withToken($this->apiKey)
                ->get("{$this->apiUrl}/card-programs", $query);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? []];
            }

            return ['success' => false, 'message' => 'Failed to fetch card programs'];
        } catch (\Exception $e) {
            Log::error('Sudo getCardPrograms exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getCardProgram(string $id): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->get("{$this->apiUrl}/card-programs/{$id}");

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? $response->json()];
            }

            return ['success' => false, 'message' => 'Failed to fetch card program'];
        } catch (\Exception $e) {
            Log::error('Sudo getCardProgram exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getCardProgramCards(string $programId): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->get("{$this->apiUrl}/card-programs/{$programId}/cards");

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? []];
            }

            return ['success' => false, 'message' => 'Failed to fetch program cards'];
        } catch (\Exception $e) {
            Log::error('Sudo getCardProgramCards exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateCardProgram(string $id, array $data): array
    {
        try {
            $payload = array_filter([
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? null,
                'debitAccountId' => $data['debit_account_id'] ?? null,
                'fundingSourceId' => $data['funding_source_id'] ?? null,
                'issuerCountry' => $data['issuer_country'] ?? null,
                'currency' => $data['currency'] ?? null,
                'cardBrand' => $data['card_brand'] ?? null,
                'cardType' => $data['card_type'] ?? null,
            ], fn($v) => $v !== null);

            $response = Http::timeout(30)->withToken($this->apiKey)
                ->patch("{$this->apiUrl}/card-programs/{$id}", $payload);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? $response->json()];
            }

            Log::error('Sudo updateCardProgram failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'message' => $response->json()['message'] ?? 'Failed to update card program'];
        } catch (\Exception $e) {
            Log::error('Sudo updateCardProgram exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── FUNDING SOURCES ─────────────────────────────────────────

    public function createFundingSource(string $type = 'default', string $status = 'active', array $jitGateway = []): array
    {
        try {
            $payload = ['type' => $type, 'status' => $status];
            if ($type === 'gateway' && !empty($jitGateway)) {
                $payload['jitGateway'] = $jitGateway;
            }

            $response = Http::timeout(30)->withToken($this->apiKey)
                ->post("{$this->apiUrl}/fundingsources", $payload);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? $response->json()];
            }

            Log::error('Sudo createFundingSource failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'message' => $response->json()['message'] ?? 'Failed to create funding source'];
        } catch (\Exception $e) {
            Log::error('Sudo createFundingSource exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getFundingSources(): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->get("{$this->apiUrl}/fundingsources");

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? []];
            }

            return ['success' => false, 'message' => 'Failed to fetch funding sources'];
        } catch (\Exception $e) {
            Log::error('Sudo getFundingSources exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getFundingSource(string $id): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->get("{$this->apiUrl}/fundingsources/{$id}");

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? $response->json()];
            }

            return ['success' => false, 'message' => 'Failed to fetch funding source'];
        } catch (\Exception $e) {
            Log::error('Sudo getFundingSource exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateFundingSource(string $id, string $status = 'active', array $jitGateway = []): array
    {
        try {
            $payload = ['status' => $status];
            if (!empty($jitGateway)) {
                $payload['jitGateway'] = $jitGateway;
            }

            $response = Http::timeout(30)->withToken($this->apiKey)
                ->put("{$this->apiUrl}/fundingsources/{$id}", $payload);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? $response->json()];
            }

            Log::error('Sudo updateFundingSource failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'message' => $response->json()['message'] ?? 'Failed to update funding source'];
        } catch (\Exception $e) {
            Log::error('Sudo updateFundingSource exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── ACCOUNTS ────────────────────────────────────────────────

    public function getAccounts(int $page = 0, int $limit = 25): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->get("{$this->apiUrl}/accounts", ['page' => $page, 'limit' => $limit]);

            if ($response->successful()) {
                $body = $response->json();
                return ['success' => true, 'data' => $body['data'] ?? []];
            }

            return ['success' => false, 'message' => 'Failed to fetch accounts'];
        } catch (\Exception $e) {
            Log::error('Sudo getAccounts exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── AUTHORIZATIONS ──────────────────────────────────────────

    public function getAuthorizations(int $page = 0, int $limit = 25, ?string $fromDate = null, ?string $toDate = null): array
    {
        try {
            $query = array_filter(['page' => $page, 'limit' => $limit, 'fromDate' => $fromDate, 'toDate' => $toDate]);
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->get("{$this->apiUrl}/cards/authorizations", $query);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? []];
            }

            return ['success' => false, 'message' => 'Failed to fetch authorizations'];
        } catch (\Exception $e) {
            Log::error('Sudo getAuthorizations exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getCardAuthorizations(string $cardId, int $page = 0, int $limit = 25, ?string $fromDate = null, ?string $toDate = null): array
    {
        try {
            $query = array_filter(['page' => $page, 'limit' => $limit, 'fromDate' => $fromDate, 'toDate' => $toDate]);
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->get("{$this->apiUrl}/cards/{$cardId}/authorizations", $query);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? []];
            }

            return ['success' => false, 'message' => 'Failed to fetch card authorizations'];
        } catch (\Exception $e) {
            Log::error('Sudo getCardAuthorizations exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getAuthorization(string $authId): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->get("{$this->apiUrl}/cards/authorizations/{$authId}");

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? $response->json()];
            }

            return ['success' => false, 'message' => 'Failed to fetch authorization'];
        } catch (\Exception $e) {
            Log::error('Sudo getAuthorization exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateAuthorization(string $authId, string $metadata = '{}'): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->put("{$this->apiUrl}/cards/authorizations/{$authId}", ['metadata' => $metadata]);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? $response->json()];
            }

            return ['success' => false, 'message' => 'Failed to update authorization'];
        } catch (\Exception $e) {
            Log::error('Sudo updateAuthorization exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── ACCOUNTS: TRANSFER RATES ────────────────────────────────

    public function getTransferRate(string $currencyPair = 'USDNGN'): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->get("{$this->apiUrl}/accounts/transfer/rate/{$currencyPair}");

            if ($response->successful()) {
                $body = $response->json();
                return [
                    'success' => true,
                    'data' => $body['data'] ?? $body,
                ];
            }

            Log::error('Sudo getTransferRate failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'message' => $response->json()['message'] ?? 'Failed to fetch transfer rate'];
        } catch (\Exception $e) {
            Log::error('Sudo getTransferRate exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── DISPUTES ────────────────────────────────────────────────

    public function createDispute(string $transactionId, string $reason, string $explanation, string $metadata = '{}'): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->post("{$this->apiUrl}/cards/disputes", [
                    'transactionId' => $transactionId,
                    'reason' => $reason,
                    'explanation' => $explanation,
                    'metadata' => $metadata,
                ]);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? $response->json()];
            }

            Log::error('Sudo createDispute failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'message' => $response->json()['message'] ?? 'Failed to create dispute'];
        } catch (\Exception $e) {
            Log::error('Sudo createDispute exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getDisputes(int $page = 0, int $limit = 25): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->get("{$this->apiUrl}/cards/disputes", ['page' => $page, 'limit' => $limit]);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? []];
            }

            return ['success' => false, 'message' => 'Failed to fetch disputes'];
        } catch (\Exception $e) {
            Log::error('Sudo getDisputes exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getDispute(string $disputeId): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->get("{$this->apiUrl}/cards/disputes/{$disputeId}");

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? $response->json()];
            }

            return ['success' => false, 'message' => 'Failed to fetch dispute'];
        } catch (\Exception $e) {
            Log::error('Sudo getDispute exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateDispute(string $disputeId, string $reason, string $explanation, string $metadata = '{}'): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->put("{$this->apiUrl}/cards/disputes/{$disputeId}", [
                    'reason' => $reason,
                    'explanation' => $explanation,
                    'metadata' => $metadata,
                ]);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? $response->json()];
            }

            Log::error('Sudo updateDispute failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'message' => $response->json()['message'] ?? 'Failed to update dispute'];
        } catch (\Exception $e) {
            Log::error('Sudo updateDispute exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── SANDBOX SIMULATORS ──────────────────────────────────────

    public function simulateFundAccount(string $accountId, float $amount): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->post("{$this->apiUrl}/accounts/simulator/fund", [
                    'accountId' => $accountId,
                    'amount' => $amount,
                ]);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? $response->json()];
            }

            return ['success' => false, 'message' => 'Failed to simulate fund'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function simulateAuthorization(string $cardId, float $amount, string $channel = 'web'): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->post("{$this->apiUrl}/cards/simulator/authorization", [
                    'cardId' => $cardId,
                    'channel' => $channel,
                    'type' => 'purchase',
                    'amount' => $amount,
                    'currency' => 'USD',
                ]);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? $response->json()];
            }

            return ['success' => false, 'message' => 'Failed to simulate authorization'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── CARD TOKEN (for Secure Proxy Show) ──────────────────────

    public function generateCardToken(string $cardId): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->get("{$this->apiUrl}/cards/{$cardId}/token");

            if ($response->successful()) {
                $body = $response->json();
                return ['success' => true, 'token' => $body['data']['token'] ?? null];
            }

            return ['success' => false, 'message' => $response->json()['message'] ?? 'Failed to generate card token'];
        } catch (\Exception $e) {
            Log::error('Sudo generateCardToken exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function digitalizeCard(string $cardId): array
    {
        try {
            $response = Http::timeout(30)->withToken($this->apiKey)
                ->put("{$this->apiUrl}/cards/digitalize/{$cardId}");

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()['data'] ?? $response->json()];
            }

            return ['success' => false, 'message' => $response->json()['message'] ?? 'Failed to digitalize card'];
        } catch (\Exception $e) {
            Log::error('Sudo digitalizeCard exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function handleWebhook($request)
    {
        Log::info('Sudo Webhook Received', ['payload' => $request->all()]);
        // Implement logic to update card status or settle transactions if needed
        return response()->json(['status' => 'success']);
    }

    // --- ALIASES FOR DOLLAR CARD CONTROLLER PARITY ---

    public function fundVirtualCard(string $cardId, float $amount)
    {
        return $this->fundCard($cardId, $amount);
    }

    public function withdrawVirtualCard(string $cardId, float $amount)
    {
        return $this->withdrawFromCard($cardId, $amount);
    }

    public function terminateVirtualCard(string $cardId, string $reason = 'lost')
    {
        return $this->terminateCard($cardId, $reason);
    }

    public function getTransactions(string $cardId)
    {
        return $this->getCardTransactions($cardId);
    }
}
