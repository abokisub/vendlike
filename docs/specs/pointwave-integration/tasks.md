# Implementation Plan: PointWave Integration

## Overview

This implementation plan breaks down the PointWave payment gateway integration into discrete, incremental coding tasks. Each task builds on previous work and includes specific requirements references. The plan follows an 8-phase approach from database foundation through production launch, with testing integrated throughout.

**Key Constraints**:
- NO BREAKING CHANGES: Preserve all existing functionality
- INCREMENTAL MIGRATIONS ONLY: New tables with "pointwave_" prefix only
- NO migrate:fresh or migrate:refresh commands
- Work alongside existing providers (Xixapay, Paystack, Monnify)
- Integrate with existing user.bal column for wallet operations

**Implementation Language**: PHP (Laravel 8)
**Testing Framework**: Pest PHP with property testing plugin
**Target Coverage**: 80% overall (90% service, 85% controller, 75% model)

## Tasks

- [-] 1. Phase 1: Database Foundation and Models
  - [x] 1.1 Create database migration for pointwave_virtual_accounts table
    - Create migration file with schema: id, user_id, customer_id, account_number, account_name, bank_name, bank_code, status, external_reference, timestamps
    - Add unique constraints on user_id, customer_id, account_number, external_reference
    - Add indexes on user_id, customer_id, status
    - Add foreign key constraint to user table with CASCADE delete
    - _Requirements: 1.3, 1.4, 1.7, 1.8, 1.9, 1.12_
  
  - [x] 1.2 Create database migration for pointwave_transactions table
    - Create migration file with schema: id, user_id, type, amount, fee, status, reference, pointwave_transaction_id, pointwave_customer_id, account_number, bank_code, account_name, narration, metadata (JSON), timestamps
    - Add unique constraint on reference
    - Add indexes on user_id, type, status, reference, pointwave_transaction_id, created_at
    - Add composite index on (user_id, type, status) for common queries
    - Add foreign key constraint to user table with CASCADE delete
    - _Requirements: 2.6, 2.9, 6.2, 6.3, 6.9_

  - [x] 1.3 Create database migration for pointwave_kyc table
    - Create migration file with schema: id, user_id, id_type, id_number_encrypted (TEXT), kyc_status, tier, daily_limit, verified_at, timestamps
    - Add unique constraint on user_id
    - Add indexes on kyc_status, tier
    - Add foreign key constraint to user table with CASCADE delete
    - Set default values: kyc_status='not_submitted', tier='tier_1', daily_limit=300000.00
    - _Requirements: 7.1, 7.2, 7.3, 7.5, 7.6, 7.8_
  
  - [x] 1.4 Create database migration for pointwave_webhooks table
    - Create migration file with schema: id, event_type, payload (JSON), signature, processed (BOOLEAN), processed_at, error_message (TEXT), created_at
    - Add indexes on event_type, processed, created_at
    - Add composite index on (processed, created_at) for unprocessed webhook queries
    - _Requirements: 4.9, 5.9, 19.3, 19.10_
  
  - [x] 1.5 Create database migration for pointwave_settings table
    - Create migration file with schema: id, key, value (TEXT), updated_by, updated_at
    - Add unique constraint on key
    - Add index on key
    - No timestamps (using updated_at only)
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_
  
  - [x] 1.6 Create seeder for default PointWave settings
    - Create PointWaveSettingsSeeder with default values
    - Seed: pointwave_enabled=true, pointwave_transfer_fee=50.00, pointwave_min_transfer=100.00, pointwave_max_transfer=5000000.00, pointwave_auto_create_virtual_account=true
    - Use updateOrCreate to avoid duplicates on re-seeding
    - _Requirements: 2.2, 2.3, 2.4, 8.1_
  
  - [x] 1.7 Create PointWaveVirtualAccount model
    - Create model with fillable fields: user_id, customer_id, account_number, account_name, bank_name, bank_code, status, external_reference
    - Add belongsTo relationship to User model
    - Add casts for timestamps
    - _Requirements: 1.3, 1.4_
  
  - [x] 1.8 Create PointWaveTransaction model
    - Create model with fillable fields: user_id, type, amount, fee, status, reference, pointwave_transaction_id, pointwave_customer_id, account_number, bank_code, account_name, narration, metadata
    - Add belongsTo relationship to User model
    - Add casts: amount=decimal:2, fee=decimal:2, metadata=array, timestamps
    - Add scopes: deposits(), transfers(), pending(), successful()
    - _Requirements: 6.2, 6.3, 6.4, 6.9_
  
  - [x] 1.9 Create PointWaveKYC model
    - Create model with fillable fields: user_id, id_type, id_number_encrypted, kyc_status, tier, daily_limit, verified_at
    - Add belongsTo relationship to User model
    - Add accessor getIdNumberAttribute() to decrypt id_number_encrypted
    - Add mutator setIdNumberAttribute() to encrypt id_number before storage
    - Add casts: daily_limit=decimal:2, verified_at=datetime, timestamps
    - _Requirements: 7.1, 7.5, 7.6, 7.8_
  
  - [x] 1.10 Create PointWaveWebhook model
    - Create model with fillable fields: event_type, payload, signature, processed, processed_at, error_message
    - Add casts: payload=array, processed=boolean, processed_at=datetime, created_at=datetime
    - Add scopes: unprocessed(), byEventType($eventType)
    - _Requirements: 4.9, 19.10_
  
  - [x] 1.11 Create PointWaveSetting model
    - Create model with fillable fields: key, value, updated_by, updated_at
    - Disable timestamps (public $timestamps = false)
    - Add static helper method get($key, $default = null)
    - Add static helper method set($key, $value, $updatedBy = null)
    - _Requirements: 8.1, 8.2_
  
  - [ ]* 1.12 Create model factories for testing
    - Create PointWaveVirtualAccountFactory with realistic test data
    - Create PointWaveTransactionFactory with realistic test data
    - Create PointWaveKYCFactory with realistic test data
    - Create PointWaveWebhookFactory with realistic test data
    - _Requirements: Testing infrastructure_

- [x] 2. Checkpoint - Verify database foundation
  - Ensure all migrations run successfully without errors
  - Verify all models load correctly
  - Verify relationships work as expected
  - Ask the user if questions arise

