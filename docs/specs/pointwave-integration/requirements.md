# Requirements Document: PointWave Integration

## Introduction

This document specifies the requirements for integrating the PointWave payment gateway (via PalmPay) into the VendLike Laravel VTU/Payment platform. The integration will enable users to create virtual accounts for receiving payments, send bank transfers to Nigerian bank accounts, and receive real-time payment notifications via webhooks. The system will support KYC verification, transaction management, and administrative controls for the PointWave provider.

## Glossary

- **PointWave_System**: The PointWave payment gateway service that provides virtual accounts, bank transfers, and payment processing capabilities
- **VendLike_Platform**: The existing Laravel 8 VTU/Payment platform that will integrate with PointWave
- **Virtual_Account**: A PalmPay bank account number assigned to a user for receiving payments
- **User**: A registered customer on the VendLike platform who can create virtual accounts and initiate transfers
- **Admin**: A platform administrator who manages PointWave settings and monitors transactions
- **Webhook**: An HTTP callback that PointWave sends to notify VendLike of payment events
- **KYC**: Know Your Customer verification using BVN (Bank Verification Number) or NIN (National Identification Number)
- **Transfer**: A bank transfer from the PointWave wallet to a Nigerian bank account
- **Deposit**: An incoming payment to a user's virtual account
- **Transaction_Record**: A database entry tracking a PointWave transaction (deposit, transfer, or withdrawal)
- **Wallet**: A user's balance on the VendLike platform
- **Provider**: A payment service integrated into VendLike (e.g., PointWave, Xixapay, Paystack, Monnify)
- **HMAC_Signature**: A cryptographic signature used to verify webhook authenticity
- **Idempotency_Key**: A unique identifier to prevent duplicate API requests
- **Bank_Code**: A unique identifier for Nigerian banks (e.g., "058" for GTBank)
- **Account_Verification**: The process of confirming a bank account's validity and owner name
- **Tier**: A KYC level that determines transaction limits (Tier 1: ₦300k/day, Tier 3: ₦5M/day)

## Requirements

### Requirement 1: Virtual Account Creation

**User Story:** As a user, I want to create a PalmPay virtual account, so that I can receive payments directly into my VendLike wallet.

#### Acceptance Criteria

1. WHEN a user registers on VendLike_Platform, THE System SHALL create a Virtual_Account for the user automatically
2. WHEN a user requests virtual account creation manually, THE System SHALL create a Virtual_Account within 5 seconds
3. THE System SHALL store the virtual account details (account_number, bank_name, account_name, customer_id) in the database
4. THE System SHALL link each Virtual_Account to exactly one User
5. WHEN creating a Virtual_Account, THE System SHALL send the user's first_name, last_name, email, and phone_number to PointWave_System
6. WHERE a user provides KYC information (BVN or NIN), THE System SHALL include id_type and id_number in the virtual account creation request
7. THE System SHALL use "static" as the default account_type for all virtual accounts
8. THE System SHALL use bank_code "100033" (PalmPay) as the default bank for virtual accounts
9. THE System SHALL generate a unique external_reference for each virtual account creation request
10. IF virtual account creation fails, THEN THE System SHALL log the error and return a descriptive error message to the user
11. THE System SHALL display the Virtual_Account details (account_number, bank_name, account_name) in the user dashboard
12. THE System SHALL prevent duplicate virtual account creation for the same user

### Requirement 2: Bank Transfer Initiation

**User Story:** As a user, I want to send money to any Nigerian bank account via PointWave, so that I can withdraw funds or pay others.

#### Acceptance Criteria

