<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$secretKey = env('XIXAPAY_SECRET_KEY');
$apiKey = env('XIXAPAY_API_KEY');
$businessId = env('XIXAPAY_BUSINESS_ID');

$u20 = DB::table('user')->where('id', 20)->first();
echo "User: {$u20->username} | email: {$u20->email}\n";

// Try to re-create customer on Xixapay — if already exists it returns the existing customer_id
$kycDocs = json_decode($u20->kyc_documents ?? '{}', true);
$meta = $kycDocs['submitted_metadata'] ?? [];

$nameParts = explode(' ', $u20->name ?? '');
$firstName = $nameParts[0] ?? 'User';
$lastName = implode(' ', array_slice($nameParts, 1)) ?: $firstName;

// Build multipart request
$response = Http::timeout(60)
    ->withHeaders([
        'Authorization' => 'Bearer ' . $secretKey,
        'api-key' => $apiKey,
    ])
    ->asMultipart()
    ->post('https://api.xixapay.com/api/customer/create', [
        ['name' => 'businessId', 'contents' => $businessId],
        ['name' => 'first_name', 'contents' => $firstName],
        ['name' => 'last_name', 'contents' => $lastName],
        ['name' => 'email', 'contents' => $u20->email],
        ['name' => 'phone_number', 'contents' => $meta['phone'] ?? $u20->username],
        ['name' => 'address', 'contents' => $meta['address'] ?? '10 Test Street'],
        ['name' => 'state', 'contents' => $meta['state'] ?? 'Lagos'],
        ['name' => 'city', 'contents' => $meta['city'] ?? 'Ikeja'],
        ['name' => 'postal_code', 'contents' => $meta['postal_code'] ?? '100001'],
        ['name' => 'date_of_birth', 'contents' => $meta['dob'] ?? '1990-01-01'],
        ['name' => 'id_type', 'contents' => $kycDocs['id_type'] ?? 'bvn'],
        ['name' => 'id_number', 'contents' => $kycDocs['id_number'] ?? $u20->bvn],
    ]);

$data = $response->json();
echo "Xixapay response: " . json_encode($data) . "\n";

// Extract customer_id
$customerId = $data['customer']['customer_id']
    ?? $data['data']['customer_id']
    ?? null;

if (!$customerId && isset($data['message']) && str_contains(strtolower($data['message']), 'already exists')) {
    echo "Customer already exists on Xixapay — need to find their customer_id\n";
    echo "Please check Xixapay dashboard for email: {$u20->email}\n";
} elseif ($customerId) {
    echo "Got customer_id: $customerId\n";

    // Save to user table
    DB::table('user')->where('id', 20)->update(['customer_id' => $customerId]);

    // Save to dollar_customers
    DB::table('dollar_customers')->updateOrInsert(
        ['user_id' => 20, 'provider' => 'xixapay'],
        [
            'customer_id' => $customerId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $u20->email,
            'phone' => $meta['phone'] ?? $u20->username,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );
    echo "Saved to user table and dollar_customers. Done!\n";
} else {
    echo "Could not get customer_id. Full response above.\n";
}