- [x] 3. Phase 2: Enhanced PointWave Service Layer
  - [x] 3.1 Enhance PointWaveService with retry logic and error handling
    - Add private method retryRequest(callable $request, int $maxRetries = 2) with exponential backoff (1s, 2s)
    - Add private method handleApiError(\Exception $e) to format error responses
    - Add timeout configuration (30 seconds) to all HTTP requests
    - Add comprehensive request/response logging to 'pointwave' log channel
    - Mask sensitive data in logs (API keys, account numbers, BVN/NIN)
    - _Requirements: 10.5, 10.6, 11.1, 11.2, 11.3_
  
  - [x] 3.2 Implement virtual account creation in PointWaveService
    - Enhance createVirtualAccount(array $data) method
    - Generate unique external_reference using "PW-VA-{timestamp}-{user_id}"
    - Build request payload with first_name, last_name, email, phone_number, external_reference
    - Set account_type='static' and provider='palmpay' (bank_code='100033')
    - Make POST request to /virtual-accounts endpoint
    - Return formatted response with customer_id, account_number, account_name, bank_name
    - _Requirements: 1.1, 1.2, 1.3, 1.7, 1.8, 1.9_
  
  - [x] 3.3 Implement bank operations in PointWaveService
    - Implement getBanks() method with 24-hour caching
    - Implement verifyBankAccount(string $accountNumber, string $bankCode) with 24-hour caching
    - Cache key format: "pointwave_banks" and "pointwave_verify_{accountNumber}_{bankCode}"
    - Make GET request to /banks endpoint
    - Make POST request to /banks/verify endpoint
    - Return account_name on successful verification
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.7_
  
  - [x] 3.4 Implement transfer operations in PointWaveService
    - Implement initiateTransfer(array $data) method
    - Generate unique reference using "PW-{timestamp}-{user_id}"
    - Generate idempotency key and include in headers
    - Build request payload with amount, account_number, bank_code, narration, reference
    - Make POST request to /transfers endpoint
    - Return formatted response with transaction details
    - _Requirements: 2.1, 2.6, 2.7, 2.8, 2.11_
  
  - [x] 3.5 Implement transaction query methods in PointWaveService
    - Implement getTransfer(string $reference) method
    - Implement getTransactions(array $filters = []) method
    - Implement getTransaction(string $transactionId) method
    - Make GET requests to appropriate endpoints
    - Support filtering by date range, status, type
    - _Requirements: 6.1, 6.4, 6.5_
  
  - [x] 3.6 Implement wallet and customer methods in PointWaveService
    - Implement getWalletBalance() method
    - Implement getCustomer(string $customerId) method
    - Implement updateCustomer(string $customerId, array $data) method
    - Make GET/PUT requests to appropriate endpoints
    - _Requirements: 9.1, 9.2, 9.3_
  
  - [ ]* 3.7 Write unit tests for PointWaveService
    - Test createVirtualAccount with correct payload structure
    - Test API timeout with retry logic (HTTP::sequence())
    - Test idempotency key inclusion in transfer requests
    - Test error handling for 401, 429, 500 status codes
    - Test caching for getBanks and verifyBankAccount
    - Target 90% coverage for service layer
    - _Requirements: Testing_

- [x] 4. Checkpoint - Verify service layer
  - Ensure all service methods work correctly
  - Verify retry logic and error handling
  - Verify caching works as expected
  - Ask the user if questions arise

- [x] 5. Phase 3: Virtual Account and Transfer Controllers
  - [x] 5.1 Create PointWaveController for user-facing endpoints
    - Create controller at app/Http/Controllers/API/PointWaveController.php
    - Add constructor with PointWaveService dependency injection
    - Apply auth:sanctum middleware to all routes
    - _Requirements: 1.10, 2.1_
  
  - [x] 5.2 Implement virtual account endpoints in PointWaveController
    - Implement getVirtualAccount(Request $request) method
    - Query pointwave_virtual_accounts by auth()->id()
    - Return existing account or null if not found
    - Implement createVirtualAccount(Request $request) method
    - Check for existing account (return if exists)
    - Call PointWaveService->createVirtualAccount()
    - Store result in pointwave_virtual_accounts table
    - Return virtual account details
    - _Requirements: 1.10, 1.11, 1.12, 1.13_
  
  - [x] 5.3 Implement bank operations endpoints in PointWaveController
    - Implement getBanks(Request $request) method
    - Call PointWaveService->getBanks()
    - Return list of banks with code and name
    - Implement verifyAccount(Request $request) method
    - Validate input: account_number (required, digits:10), bank_code (required)
    - Call PointWaveService->verifyBankAccount()
    - Return account_name on success
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_
  
  - [x] 5.4 Implement transfer initiation endpoint in PointWaveController
    - Implement initiateTransfer(Request $request) method
    - Validate input: amount (required, numeric, min:100, max:5000000), account_number (required, digits:10), bank_code (required), narration (nullable, string, max:255)
    - Check user wallet balance >= (amount + 50)
    - Check KYC tier limits if applicable
    - Start database transaction
    - Deduct (amount + fee) from user.bal
    - Create pending transaction record in pointwave_transactions
    - Call PointWaveService->initiateTransfer()
    - If API call fails: rollback transaction, refund wallet, mark transaction as failed
    - If API call succeeds: commit transaction
    - Return transfer details
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.9, 2.10, 2.12, 7.9_
  
  - [x] 5.5 Implement transaction query endpoints in PointWaveController
    - Implement getTransactions(Request $request) method
    - Query pointwave_transactions where user_id = auth()->id()
    - Support filtering by type, status, date_from, date_to
    - Apply pagination (20 per page default)
    - Order by created_at DESC
    - Return paginated transaction list
    - Implement getTransaction(Request $request, string $reference) method
    - Query pointwave_transactions where reference = $reference AND user_id = auth()->id()
    - Return single transaction or 404
    - _Requirements: 6.1, 6.4, 6.5, 6.6, 6.7_
  
  - [ ]* 5.6 Write feature tests for PointWaveController
    - Test virtual account creation and retrieval
    - Test duplicate virtual account prevention
    - Test bank list retrieval
    - Test account verification
    - Test transfer with insufficient balance (422 error)
    - Test transfer with invalid amount (422 error)
    - Test transfer creates pending transaction
    - Test transfer deducts correct amount from wallet
    - Test transaction list filtering and pagination
    - Target 85% coverage for controller layer
    - _Requirements: Testing_