1. WHEN a user initiates a transfer, THE System SHALL verify the recipient's bank account before processing
2. THE System SHALL charge a flat fee of ₦50 for each transfer
3. THE System SHALL enforce a minimum transfer amount of ₦100
4. THE System SHALL enforce a maximum transfer amount of ₦5,000,000
5. WHEN initiating a transfer, THE System SHALL deduct the transfer amount plus ₦50 fee from the user's Wallet
6. THE System SHALL generate a unique reference for each transfer using the format "PW-{timestamp}-{user_id}"
7. THE System SHALL send the amount, account_number, bank_code, account_name, narration, and reference to PointWave_System
8. THE System SHALL include an Idempotency_Key header to prevent duplicate transfers
9. THE System SHALL store a Transaction_Record with status "pending" immediately after initiating the transfer
10. IF the user's Wallet balance is insufficient, THEN THE System SHALL reject the transfer and return an error message
11. IF bank account verification fails, THEN THE System SHALL reject the transfer and return the verification error
12. IF the transfer initiation fails, THEN THE System SHALL refund the deducted amount to the user's Wallet
13. THE System SHALL log all transfer attempts including success and failure cases
14. THE System SHALL display PointWave as a transfer provider option in the withdrawal interface

### Requirement 3: Bank Account Verification

**User Story:** As a user, I want the system to verify bank account details before transfers, so that I can ensure my money goes to the correct recipient.

#### Acceptance Criteria

1. WHEN a user enters a bank account number and selects a bank, THE System SHALL verify the account with PointWave_System
2. THE System SHALL retrieve and display the account_name from the verification response
3. THE System SHALL complete account verification within 3 seconds
4. THE System SHALL include an Idempotency_Key header in verification requests
5. IF the account number is invalid, THEN THE System SHALL return an error message "Invalid account number"
6. IF the bank code is invalid, THEN THE System SHALL return an error message "Invalid bank selected"
7. THE System SHALL cache verified account details for 24 hours to reduce API calls
8. THE System SHALL provide a list of supported Nigerian banks from PointWave_System

### Requirement 4: Webhook Payment Notifications

**User Story:** As a user, I want my wallet to be credited automatically when I fund my virtual account, so that I can use the funds immediately.

#### Acceptance Criteria

1. WHEN PointWave_System sends a "payment.received" webhook, THE System SHALL verify the HMAC_Signature using SHA256
2. IF the HMAC_Signature is invalid, THEN THE System SHALL reject the webhook and log a security warning
3. WHEN a valid payment webhook is received, THE System SHALL extract the amount, reference, customer_id, and transaction_id
4. THE System SHALL identify the User by matching the customer_id to the stored Virtual_Account
5. THE System SHALL credit the User's Wallet with the payment amount
6. THE System SHALL create a Transaction_Record with type "deposit" and status "successful"
7. THE System SHALL send a notification to the User confirming the deposit
8. THE System SHALL respond to PointWave_System with HTTP 200 status within 5 seconds
9. THE System SHALL prevent duplicate processing of the same webhook using the transaction_id
10. THE System SHALL log all webhook events including payload and processing result
11. IF webhook processing fails, THEN THE System SHALL log the error and respond with HTTP 500 status

### Requirement 5: Webhook Transfer Status Updates

**User Story:** As a user, I want to be notified when my transfer succeeds or fails, so that I know the status of my withdrawal.

#### Acceptance Criteria

1. WHEN PointWave_System sends a "transfer.success" webhook, THE System SHALL verify the HMAC_Signature
2. WHEN a valid transfer success webhook is received, THE System SHALL update the Transaction_Record status to "successful"
3. THE System SHALL send a success notification to the User with the transfer details
4. WHEN PointWave_System sends a "transfer.failed" webhook, THE System SHALL verify the HMAC_Signature
5. WHEN a valid transfer failure webhook is received, THE System SHALL update the Transaction_Record status to "failed"
6. THE System SHALL refund the transfer amount plus ₦50 fee to the User's Wallet
7. THE System SHALL send a failure notification to the User with the reason for failure
8. THE System SHALL log the failure reason from the webhook payload
9. THE System SHALL prevent duplicate processing of transfer status webhooks using the reference

### Requirement 6: Transaction Management

