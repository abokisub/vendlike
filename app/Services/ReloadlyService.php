<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReloadlyService
{
    private string $authUrl = 'https://auth.reloadly.com';
    private string $baseUrl;
    private string $audience;
    private string $clientId;
    private string $clientSecret;

    public function __construct()
    {
        $env = config('app.reloadly_environment', 'sandbox');
        $this->clientId = config('app.reloadly_client_id', '');
        $this->clientSecret = config('app.reloadly_client_secret', '');

        if ($env === 'production') {
            $this->baseUrl = 'https://giftcards.reloadly.com';
            $this->audience = 'https://giftcards.reloadly.com';
        } else {
            $this->baseUrl = 'https://giftcards-sandbox.reloadly.com';
            $this->audience = 'https://giftcards-sandbox.reloadly.com';
        }
    }

    /**
     * Get access token (cached for 50 days to be safe, token lasts ~60 days)
     */
    public function getAccessToken(): ?string
    {
        return Cache::remember('reloadly_giftcard_token', 60 * 60 * 24 * 50, function () {
            try {
                $response = Http::timeout(30)->post($this->authUrl . '/oauth/token', [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'client_credentials',
                    'audience' => $this->audience,
                ]);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Log::error('Reloadly auth connection failed', ['error' => $e->getMessage()]);
                return null;
            }

            if ($response->successful()) {
                return $response->json('access_token');
            }

            Log::error('Reloadly auth failed', ['response' => $response->json()]);
            return null;
        });
    }

    /**
     * Force refresh the access token
     */
    public function refreshToken(): ?string
    {
        Cache::forget('reloadly_giftcard_token');
        return $this->getAccessToken();
    }

    /**
     * Make authenticated GET request to Reloadly
     */
    private function get(string $endpoint, array $query = []): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['error' => true, 'message' => 'Failed to authenticate with Reloadly'];
        }

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/com.reloadly.giftcards-v1+json',
                'Authorization' => 'Bearer ' . $token,
            ])->timeout(30)->get($this->baseUrl . $endpoint, $query);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Reloadly connection failed', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);
            return ['error' => true, 'message' => 'Could not connect to Reloadly. Please try again later.'];
        }

        if ($response->status() === 401) {
            $token = $this->refreshToken();
            if (!$token) {
                return ['error' => true, 'message' => 'Authentication failed'];
            }
            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/com.reloadly.giftcards-v1+json',
                    'Authorization' => 'Bearer ' . $token,
                ])->timeout(30)->get($this->baseUrl . $endpoint, $query);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                return ['error' => true, 'message' => 'Could not connect to Reloadly. Please try again later.'];
            }
        }

        if ($response->successful()) {
            return ['error' => false, 'data' => $response->json()];
        }

        Log::error('Reloadly GET failed', [
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'response' => $response->json(),
        ]);

        return [
            'error' => true,
            'message' => $response->json('message') ?? 'Request failed',
            'status' => $response->status(),
        ];
    }

    /**
     * Make authenticated POST request to Reloadly
     */
    private function post(string $endpoint, array $data = []): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['error' => true, 'message' => 'Failed to authenticate with Reloadly'];
        }

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/com.reloadly.giftcards-v1+json',
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->baseUrl . $endpoint, $data);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Reloadly connection failed', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);
            return ['error' => true, 'message' => 'Could not connect to Reloadly. Please try again later.'];
        }

        if ($response->status() === 401) {
            $token = $this->refreshToken();
            if (!$token) {
                return ['error' => true, 'message' => 'Authentication failed'];
            }
            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/com.reloadly.giftcards-v1+json',
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ])->timeout(30)->post($this->baseUrl . $endpoint, $data);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                return ['error' => true, 'message' => 'Could not connect to Reloadly. Please try again later.'];
            }
        }

        if ($response->successful()) {
            return ['error' => false, 'data' => $response->json()];
        }

        Log::error('Reloadly POST failed', [
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'response' => $response->json(),
        ]);

        return [
            'error' => true,
            'message' => $response->json('message') ?? 'Request failed',
            'status' => $response->status(),
        ];
    }

    // ========================================
    // PUBLIC API METHODS
    // ========================================

    /**
     * Get Reloadly account balance
     */
    public function getBalance(): array
    {
        return $this->get('/accounts/balance');
    }

    /**
     * Get all gift card categories
     */
    public function getCategories(): array
    {
        return $this->get('/product-categories');
    }

    /**
     * Get all supported countries
     */
    public function getCountries(): array
    {
        return $this->get('/countries');
    }

    /**
     * Get country by ISO code
     */
    public function getCountryByIso(string $isoCode): array
    {
        return $this->get('/countries/' . strtoupper($isoCode));
    }

    /**
     * Get gift card products with optional filters
     */
    public function getProducts(array $filters = []): array
    {
        $query = [];
        if (!empty($filters['size'])) $query['size'] = $filters['size'];
        if (!empty($filters['page'])) $query['page'] = $filters['page'];
        if (!empty($filters['productName'])) $query['productName'] = $filters['productName'];
        if (!empty($filters['countryCode'])) $query['countryCode'] = $filters['countryCode'];
        if (!empty($filters['productCategoryId'])) $query['productCategoryId'] = $filters['productCategoryId'];
        if (isset($filters['includeRange'])) $query['includeRange'] = $filters['includeRange'];
        if (isset($filters['includeFixed'])) $query['includeFixed'] = $filters['includeFixed'];

        return $this->get('/products', $query);
    }

    /**
     * Get single product by ID
     */
    public function getProduct(int $productId): array
    {
        return $this->get('/products/' . $productId);
    }

    /**
     * Get products available in a specific country
     */
    public function getProductsByCountry(string $countryCode): array
    {
        return $this->get('/countries/' . strtoupper($countryCode) . '/products');
    }

    /**
     * Get redeem instructions for a product
     */
    public function getRedeemInstructions(int $productId): array
    {
        return $this->get('/products/' . $productId . '/redeem-instructions');
    }

    /**
     * Get FX rate
     */
    public function getFxRate(string $currencyCode, float $amount): array
    {
        return $this->get('/fx-rate', [
            'currencyCode' => $currencyCode,
            'amount' => $amount,
        ]);
    }

    /**
     * Get discount for a product
     */
    public function getProductDiscount(int $productId): array
    {
        return $this->get('/products/' . $productId . '/discounts');
    }

    /**
     * Order (purchase) a gift card
     */
    public function orderGiftCard(array $orderData): array
    {
        return $this->post('/orders', $orderData);
    }

    /**
     * Get redeem code for a completed transaction (v2 with redemptionUrl)
     */
    public function getRedeemCode(int $transactionId): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['error' => true, 'message' => 'Failed to authenticate with Reloadly'];
        }

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/com.reloadly.giftcards-v2+json',
                'Authorization' => 'Bearer ' . $token,
            ])->timeout(30)->get($this->baseUrl . '/orders/transactions/' . $transactionId . '/cards');
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return ['error' => true, 'message' => 'Could not connect to Reloadly. Please try again later.'];
        }

        if ($response->status() === 401) {
            $token = $this->refreshToken();
            if (!$token) {
                return ['error' => true, 'message' => 'Authentication failed'];
            }
            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/com.reloadly.giftcards-v2+json',
                    'Authorization' => 'Bearer ' . $token,
                ])->timeout(30)->get($this->baseUrl . '/orders/transactions/' . $transactionId . '/cards');
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                return ['error' => true, 'message' => 'Could not connect to Reloadly. Please try again later.'];
            }
        }

        if ($response->successful()) {
            return ['error' => false, 'data' => $response->json()];
        }

        return [
            'error' => true,
            'message' => $response->json('message') ?? 'Failed to get redeem code',
            'status' => $response->status(),
        ];
    }

    /**
     * Get transaction by ID from Reloadly
     */
    public function getTransaction(int $transactionId): array
    {
        return $this->get('/reports/transactions/' . $transactionId);
    }

    /**
     * Get transaction history from Reloadly
     */
    public function getTransactions(array $filters = []): array
    {
        return $this->get('/reports/transactions', $filters);
    }
}