- [x] 6. Checkpoint - Verify virtual account and transfer flows
  - Ensure virtual account creation works end-to-end
  - Ensure transfer initiation works with proper validation
  - Ensure wallet deduction and rollback work correctly
  - Ask the user if questions arise

- [x] 7. Phase 4: Webhook Processing
  - [x] 7.1 Create VerifyPointWaveWebhook middleware
    - Create middleware at app/Http/Middleware/VerifyPointWaveWebhook.php
    - Extract X-Pointwave-Signature header from request
    - Get raw request payload using $request->getContent()
    - Compute expected signature using hash_hmac('sha256', $payload, env('POINTWAVE_SECRET_KEY'))
    - Use hash_equals() for constant-time comparison (prevent timing attacks)
    - Return 401 Unauthorized if signature doesn't match
    - Log all failed verification attempts with IP address
    - _Requirements: 4.1, 4.2, 10.1, 10.2, 10.3, 10.4_
  
  - [x] 7.2 Create PointWaveWebhookController
    - Create controller at app/Http/Controllers/API/PointWaveWebhookController.php
    - Apply VerifyPointWaveWebhook middleware
    - Apply throttle:100,1 rate limiting (100 requests per minute)
    - _Requirements: 4.1, 4.2_
  
  - [x] 7.3 Implement webhook handler with duplicate detection
    - Implement handleWebhook(Request $request) method
    - Extract event_type and data from payload
    - Validate required fields: event_type, data, timestamp
    - Check for duplicate using pointwave_transaction_id in payload
    - If duplicate found: return 200 OK with "Already processed" message
    - Store webhook in pointwave_webhooks table (processed=false)
    - Dispatch ProcessPointWaveWebhook job to queue
    - Return 200 OK immediately (acknowledge receipt)
    - _Requirements: 4.3, 4.9, 5.9, 19.3_
  
  - [x] 7.4 Create ProcessPointWaveWebhook job
    - Create job at app/Jobs/ProcessPointWaveWebhook.php
    - Implement ShouldQueue interface
    - Set tries = 3, timeout = 60
    - Accept webhookData array in constructor
    - Implement handle() method to process webhook based on event_type
    - Implement failed(\Exception $exception) method to log failures
    - _Requirements: 4.3, 4.4_
  
  - [x] 7.5 Implement payment.received webhook processing
    - In ProcessPointWaveWebhook job, handle 'payment.received' event
    - Extract customer_id, amount, transaction_id, reference from payload
    - Find user by querying pointwave_virtual_accounts where customer_id matches
    - Start database transaction
    - Credit user.bal with payment amount
    - Create transaction record: type='deposit', status='successful', amount, pointwave_transaction_id
    - Update webhook record: processed=true, processed_at=now()
    - Commit transaction
    - Dispatch SendPointWaveNotification job
    - If any error: rollback, log error, update webhook with error_message
    - _Requirements: 4.4, 4.5, 4.6, 4.7, 4.8_
  
  - [x] 7.6 Implement transfer.success webhook processing
    - In ProcessPointWaveWebhook job, handle 'transfer.success' event
    - Extract reference, transaction_id from payload
    - Find transaction by reference in pointwave_transactions
    - Update transaction: status='successful', pointwave_transaction_id
    - Update webhook record: processed=true, processed_at=now()
    - Dispatch SendPointWaveNotification job
    - _Requirements: 5.1, 5.2, 5.3, 5.4_
  
  - [x] 7.7 Implement transfer.failed webhook processing
    - In ProcessPointWaveWebhook job, handle 'transfer.failed' event
    - Extract reference, transaction_id, reason from payload
    - Find transaction by reference in pointwave_transactions
    - Start database transaction
    - Refund user.bal with (amount + fee)
    - Update transaction: status='failed', pointwave_transaction_id, narration with failure reason
    - Update webhook record: processed=true, processed_at=now()
    - Commit transaction
    - Dispatch SendPointWaveNotification job
    - _Requirements: 5.5, 5.6, 5.7, 5.8_
  
  - [x] 7.8 Create SendPointWaveNotification job
    - Create job at app/Jobs/SendPointWaveNotification.php
    - Implement ShouldQueue interface
    - Set tries = 3
    - Accept User $user, string $notificationType, array $data in constructor
    - Implement handle() method to send email/SMS/in-app notifications
    - Use existing Laravel notification system
    - Support notification types: payment_received, transfer_success, transfer_failed
    - _Requirements: 4.7, 5.3, 5.7_
  
  - [ ]* 7.9 Write feature tests for webhook processing
    - Test webhook with invalid signature (401 error)
    - Test webhook with valid signature (200 OK)
    - Test payment.received credits wallet correctly
    - Test payment.received creates deposit transaction
    - Test duplicate webhook prevention (same transaction_id)
    - Test transfer.success updates transaction status
    - Test transfer.failed refunds wallet
    - Test transfer.failed updates transaction status
    - Test webhook payload validation (missing required fields)
    - _Requirements: Testing_
  
  - [ ]* 7.10 Write property test for webhook idempotency
    - **Property 13: Webhook Idempotency**
    - **Validates: Requirements 4.9, 5.9**
    - Generate random transaction_id and amount
    - Process webhook once, record wallet balance
    - Attempt to process same webhook again
    - Assert wallet balance unchanged after second processing
    - Run 100 iterations
    - _Requirements: 4.9, 5.9_

- [x] 8. Checkpoint - Verify webhook processing
  - Ensure webhook signature verification works
  - Ensure payment.received credits wallet correctly
  - Ensure transfer webhooks update status correctly
  - Ensure duplicate detection prevents double processing
  - Ask the user if questions arise