**User Story:** As a user, I want to view my PointWave transaction history, so that I can track my deposits and transfers.

#### Acceptance Criteria

1. THE System SHALL store all PointWave transactions in the database with fields: id, user_id, type, amount, fee, status, reference, pointwave_transaction_id, pointwave_customer_id, metadata, created_at, updated_at
2. THE System SHALL support transaction types: "deposit", "transfer", "withdrawal"
3. THE System SHALL support transaction statuses: "pending", "successful", "failed"
4. WHEN a user requests transaction history, THE System SHALL return transactions filtered by user_id
5. THE System SHALL support filtering transactions by type, status, and date range
6. THE System SHALL support pagination with a default limit of 20 transactions per page
7. THE System SHALL display transaction details including amount, fee, status, reference, and timestamp
8. THE System SHALL provide an API endpoint to retrieve a single transaction by reference
9. THE System SHALL link each Transaction_Record to the User who initiated it
10. THE System SHALL store the PointWave transaction_id for reconciliation purposes

### Requirement 7: KYC Integration

**User Story:** As a user, I want to provide my BVN or NIN during account creation, so that I can access higher transaction limits.

#### Acceptance Criteria

1. WHERE a user provides KYC information, THE System SHALL accept id_type values "bvn" or "nin"
2. WHERE a user provides BVN, THE System SHALL validate that the id_number is exactly 11 digits
3. WHERE a user provides NIN, THE System SHALL validate that the id_number is exactly 11 digits
4. THE System SHALL store KYC information (id_type, id_number, kyc_status) securely in the database
5. THE System SHALL encrypt id_number before storing in the database
6. THE System SHALL support KYC statuses: "not_submitted", "pending", "verified", "rejected"
7. WHEN KYC is verified, THE System SHALL update the user's tier to "tier_3" with a daily limit of ₦5,000,000
8. WHEN KYC is not provided, THE System SHALL set the user's tier to "tier_1" with a daily limit of ₦300,000
9. THE System SHALL enforce tier-based transaction limits on transfers
10. IF a transfer exceeds the user's tier limit, THEN THE System SHALL reject the transfer with message "Transfer exceeds your daily limit"

### Requirement 8: Admin Transaction Management

**User Story:** As an admin, I want to view all PointWave transactions, so that I can monitor platform activity and resolve issues.

#### Acceptance Criteria

1. THE System SHALL provide an admin dashboard page for PointWave transactions
2. WHEN an Admin accesses the PointWave dashboard, THE System SHALL display all transactions across all users
3. THE System SHALL support filtering by user, type, status, and date range
4. THE System SHALL display transaction details including user_name, amount, fee, status, reference, and timestamp
5. THE System SHALL provide a search function to find transactions by reference or user email
6. THE System SHALL display summary statistics: total deposits, total transfers, total fees collected, success rate
7. THE System SHALL allow Admin to view the full webhook payload for each transaction
8. THE System SHALL allow Admin to manually refund a failed transaction
9. WHEN an Admin initiates a manual refund, THE System SHALL credit the user's Wallet and update the transaction status to "refunded"
10. THE System SHALL log all admin actions on transactions including admin_id and action_type

### Requirement 9: Admin Provider Configuration

**User Story:** As an admin, I want to configure PointWave settings, so that I can control fees, limits, and enable/disable the provider.

#### Acceptance Criteria

1. THE System SHALL provide an admin settings page for PointWave configuration
2. THE System SHALL allow Admin to enable or disable PointWave as a transfer provider
3. WHEN PointWave is disabled, THE System SHALL hide PointWave from the user's transfer provider options
4. THE System SHALL allow Admin to configure the transfer fee (default: ₦50)
5. THE System SHALL allow Admin to configure minimum transfer amount (default: ₦100)
6. THE System SHALL allow Admin to configure maximum transfer amount (default: ₦5,000,000)
7. THE System SHALL allow Admin to view the current PointWave wallet balance
8. THE System SHALL allow Admin to refresh the wallet balance on demand
9. THE System SHALL store all configuration changes in the database with timestamp and admin_id
10. THE System SHALL validate that minimum amount is less than maximum amount
11. THE System SHALL validate that transfer fee is a positive number

