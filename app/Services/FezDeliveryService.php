<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FezDeliveryService
{
    private $baseUrl;
    private $userId;
    private $password;
    private $secretKey;

    public function __construct()
    {
        $env = config('services.fez.environment', 'sandbox');
        $this->baseUrl = $env === 'production'
            ? 'https://api.fezdelivery.co/v1'
            : 'https://apisandbox.fezdelivery.co/v1';
        $this->userId = config('services.fez.user_id', '');
        $this->password = config('services.fez.password', '');
        $this->secretKey = config('services.fez.secret_key', '');
    }

    /**
     * Get auth token (cached until expiry)
     */
    private function getAuthToken(): ?string
    {
        return Cache::remember('fez_auth_token', 3500, function () {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->baseUrl . '/user/authenticate',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POSTFIELDS => json_encode([
                    'user_id' => $this->userId,
                    'password' => $this->password,
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            ]);
            $response = json_decode(curl_exec($ch), true);
            curl_close($ch);

            if (isset($response['authDetails']['authToken'])) {
                return $response['authDetails']['authToken'];
            }
            Log::error('Fez auth failed', ['response' => $response]);
            return null;
        });
    }

    /**
     * Make authenticated API request
     */
    private function request(string $method, string $endpoint, array $data = [], bool $retry = true)
    {
        $token = $this->getAuthToken();
        if (!$token) {
            return ['status' => 'Error', 'description' => 'Authentication failed'];
        }

        $ch = curl_init();
        $url = $this->baseUrl . $endpoint;

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'secret-key: ' . $this->secretKey,
            ],
        ];

        if ($method === 'GET') {
            $opts[CURLOPT_URL] = $url;
            $opts[CURLOPT_CUSTOMREQUEST] = 'GET';
        } elseif ($method === 'POST') {
            $opts[CURLOPT_URL] = $url;
            $opts[CURLOPT_CUSTOMREQUEST] = 'POST';
            $opts[CURLOPT_POSTFIELDS] = json_encode($data);
        } elseif ($method === 'PUT') {
            $opts[CURLOPT_URL] = $url;
            $opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
            $opts[CURLOPT_POSTFIELDS] = json_encode($data);
        } elseif ($method === 'DELETE') {
            $opts[CURLOPT_URL] = $url;
            $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
            $opts[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($raw, true);

        // Retry on 401 (token expired)
        if ($httpCode === 401 && $retry) {
            Cache::forget('fez_auth_token');
            return $this->request($method, $endpoint, $data, false);
        }

        return $response ?? ['status' => 'Error', 'description' => 'No response from Fez'];
    }

    /**
     * Get delivery cost for a destination state + weight
     */
    public function getDeliveryCost(string $state, ?float $weight = null, ?string $pickUpState = null): array
    {
        $body = ['state' => $state];
        if ($weight) $body['weight'] = $weight;
        if ($pickUpState) $body['pickUpState'] = $pickUpState;

        return $this->request('POST', '/order/cost', $body);
    }

    /**
     * Get delivery cost for ALL states (no state param)
     */
    public function getAllDeliveryCosts(?float $weight = null): array
    {
        $body = [];
        if ($weight) $body['weight'] = $weight;
        return $this->request('POST', '/order/cost', $body);
    }

    /**
     * Create order (book delivery)
     */
    public function createOrder(array $orderData): array
    {
        return $this->request('POST', '/order', [$orderData]);
    }

    /**
     * Get order details
     */
    public function getOrder(string $orderNo): array
    {
        return $this->request('GET', '/orders/' . $orderNo);
    }

    /**
     * Track order (get status + timeline)
     */
    public function trackOrder(string $orderNo): array
    {
        return $this->request('GET', '/order/track/' . $orderNo);
    }

    /**
     * Get delivery time estimate
     */
    public function getDeliveryEstimate(string $pickUpState, string $dropOffState): array
    {
        return $this->request('POST', '/delivery-time-estimate', [
            'delivery_type' => 'local',
            'pick_up_state' => $pickUpState,
            'drop_off_state' => $dropOffState,
        ]);
    }

    /**
     * Get all states
     */
    public function getStates(): array
    {
        return $this->request('GET', '/states');
    }

    /**
     * Search orders
     */
    public function searchOrders(string $startDate, string $endDate, int $page = 1, array $filters = []): array
    {
        $body = array_merge(['startDate' => $startDate, 'endDate' => $endDate, 'page' => $page], $filters);
        return $this->request('POST', '/orders/search', $body);
    }
}