- [x] 9. Phase 5: KYC Integration and Transaction Management
  - [x] 9.1 Implement KYC submission endpoint in PointWaveController
    - Implement submitKYC(Request $request) method
    - Validate input: id_type (required, in:bvn,nin), id_number (required, digits:11)
    - Check for existing KYC record for user
    - If exists and verified: return error "KYC already verified"
    - Create or update pointwave_kyc record
    - Encrypt id_number before storage (use model mutator)
    - Set kyc_status='pending', tier='tier_1', daily_limit=300000.00
    - Return success message
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6_
  
  - [x] 9.2 Implement KYC verification logic (admin-triggered)
    - Add method verifyKYC(int $userId) in PointWaveController or admin controller
    - Find KYC record by user_id
    - Update: kyc_status='verified', tier='tier_3', daily_limit=5000000.00, verified_at=now()
    - Return success message
    - _Requirements: 7.7, 7.9_
  
  - [x] 9.3 Add KYC tier limit enforcement to transfer endpoint
    - In PointWaveController->initiateTransfer(), after amount validation
    - Query user's KYC record
    - If no KYC: use tier_1 limit (₦300,000)
    - If KYC verified: use tier_3 limit (₦5,000,000)
    - Check if transfer amount exceeds daily_limit
    - If exceeds: return 422 error with message "Transfer exceeds your tier limit. Please complete KYC verification."
    - _Requirements: 7.8, 7.9_
  
  - [ ]* 9.4 Write unit tests for KYC functionality
    - Test KYC submission with valid BVN (11 digits)
    - Test KYC submission with valid NIN (11 digits)
    - Test KYC submission with invalid length (reject)
    - Test KYC id_number encryption in database
    - Test KYC id_number decryption on retrieval
    - Test tier upgrade on verification
    - Test daily limit update on verification
    - Target 75% coverage for model layer
    - _Requirements: Testing_
  
  - [ ]* 9.5 Write property test for KYC validation
    - **Property 24: KYC ID Number Length**
    - **Validates: Requirements 7.2, 7.3**
    - Generate random 11-digit number (valid)
    - Generate random 10-digit number (invalid)
    - Validate both using Laravel validator
    - Assert valid passes, invalid fails
    - Run 100 iterations
    - _Requirements: 7.2, 7.3_
  
  - [ ]* 9.6 Write property test for tier-based limits
    - **Property 29: Tier-Based Transfer Limit Enforcement**
    - **Validates: Requirements 7.9**
    - Create user with random tier (tier_1 or tier_3)
    - Generate transfer amount above tier limit
    - Attempt transfer
    - Assert transfer rejected with appropriate error
    - Run 100 iterations
    - _Requirements: 7.9_

- [x] 10. Checkpoint - Verify KYC and limits
  - Ensure KYC submission works correctly
  - Ensure ID number encryption works
  - Ensure tier limits are enforced on transfers
  - Ask the user if questions arise

- [x] 11. Phase 6: Admin Features and Reconciliation
  - [x] 11.1 Create PointWaveAdminController
    - Create controller at app/Http/Controllers/Admin/PointWaveAdminController.php
    - Apply auth:sanctum and admin middleware
    - Add constructor with PointWaveService dependency injection
    - _Requirements: 8.1, 9.1_
  
  - [x] 11.2 Implement admin dashboard endpoint
    - Implement dashboard(Request $request) method
    - Query statistics: total transactions, successful count, failed count, pending count
    - Query total volume: sum of successful deposits, sum of successful transfers
    - Query recent transactions (last 10)
    - Query failed transactions requiring attention
    - Return dashboard data
    - _Requirements: 9.1, 9.2, 9.3_
  
  - [x] 11.3 Implement admin transaction management endpoints
    - Implement transactions(Request $request) method
    - Query all pointwave_transactions (not filtered by user)
    - Support filtering by user_id, type, status, date_from, date_to
    - Support search by reference, account_number, account_name
    - Apply pagination (50 per page)
    - Order by created_at DESC
    - Return paginated transaction list
    - _Requirements: 9.2, 9.4_
  
  - [x] 11.4 Implement manual refund endpoint
    - Implement refundTransaction(Request $request, int $id) method
    - Find transaction by id
    - Validate transaction is type='transfer' and status='successful'
    - Start database transaction
    - Credit user.bal with (amount + fee)
    - Update transaction: status='refunded', narration with refund reason
    - Log refund action with admin_id
    - Commit transaction
    - Dispatch notification to user
    - Return success message
    - _Requirements: 9.5_
  
  - [x] 11.5 Implement settings management endpoints
    - Implement getSettings(Request $request) method
    - Query all records from pointwave_settings
    - Return key-value pairs
    - Implement updateSettings(Request $request) method
    - Validate input: enabled (boolean), transfer_fee (numeric, min:0), min_transfer (numeric, min:0), max_transfer (numeric, min:0), auto_create_virtual_account (boolean)
    - Update each setting using PointWaveSetting::set()
    - Log changes with admin_id
    - Return success message
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_
  
  - [x] 11.6 Implement reconciliation endpoint
    - Implement reconcile(Request $request) method
    - Query local transactions from pointwave_transactions for specified date range
    - Call PointWaveService->getTransactions() for same date range
    - Compare local vs remote transactions by reference
    - Identify discrepancies: missing locally, missing remotely, status mismatch
    - Return reconciliation report with discrepancies
    - Optionally auto-sync missing transactions
    - _Requirements: 9.6, 9.7, 9.8_
  
  - [x] 11.7 Implement wallet balance endpoint
    - Implement getBalance(Request $request) method
    - Call PointWaveService->getWalletBalance()
    - Return PointWave wallet balance
    - Compare with local transaction totals
    - _Requirements: 9.1_
  
  - [ ]* 11.8 Write feature tests for admin endpoints
    - Test admin dashboard loads statistics correctly
    - Test admin can view all transactions (not just own)
    - Test admin can filter and search transactions
    - Test admin can refund transaction
    - Test admin can update settings
    - Test admin can run reconciliation
    - Test non-admin cannot access admin endpoints (403 error)
    - _Requirements: Testing_

- [x] 12. Checkpoint - Verify admin features
  - Ensure admin dashboard displays correct statistics
  - Ensure admin can manage settings
  - Ensure reconciliation identifies discrepancies
  - Ensure manual refunds work correctly
  - Ask the user if questions arise