### Requirement 10: Webhook Security

**User Story:** As a platform administrator, I want webhook requests to be authenticated, so that only legitimate PointWave notifications are processed.

#### Acceptance Criteria

1. THE System SHALL verify the HMAC_Signature header on all incoming webhooks
2. THE System SHALL compute the expected signature using HMAC SHA256 with the PointWave secret key
3. THE System SHALL compare the computed signature with the received HMAC_Signature header
4. IF signatures do not match, THEN THE System SHALL reject the webhook with HTTP 401 status
5. THE System SHALL log all rejected webhooks with the received payload and signature
6. THE System SHALL use constant-time comparison to prevent timing attacks
7. THE System SHALL rate-limit webhook endpoints to 100 requests per minute per IP address
8. IF rate limit is exceeded, THEN THE System SHALL respond with HTTP 429 status
9. THE System SHALL validate that the webhook payload contains required fields before processing
10. THE System SHALL sanitize all webhook data before storing in the database

### Requirement 11: Error Handling and Logging

**User Story:** As a developer, I want comprehensive error logging, so that I can debug issues and monitor system health.

#### Acceptance Criteria

1. THE System SHALL log all API requests to PointWave_System including endpoint, payload, and response
2. THE System SHALL log all API errors with error code, message, and full response body
3. THE System SHALL log all webhook events with event type, payload, and processing result
4. THE System SHALL use Laravel's logging system with channel "pointwave"
5. THE System SHALL log at INFO level for successful operations
6. THE System SHALL log at ERROR level for failed operations
7. THE System SHALL log at WARNING level for security events (invalid signatures, rate limits)
8. THE System SHALL include request_id in all log entries for tracing
9. THE System SHALL mask sensitive data (API keys, account numbers) in logs
10. THE System SHALL retain logs for 90 days

### Requirement 12: API Integration Reliability

**User Story:** As a platform operator, I want the PointWave integration to handle API failures gracefully, so that users experience minimal disruption.

#### Acceptance Criteria

1. THE System SHALL include proper authentication headers (Authorization, X-Business-ID, X-API-Key) in all API requests
2. THE System SHALL set a timeout of 30 seconds for all API requests
3. IF an API request times out, THEN THE System SHALL retry up to 2 times with exponential backoff
4. IF all retries fail, THEN THE System SHALL log the error and return a user-friendly error message
5. THE System SHALL handle HTTP 401 errors by logging "Invalid credentials" and alerting administrators
6. THE System SHALL handle HTTP 429 errors by implementing exponential backoff
7. THE System SHALL handle HTTP 500 errors by retrying the request once after 5 seconds
8. THE System SHALL validate API responses contain expected fields before processing
9. THE System SHALL use Idempotency_Key headers for all state-changing requests (POST, PUT)
10. THE System SHALL store API response metadata for debugging purposes

### Requirement 13: Database Schema

**User Story:** As a developer, I want a well-structured database schema, so that PointWave data is organized and queryable.

#### Acceptance Criteria

1. THE System SHALL create a "pointwave_virtual_accounts" table with columns: id, user_id, customer_id, account_number, account_name, bank_name, bank_code, status, external_reference, created_at, updated_at
2. THE System SHALL create a "pointwave_transactions" table with columns: id, user_id, type, amount, fee, status, reference, pointwave_transaction_id, pointwave_customer_id, account_number, bank_code, account_name, narration, metadata, created_at, updated_at
3. THE System SHALL create a "pointwave_kyc" table with columns: id, user_id, id_type, id_number_encrypted, kyc_status, tier, daily_limit, verified_at, created_at, updated_at
4. THE System SHALL create a "pointwave_webhooks" table with columns: id, event_type, payload, signature, processed, processed_at, error_message, created_at
5. THE System SHALL create a "pointwave_settings" table with columns: id, key, value, updated_by, updated_at
6. THE System SHALL add foreign key constraints linking user_id to the users table
7. THE System SHALL add unique constraints on pointwave_virtual_accounts.user_id
8. THE System SHALL add unique constraints on pointwave_transactions.reference
9. THE System SHALL add indexes on frequently queried columns: user_id, status, type, created_at
10. THE System SHALL add indexes on pointwave_webhooks.processed for efficient webhook processing