- [ ] 13. Phase 7: Security, Rate Limiting, and Logging
  - [ ] 13.1 Create RateLimitPointWave middleware
    - Create middleware at app/Http/Middleware/RateLimitPointWave.php
    - Implement per-user rate limiting using Cache
    - Key format: "pointwave_api_{user_id}"
    - Limit: 60 requests per minute per user
    - If limit exceeded: return 429 error with retry-after header
    - _Requirements: 10.7, 10.8_
  
  - [ ] 13.2 Apply rate limiting to all PointWave routes
    - Apply RateLimitPointWave middleware to PointWaveController routes
    - Apply throttle:100,1 to webhook endpoint
    - Apply throttle:60,1 to user endpoints
    - Apply throttle:120,1 to admin endpoints
    - _Requirements: 10.7, 10.8_
  
  - [ ] 13.3 Configure dedicated PointWave log channel
    - Add 'pointwave' channel to config/logging.php
    - Use 'daily' driver with 90-day retention
    - Set path to storage/logs/pointwave.log
    - Set level to 'info'
    - _Requirements: 11.1, 11.2_
  
  - [ ] 13.4 Add comprehensive logging to all operations
    - Log virtual account creation (INFO level)
    - Log transfer initiation (INFO level)
    - Log webhook receipt (INFO level)
    - Log webhook processing (INFO level)
    - Log failed signature verification (WARNING level)
    - Log API errors (ERROR level)
    - Log rate limit hits (WARNING level)
    - Include request_id, user_id, duration_ms in all logs
    - Mask sensitive data (API keys, account numbers, BVN/NIN)
    - _Requirements: 11.1, 11.2, 11.3, 11.4_
  
  - [ ] 13.5 Implement audit logging for security events
    - Create 'security' log channel in config/logging.php
    - Log failed authentication attempts
    - Log suspicious activity (multiple failed transfers)
    - Log admin actions (settings changes, manual refunds)
    - Log KYC submissions and verifications
    - Include IP address, user_id, timestamp in all security logs
    - _Requirements: 11.5_
  
  - [ ] 13.6 Add CSRF exception for webhook endpoint
    - Update app/Http/Middleware/VerifyCsrfToken.php
    - Add 'api/pointwave/webhook' to $except array
    - _Requirements: 4.1_
  
  - [ ] 13.7 Configure HTTPS enforcement for production
    - Add URL::forceScheme('https') in AppServiceProvider for production environment
    - _Requirements: 10.9_
  
  - [ ]* 13.8 Write security tests
    - Test rate limiting blocks excessive requests
    - Test CSRF protection on non-webhook endpoints
    - Test webhook signature verification with various attack vectors
    - Test SQL injection prevention (parameterized queries)
    - Test XSS prevention (output escaping)
    - _Requirements: Testing_

- [ ] 14. Checkpoint - Verify security and logging
  - Ensure rate limiting works correctly
  - Ensure all operations are logged
  - Ensure sensitive data is masked in logs
  - Ensure security events are audited
  - Ask the user if questions arise

- [ ] 15. Phase 8: Comprehensive Testing and Property Validation
  - [ ]* 15.1 Install Pest PHP and property testing plugin
    - Run: composer require pestphp/pest --dev
    - Run: composer require pestphp/pest-plugin-laravel --dev
    - Run: composer require pestphp/pest-plugin-faker --dev
    - Run: ./vendor/bin/pest --init
    - Configure pest.php with test suites: Unit, Feature, Property, Integration
    - _Requirements: Testing infrastructure_
  
  - [ ]* 15.2 Write property tests for virtual accounts
    - **Property 1: Virtual Account User Uniqueness**
    - **Validates: Requirements 1.4, 1.12**
    - Create multiple virtual accounts for same user
    - Assert only one active account exists per user
    - Run 100 iterations
  
  - [ ]* 15.3 Write property tests for virtual account fields
    - **Property 2: Virtual Account Required Fields**
    - **Validates: Requirements 1.3**
    - Create virtual account with random data
    - Assert account_number, bank_name, account_name, customer_id are not null
    - Run 100 iterations
  
  - [ ]* 15.4 Write property tests for virtual account defaults
    - **Property 3: Virtual Account Default Values**
    - **Validates: Requirements 1.7, 1.8**
    - Create virtual account without explicit account_type or bank_code
    - Assert account_type='static' and bank_code='100033'
    - Run 100 iterations
  
  - [ ]* 15.5 Write property tests for reference uniqueness
    - **Property 4: Virtual Account Reference Uniqueness**
    - **Validates: Requirements 1.9**
    - Generate multiple external_reference values
    - Assert all references are unique
    - Run 100 iterations
  
  - [ ]* 15.6 Write property tests for transfer fee calculation
    - **Property 5: Transfer Fee Calculation**
    - **Validates: Requirements 2.2, 2.5**
    - Generate random initial balance and transfer amount
    - Simulate transfer (deduct amount + ₦50)
    - Assert wallet balance = initial - amount - 50
    - Run 100 iterations
  
  - [ ]* 15.7 Write property tests for transfer amount validation
    - **Property 6: Transfer Amount Validation**
    - **Validates: Requirements 2.3, 2.4**
    - Generate amounts below ₦100 and above ₦5,000,000
    - Validate using Laravel validator
    - Assert validation fails for invalid amounts
    - Run 100 iterations
  
  - [ ]* 15.8 Write property tests for transfer reference format
    - **Property 7: Transfer Reference Format**
    - **Validates: Requirements 2.6**
    - Generate multiple transfer references
    - Assert format matches "PW-{timestamp}-{user_id}"
    - Assert all references are unique
    - Run 100 iterations
  
  - [ ]* 15.9 Write property tests for insufficient balance rejection
    - **Property 8: Transfer Insufficient Balance Rejection**
    - **Validates: Requirements 2.10**
    - Create user with random balance
    - Attempt transfer with amount > balance
    - Assert transfer rejected and wallet unchanged
    - Run 100 iterations
  
  - [ ]* 15.10 Write property tests for transfer rollback
    - **Property 9: Transfer Rollback on Failure**
    - **Validates: Requirements 2.12**
    - Record initial wallet balance
    - Simulate failed transfer (mock API error)
    - Assert wallet balance restored to initial value
    - Run 100 iterations
  
  - [ ]* 15.11 Write property tests for webhook signature verification
    - **Property 12: Webhook Signature Verification**
    - **Validates: Requirements 4.1, 4.2, 10.1, 10.2, 10.3, 10.4**
    - Generate random webhook payload
    - Compute correct HMAC signature
    - Test with correct signature (accept)
    - Test with incorrect signature (reject with 401)
    - Run 100 iterations
  
  - [ ]* 15.12 Write property tests for payment webhook wallet credit
    - **Property 14: Payment Webhook Wallet Credit**
    - **Validates: Requirements 4.5**
    - Record initial wallet balance
    - Process payment.received webhook with random amount
    - Assert wallet balance = initial + amount
    - Run 100 iterations
  
  - [ ]* 15.13 Write property tests for payment webhook transaction record
    - **Property 15: Payment Webhook Transaction Record**
    - **Validates: Requirements 4.6**
    - Process payment.received webhook
    - Assert transaction record created with type='deposit' and status='successful'
    - Run 100 iterations
  
  - [ ]* 15.14 Write property tests for transfer status updates
    - **Property 16: Transfer Success Status Update**
    - **Validates: Requirements 5.2**
    - Create pending transaction
    - Process transfer.success webhook
    - Assert transaction status updated to 'successful'
    - Run 100 iterations
  
  - [ ]* 15.15 Write property tests for transfer failure refund
    - **Property 17: Transfer Failure Refund**
    - **Validates: Requirements 5.5, 5.6**
    - Record initial wallet balance
    - Create pending transaction with amount and fee
    - Process transfer.failed webhook
    - Assert wallet credited with amount + fee
    - Assert transaction status = 'failed'
    - Run 100 iterations
  
  - [ ]* 15.16 Write property tests for KYC encryption
    - **Property 25: KYC ID Number Encryption**
    - **Validates: Requirements 7.5**
    - Create KYC record with random id_number
    - Query database directly for id_number_encrypted field
    - Assert encrypted value != plaintext id_number
    - Assert decrypted value = plaintext id_number
    - Run 100 iterations
  
  - [ ]* 15.17 Write property tests for KYC tier upgrade
    - **Property 27: KYC Tier Upgrade**
    - **Validates: Requirements 7.7**
    - Create KYC record with status='pending'
    - Update status to 'verified'
    - Assert tier='tier_3' and daily_limit=5000000.00
    - Run 100 iterations
  
  - [ ]* 15.18 Write property tests for webhook payload round-trip
    - **Property 30: Webhook Payload Parsing Round-Trip**
    - **Validates: Requirements 19.7**
    - Generate random webhook payload
    - Parse to array, serialize to JSON, parse again
    - Assert final result equals original payload
    - Run 100 iterations
  
  - [ ]* 15.19 Write property tests for webhook required fields
    - **Property 31: Webhook Required Fields Validation**
    - **Validates: Requirements 19.3**
    - Generate webhook payload missing required fields
    - Attempt to parse/validate
    - Assert validation fails with descriptive error
    - Run 100 iterations
  
  - [ ]* 15.20 Write property tests for API response parsing
    - **Property 35: API Response Parsing Round-Trip**
    - **Validates: Requirements 20.7**
    - Generate random API response
    - Parse from JSON, serialize back, parse again
    - Assert final result equals original response
    - Run 100 iterations
  
  - [ ]* 15.21 Write integration test for complete transfer flow
    - Create user with sufficient balance
    - Mock PointWave API responses (verify account, initiate transfer)
    - Initiate transfer via API endpoint
    - Assert wallet deducted correctly
    - Assert pending transaction created
    - Simulate transfer.success webhook
    - Assert transaction status updated to 'successful'
    - _Requirements: Integration testing_
  
  - [ ]* 15.22 Write integration test for complete payment flow
    - Create user with virtual account
    - Simulate payment.received webhook
    - Assert wallet credited correctly
    - Assert deposit transaction created
    - Assert notification dispatched
    - _Requirements: Integration testing_
  
  - [ ]* 15.23 Write integration test for failed transfer with refund
    - Create user with sufficient balance
    - Mock PointWave API responses
    - Initiate transfer via API endpoint
    - Simulate transfer.failed webhook
    - Assert wallet refunded correctly
    - Assert transaction status = 'failed'
    - _Requirements: Integration testing_
  
  - [ ]* 15.24 Run full test suite and verify coverage
    - Run: ./vendor/bin/pest --testsuite=Unit
    - Run: ./vendor/bin/pest --testsuite=Feature
    - Run: ./vendor/bin/pest --testsuite=Property
    - Run: ./vendor/bin/pest --testsuite=Integration
    - Run: ./vendor/bin/pest --coverage --min=80
    - Verify overall coverage >= 80%
    - Verify service layer coverage >= 90%
    - Verify controller layer coverage >= 85%
    - Verify model layer coverage >= 75%
    - _Requirements: Testing_

- [ ] 16. Checkpoint - Verify all tests pass
  - Ensure all unit tests pass
  - Ensure all feature tests pass
  - Ensure all property tests pass (100 iterations each)
  - Ensure all integration tests pass
  - Ensure coverage targets met
  - Ask the user if questions arise

- [ ] 17. Phase 9: Routes, Configuration, and Integration
  - [x] 17.1 Register all API routes
    - Add routes to routes/api.php
    - Group user routes under auth:sanctum middleware
    - Group admin routes under auth:sanctum and admin middleware
    - Add webhook route without CSRF protection
    - Apply appropriate rate limiting to each route group
    - _Requirements: All endpoint requirements_
  
  - [ ] 17.2 Create PointWave configuration file
    - Create config/pointwave.php
    - Define all configuration options: enabled, auto_create_virtual_account, api settings, transfer limits, KYC limits, cache TTLs, rate limits, webhook allowed IPs
    - Use env() for sensitive values
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_
  
  - [ ] 17.3 Update User model with PointWave relationships
    - Add hasOne relationship to PointWaveVirtualAccount
    - Add hasMany relationship to PointWaveTransaction
    - Add hasOne relationship to PointWaveKYC
    - _Requirements: 1.4, 6.9, 7.1_
  
  - [ ] 17.4 Add automatic virtual account creation on user registration
    - Create observer or event listener for User created event
    - Check if pointwave_auto_create_virtual_account setting is enabled
    - Call PointWaveService->createVirtualAccount()
    - Store result in pointwave_virtual_accounts table
    - Log success or failure
    - _Requirements: 1.5, 1.6_
  
  - [ ] 17.5 Create database indexes for performance
    - Verify all indexes from design document are created
    - Add composite index on pointwave_transactions (user_id, type, status)
    - Add composite index on pointwave_webhooks (processed, created_at)
    - _Requirements: Performance optimization_
  
  - [ ] 17.6 Update .env.example with PointWave variables
    - Add POINTWAVE_BASE_URL, POINTWAVE_SECRET_KEY, POINTWAVE_API_KEY, POINTWAVE_BUSINESS_ID
    - Add POINTWAVE_ENABLED, POINTWAVE_AUTO_CREATE_VIRTUAL_ACCOUNT
    - Add POINTWAVE_TRANSFER_FEE, POINTWAVE_MIN_TRANSFER, POINTWAVE_MAX_TRANSFER
    - Add comments explaining each variable
    - _Requirements: Configuration_
  
  - [ ] 17.7 Register middleware in HTTP Kernel
    - Register VerifyPointWaveWebhook middleware
    - Register RateLimitPointWave middleware
    - _Requirements: 4.1, 10.7_