### Requirement 14: User Interface Integration

**User Story:** As a user, I want PointWave features integrated into the existing VendLike interface, so that I can access them easily.

#### Acceptance Criteria

1. THE System SHALL display the Virtual_Account details on the user's wallet funding page
2. THE System SHALL display PointWave as an option in the transfer provider dropdown
3. WHEN a user selects PointWave as the transfer provider, THE System SHALL display the bank selection and account verification form
4. THE System SHALL display the ₦50 transfer fee prominently before the user confirms the transfer
5. THE System SHALL display real-time account verification results (account name) after the user enters account details
6. THE System SHALL display PointWave transactions in the user's transaction history with a "PointWave" badge
7. THE System SHALL provide a "Copy" button next to the virtual account number for easy copying
8. THE System SHALL display the user's current tier and daily limit on the profile page
9. THE System SHALL provide a KYC submission form for users to upgrade their tier
10. THE System SHALL display transaction status with visual indicators (pending: yellow, successful: green, failed: red)

### Requirement 15: Notification System

**User Story:** As a user, I want to receive notifications for PointWave transactions, so that I stay informed about my account activity.

#### Acceptance Criteria

1. WHEN a deposit is successful, THE System SHALL send an email notification to the User
2. WHEN a deposit is successful, THE System SHALL send an in-app notification to the User
3. WHEN a transfer is successful, THE System SHALL send an email notification to the User with transfer details
4. WHEN a transfer fails, THE System SHALL send an email notification to the User with the failure reason
5. THE System SHALL include transaction amount, reference, and timestamp in all notifications
6. THE System SHALL include the new wallet balance in deposit notifications
7. THE System SHALL include recipient account details in transfer notifications
8. THE System SHALL allow users to configure notification preferences (email, SMS, in-app)
9. THE System SHALL queue notifications for asynchronous processing
10. THE System SHALL retry failed notification deliveries up to 3 times

### Requirement 16: Transaction Reconciliation

**User Story:** As an admin, I want to reconcile PointWave transactions with our records, so that I can ensure data accuracy.

#### Acceptance Criteria

1. THE System SHALL provide an admin page for transaction reconciliation
2. THE System SHALL fetch transactions from PointWave_System API for a specified date range
3. THE System SHALL compare fetched transactions with local Transaction_Records
4. THE System SHALL identify discrepancies: missing local records, missing remote records, amount mismatches
5. THE System SHALL display discrepancies in a table with details: reference, local_amount, remote_amount, status
6. THE System SHALL allow Admin to sync missing transactions from PointWave_System
7. WHEN syncing a missing transaction, THE System SHALL create a Transaction_Record and update the user's Wallet if applicable
8. THE System SHALL generate a reconciliation report in CSV format
9. THE System SHALL log all reconciliation activities with admin_id and timestamp
10. THE System SHALL schedule automatic reconciliation daily at 4:00 AM

### Requirement 17: Rate Limiting and Performance

**User Story:** As a platform operator, I want the system to respect PointWave's rate limits, so that we avoid service disruptions.

#### Acceptance Criteria