- [ ] 18. Checkpoint - Verify integration
  - Ensure all routes are registered correctly
  - Ensure configuration file loads correctly
  - Ensure User model relationships work
  - Ensure automatic virtual account creation works
  - Ask the user if questions arise

- [ ] 19. Phase 10: Documentation and Deployment Preparation
  - [ ] 19.1 Create API documentation
    - Document all user-facing endpoints with request/response examples
    - Document all admin endpoints with request/response examples
    - Document webhook payload formats
    - Document error codes and messages
    - _Requirements: Documentation_
  
  - [ ] 19.2 Create admin user guide
    - Document how to access admin dashboard
    - Document how to manage settings
    - Document how to run reconciliation
    - Document how to handle failed transactions
    - Document how to perform manual refunds
    - _Requirements: Documentation_
  
  - [ ] 19.3 Create developer guide
    - Document architecture and component overview
    - Document database schema
    - Document service layer methods
    - Document testing approach
    - Document how to extend functionality
    - _Requirements: Documentation_
  
  - [ ] 19.4 Create deployment guide
    - Document environment variable setup
    - Document migration steps
    - Document seeding steps
    - Document queue worker setup
    - Document log monitoring setup
    - Document rollback procedures
    - _Requirements: Documentation_
  
  - [ ] 19.5 Create troubleshooting guide
    - Document common issues and solutions
    - Document how to debug webhook issues
    - Document how to debug transfer failures
    - Document how to check logs
    - Document how to contact PointWave support
    - _Requirements: Documentation_
  
  - [ ] 19.6 Prepare deployment checklist
    - Create checklist for pre-deployment tasks
    - Create checklist for deployment tasks
    - Create checklist for post-deployment verification
    - Include rollback plan
    - _Requirements: Deployment_
  
  - [ ] 19.7 Configure production environment variables
    - Set all POINTWAVE_* variables in production .env
    - Verify API credentials are correct
    - Verify webhook secret matches PointWave dashboard
    - Enable HTTPS enforcement
    - _Requirements: Deployment_
  
  - [ ] 19.8 Set up queue workers for production
    - Configure supervisor or systemd for queue workers
    - Set queue worker count based on expected load
    - Configure queue worker restart on failure
    - Test queue worker processes webhooks correctly
    - _Requirements: 4.3, 4.4_
  
  - [ ] 19.9 Set up log monitoring and alerts
    - Configure log aggregation (e.g., Papertrail, Loggly)
    - Set up alerts for ERROR and CRITICAL log levels
    - Set up alerts for failed webhook processing
    - Set up alerts for API authentication failures
    - Set up alerts for rate limit exceeded
    - _Requirements: 11.1, 11.2_
  
  - [ ] 19.10 Prepare backup and rollback procedures
    - Document how to backup pointwave_* tables
    - Document how to disable PointWave provider via settings
    - Document how to revert code deployment
    - Document how to restore from backup
    - Test rollback procedure on staging
    - _Requirements: Deployment_

- [ ] 20. Checkpoint - Verify documentation and deployment readiness
  - Ensure all documentation is complete and accurate
  - Ensure deployment checklist is comprehensive
  - Ensure production environment is configured
  - Ensure monitoring and alerts are set up
  - Ask the user if questions arise

- [ ] 21. Phase 11: Staging Deployment and Testing
  - [ ] 21.1 Deploy to staging environment
    - Pull latest code to staging server
    - Run composer install
    - Run php artisan migrate (verify only new pointwave_* tables created)
    - Run php artisan db:seed --class=PointWaveSettingsSeeder
    - Clear caches: php artisan cache:clear, php artisan config:clear
    - Restart queue workers
    - _Requirements: Deployment_
  
  - [ ] 21.2 Run full test suite on staging
    - Run: ./vendor/bin/pest
    - Verify all tests pass
    - Run: ./vendor/bin/pest --coverage
    - Verify coverage targets met
    - _Requirements: Testing_
  
  - [ ] 21.3 Perform manual testing on staging
    - Test virtual account creation via UI
    - Test bank account verification
    - Test transfer initiation with various amounts
    - Test transfer with insufficient balance (should fail)
    - Test transfer with amount below minimum (should fail)
    - Test transfer with amount above maximum (should fail)
    - Test KYC submission
    - Test admin dashboard access
    - Test admin settings management
    - _Requirements: Manual testing_
  
  - [ ] 21.4 Test with PointWave sandbox environment
    - Configure staging to use PointWave sandbox credentials
    - Create virtual account and verify in PointWave dashboard
    - Initiate test transfer and verify in PointWave dashboard
    - Trigger test webhook from PointWave dashboard
    - Verify webhook processing works correctly
    - _Requirements: Integration testing_
  
  - [ ] 21.5 Perform security testing on staging
    - Test webhook with invalid signature (should reject)
    - Test rate limiting (should block after limit)
    - Test SQL injection attempts (should be prevented)
    - Test XSS attempts (should be escaped)
    - Test unauthorized access to admin endpoints (should return 403)
    - _Requirements: Security testing_
  
  - [ ] 21.6 Perform load testing on staging
    - Simulate 100 concurrent users creating virtual accounts
    - Simulate 100 concurrent transfer requests
    - Simulate 100 concurrent webhook deliveries
    - Measure response times and identify bottlenecks
    - Optimize slow queries if needed
    - _Requirements: Performance testing_
  
  - [ ] 21.7 Fix any issues found during staging testing
    - Document all issues found
    - Prioritize issues by severity
    - Fix critical and high-priority issues
    - Re-test after fixes
    - Update documentation if needed
    - _Requirements: Bug fixing_

- [ ] 22. Checkpoint - Verify staging deployment success
  - Ensure all tests pass on staging
  - Ensure manual testing completed successfully
  - Ensure PointWave sandbox integration works
  - Ensure no critical issues remain
  - Ask the user if questions arise

- [ ] 23. Phase 12: Production Launch
  - [ ] 23.1 Final pre-launch checks
    - Run final security audit
    - Run final performance testing
    - Backup production database
    - Verify rollback plan is ready
    - Notify support team of launch
    - Prepare user announcement
    - _Requirements: Launch preparation_
  
  - [ ] 23.2 Deploy to production
    - Schedule maintenance window (if needed)
    - Pull latest code to production server
    - Run composer install --no-dev --optimize-autoloader
    - Run php artisan migrate (verify only new pointwave_* tables created)
    - Run php artisan db:seed --class=PointWaveSettingsSeeder
    - Run php artisan config:cache
    - Run php artisan route:cache
    - Run php artisan view:cache
    - Restart queue workers
    - Restart PHP-FPM
    - _Requirements: Deployment_
  
  - [ ] 23.3 Run smoke tests on production
    - Test homepage loads correctly
    - Test user login works
    - Test existing features still work (no breaking changes)
    - Test PointWave virtual account creation
    - Test PointWave transfer initiation
    - Test webhook endpoint responds correctly
    - _Requirements: Smoke testing_
  
  - [ ] 23.4 Monitor production for first 24 hours
    - Monitor error logs continuously
    - Monitor webhook processing queue
    - Monitor API success rate
    - Monitor response times
    - Monitor user feedback
    - Be ready to rollback if critical issues arise
    - _Requirements: Monitoring_
  
  - [ ] 23.5 Address any production issues immediately
    - Triage issues by severity
    - Fix critical issues immediately
    - Document all issues and resolutions
    - Communicate with users if needed
    - _Requirements: Issue resolution_
  
  - [ ] 23.6 Post-launch review
    - Collect user feedback
    - Review monitoring metrics
    - Review error logs
    - Document lessons learned
    - Plan future improvements
    - Celebrate successful launch!
    - _Requirements: Post-launch_

- [ ] 24. Final Checkpoint - Production launch complete
  - Ensure production deployment successful
  - Ensure no critical issues in first 24 hours
  - Ensure monitoring and alerts working
  - Ensure user feedback is positive
  - Feature implementation complete!

## Notes

- Tasks marked with `*` are optional testing tasks and can be skipped for faster MVP delivery
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation and provide opportunities for user feedback
- Property tests validate universal correctness properties with 100 iterations each
- Unit tests validate specific examples and edge cases
- Integration tests validate end-to-end flows
- All tasks build incrementally - no orphaned or hanging code
- NO BREAKING CHANGES: All existing functionality must remain intact
- Use ONLY incremental migrations with "pointwave_" prefix
- Work alongside existing payment providers, not replace them

## Testing Summary

**Property-Based Tests** (39 properties from design document):
- Virtual account properties (Properties 1-4)
- Transfer properties (Properties 5-10)
- Account verification properties (Property 11)
- Webhook security properties (Properties 12-13)
- Payment webhook properties (Properties 14-15)
- Transfer webhook properties (Properties 16-17)
- Transaction properties (Properties 18-22)
- KYC properties (Properties 23-29)
- Webhook parsing properties (Properties 30-34)
- API response properties (Properties 35-39)

**Unit Tests**:
- Service layer: API communication, retry logic, error handling, caching
- Controller layer: Input validation, business logic, response formatting
- Model layer: Relationships, scopes, accessors, mutators
- Middleware: Signature verification, rate limiting
- Jobs: Webhook processing, notifications

**Integration Tests**:
- Complete transfer flow (initiation → webhook → status update)
- Complete payment flow (webhook → wallet credit → notification)
- Failed transfer with refund flow
- KYC submission and verification flow

**Coverage Targets**:
- Overall: 80% minimum
- Service layer: 90% minimum
- Controller layer: 85% minimum
- Model layer: 75% minimum

## Success Criteria

- [ ] All migrations run successfully without errors
- [ ] All models and relationships work correctly
- [ ] Virtual account creation works end-to-end
- [ ] Bank transfers work with proper validation and wallet management
- [ ] Webhooks process correctly with signature verification
- [ ] KYC submission and tier limits work correctly
- [ ] Admin dashboard and controls work correctly
- [ ] All tests pass (unit, feature, property, integration)
- [ ] Test coverage meets targets (80% overall)
- [ ] No breaking changes to existing functionality
- [ ] Security measures in place (rate limiting, logging, encryption)
- [ ] Documentation complete
- [ ] Production deployment successful
- [ ] No critical issues in first 24 hours

## Risk Mitigation

**Technical Risks**:
- PointWave API downtime → Retry logic, queue failed requests, notify users
- Database migration issues → Test on staging, use incremental migrations only, have rollback plan
- Webhook processing failures → Use queue system, implement retry logic, log all failures

**Business Risks**:
- User confusion → Clear UI labels, user documentation, support training
- Transaction reconciliation issues → Daily automated reconciliation, manual review process, audit trail

**Security Risks**:
- Webhook spoofing → HMAC signature verification, IP whitelisting, rate limiting
- Data breach → Encryption at rest, HTTPS only, access controls, audit logging

## Rollback Plan

If critical issues discovered post-launch:
1. Disable PointWave provider via settings (pointwave_enabled=false)
2. Stop processing new transactions
3. Export all PointWave transactions for backup
4. Revert code to previous version if needed
5. Keep database tables intact (data preservation)
6. Investigate root cause and fix in development
7. Re-test thoroughly before re-launch