1. THE System SHALL enforce a rate limit of 60 API requests per minute to PointWave_System
2. IF the rate limit is approached, THEN THE System SHALL queue additional requests for processing after 1 minute
3. THE System SHALL cache the bank list for 24 hours to reduce API calls
4. THE System SHALL cache account verification results for 24 hours per account_number and bank_code combination
5. THE System SHALL use database transactions to ensure wallet updates are atomic
6. THE System SHALL process webhooks asynchronously using Laravel queues
7. THE System SHALL limit webhook processing to 10 concurrent jobs
8. THE System SHALL optimize database queries using eager loading for related models
9. THE System SHALL add database indexes on frequently queried columns
10. THE System SHALL monitor API response times and alert if average exceeds 5 seconds

### Requirement 18: Testing and Validation

**User Story:** As a developer, I want comprehensive tests, so that I can ensure the integration works correctly.

#### Acceptance Criteria

1. THE System SHALL provide a test mode that uses PointWave sandbox credentials
2. THE System SHALL provide test endpoints for creating virtual accounts without affecting production
3. THE System SHALL provide test endpoints for simulating webhook events
4. THE System SHALL validate all user inputs before sending to PointWave_System
5. THE System SHALL validate that phone numbers are in E.164 format (+234XXXXXXXXXX)
6. THE System SHALL validate that email addresses are in valid format
7. THE System SHALL validate that amounts are positive numbers with maximum 2 decimal places
8. THE System SHALL validate that bank codes exist in the supported banks list
9. THE System SHALL validate that account numbers are 10 digits
10. THE System SHALL provide unit tests for all service methods with minimum 80% code coverage

## Special Requirements: Parser and Serializer

### Requirement 19: Webhook Payload Parsing

**User Story:** As a developer, I want reliable webhook payload parsing, so that payment notifications are processed correctly.

#### Acceptance Criteria

1. THE Webhook_Parser SHALL parse incoming JSON webhook payloads into WebhookEvent objects
2. WHEN an invalid JSON payload is received, THE Webhook_Parser SHALL return a descriptive error
3. THE Webhook_Parser SHALL validate that required fields (event_type, data, timestamp) are present
4. THE Webhook_Parser SHALL validate that amount fields are numeric
5. THE Webhook_Parser SHALL validate that timestamp fields are in ISO 8601 format
6. THE Pretty_Printer SHALL format WebhookEvent objects back into valid JSON
7. FOR ALL valid WebhookEvent objects, parsing then printing then parsing SHALL produce an equivalent object (round-trip property)
8. THE Webhook_Parser SHALL handle nested data structures in the webhook payload
9. THE Webhook_Parser SHALL sanitize string fields to prevent injection attacks
10. THE Webhook_Parser SHALL support all PointWave event types: payment.received, transfer.success, transfer.failed

### Requirement 20: API Response Parsing

**User Story:** As a developer, I want reliable API response parsing, so that PointWave data is correctly interpreted.

#### Acceptance Criteria

1. THE API_Response_Parser SHALL parse PointWave API JSON responses into typed objects
2. WHEN an API returns an error response, THE API_Response_Parser SHALL extract the error code and message
3. THE API_Response_Parser SHALL validate that success responses contain expected data fields
4. THE API_Response_Parser SHALL handle pagination metadata (page, limit, total) in transaction list responses
5. THE API_Response_Parser SHALL parse nested customer and account objects
6. THE Pretty_Printer SHALL format API response objects back into valid JSON
7. FOR ALL valid API response objects, parsing then printing then parsing SHALL produce an equivalent object (round-trip property)
8. THE API_Response_Parser SHALL handle null and optional fields gracefully
9. THE API_Response_Parser SHALL convert amount strings to numeric types
10. THE API_Response_Parser SHALL parse ISO 8601 timestamps into DateTime objects

---

## Summary

This requirements document defines 20 functional requirements with 200 acceptance criteria for integrating PointWave payment gateway into the VendLike platform. The integration covers virtual account creation, bank transfers, webhook notifications, KYC verification, transaction management, admin controls, security, error handling, and testing. All requirements follow EARS patterns and comply with INCOSE quality rules for clarity, testability, and completeness.
