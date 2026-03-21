# 💳 SUDO AFRICA API REFERENCE

## Overview
**Provider**: Sudo Africa (https://sudo.africa)
**Purpose**: Virtual USD card issuance (Visa/Mastercard/Verve/AfriGo) for online/POS/ATM payments
**Documentation**: https://docs.sudo.africa
**Integration Status**: ✅ Active — Sandbox tested, production-ready

---

## Environments

| Environment | Sandbox | Production |
|-------------|---------|------------|
| Dashboard URL | https://app.sandbox.sudo.cards | https://app.sudo.africa |
| API URL | https://api.sandbox.sudo.cards | https://api.sudo.africa |
| Vault URL | https://vault.sandbox.sudo.cards | https://vault.sudo.cards |
| Vault ID | `we0dsa28svdl2xefo5` | (from production dashboard) |

> ⚠️ Vault URL is different from API URL. Card details CVV2) must go through vault endpoint.

### VGS Script (for secure card detail display in web):
```html
<script type="text/javascript" src="https://js.securepro.xyz/sudo-show/1.1/ACiWvWF9tYAez4M498DHs.min.js"></script>
```

---

## Switching Sandbox → Production

When going live, update these 5 things:

1. **`.env`** — change `SUDO_ENVIRONMENT=production`
2. **`.env`** — replace `SUDO_API_KEY` with production API key from https://app.sudo.africa → Developers
3. **`.env`** — replace `SUD_PROGRAM_ID` with production card program ID
4. **`.env`** — replace `SUDO_DEBIT_ACCOUNT_ID` with production settlement account ID
5. **`.env`** — replace `SUDO_FUNDING_SOURCE_ID` with production funding source ID

All IDs are environment-specific — sandbox IDs do NOT work in production. Get production IDs from the production dashboard.

```env
# Sandbox (current)
SUDO_ENVIRONMENT=sandbox
SUDO_API_KEY=your_sandbox_key
SUDO_VAULT_ID=we0dsa28svdl2xefo5
SUDO_CARD_PROGRAM_ID=69bda183144d27053ad6b6ad
SUDO_DEBIT_ACCOUNT_ID=69bd13df144d27053ad6911b
SUDO_FUNDING_SOURCE_ID=687a7c019d6f5695f5f4dafe

# Production (when going live — replace all values)
SUDO_ENVIRONMENT=production
SUDO_API_KEY=your_production_key
SUDO_VAULT_ID=we0dsa28svdl2xefo5   # vault ID stays same
SUDO_CARD_PROGRAM_ID=your_production_program_id
SUDO_DEBIT_ACCOUNT_ID=your_production_account_id
=your_production_funding_source_id
```

The code automatically picks the right API/Vault URLs based on `SUDO_ENVIRONMENT`.

---

## Authentication
- **Method**: Bearer Token
- **Header**: `Authorization: Bearer {API_KEY}`
- API Key created from Dashboard → Developers page
- Invalid/missing token → HTTP 401 Unauthorized

```
GET /cards HTTP/1.1
Host: api.sandbox.sudo.cards
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cC...
```

---

## Error Codes

| Code | Description |
|------|-------------|
| 200 | OK |
|0 | Bad Request — Missing/invalid parameter |
| 401 | Unauthorized — Invalid API key |
| 402 | Request Failed — Valid params but request failed |
| 403 | Forbidden — API key lacks permission |
| 404 | Not Found |
| 409 | Conflict |
| 429 | Too Many Requests — Use exponential backoff |
| 500-504 | Server Errors (Sudo's end) |

### Validation Error Format (400):
```json
{
  "statusCode": 400,
  "error": "Bad Request",
  "message": [
    {
      "target": { "field": "value" },
      "value": "bad_value",
y": "fieldName",
      "children": [],
      "constraints": {
        "isObject": "metadata must be an object"
      }
    }
  ]
}
```

---

## Metadata
- Store additional key-value pairs on any object
- Must be a **JSON object** — NOT a string
- In PHP: use `(object)[]` or `new \stdClass()` — NOT `'{}'`
- Sudo does NOT use metadata you store
- Do NOT store sensitive info (card details, passwords, PII) as metadata

```php
// ✅ Correct
'metadata' => (object)[]

// ❌ Wrong — sendata must be an object"
'metadata' => '{}'
```

---

## Pagination
- All list endpoints support `page` and `limit` params
- Default: `page=0`, `limit=25`
- Maximum: `limit=100`

```json
{
  "statusCode": 200,
  "message": "Cards fetched successfully.",
  "data": [{}, {}],
  "pagination": {
    "total": 1,
    "pages": 1,
    "page": "0",
    "limit": "25"
  }
}
```

---

## Complete API Endpoints Reference

| Method | Endpoint | Purpose |
|--------|----------|---------|
| **CUSTOMERS** | | |
| POST | `/customers` | Create customer/cardholder |
| GET | `/customers` | List all customers |
| GET | `/customers/{id}` | Get single customer |
| PUT | `/customers/{id}` | Update customer (full replace) |
| PUT | `/customers/{id}/documents/url` | Generate document upload URL |
| **CARDS** | | |
| POST | `/cards` | Create card (virtual or physical) |
| GET | `/cards` | List all cards |
| GET | `/cards/{id}` | Get single card |
| PUT | `/cards/{id}` | Update card (status, spending controls) |
| GET | `/cards/customer/{customerId}` | Get cards for a customer |
| GET | `/cards/{id}/token` | Generate card token (for Secure Proxy) |
| POST | `/cards/{id}/fund` | Fund card |
| POST | `/cards/{id}/withdraw` | Withdraw from card |
| PUT | `/cards/{id}/pin` | Change card PIN |
| GET | `/cards/{id}/transactions` | Get card transactions |
| GET | `/cards/transactions` | Get all transactions |
| GET | `/cards/transactions/{id}` | Get single transaction |
| PUT | `/cards/transactions/{id}` | Update transaction metadata |
/generate` | Generate sample card (sandbox only) |
| POST | `/cards/simulator/authorization` | Simulate transaction (sandbox only) |
| GET | `/cards/digitalize/{id}` | Get digitalization payload (Cloud Card) |
| **CARD PROGRAMS** | | |
| POST | `/card-programs` | Create card program |
| GET | `/card-programs` | List card programs |
| GET | `/card-programs/{id}` | Get single card program |
| PATCH | `/card-programs/{id}` | Update card program |
| GET | `/card-programs/{id}/cards` | Get cards in a program |
| **FUNDING SOURCES** | | |
| POST | `/fundingsources` | Create funding source |
| GET | `/fundingsources` | List funding sources |
| GET | `/fundingsources/{id}` | Get single funding source |
| PUT | `/fundingsources/{id}` | Update funding source |
| **ACCOUNTS** | | |
| GET | `/accounts` | List accounts |
| GET | `/accounts/{id}` | Get single account |
| POST | `/accounts/simulator/fund` | Fund account (sandbox only) |
| **VAULT (PCI-DSS)** | | |
| GET | `{VAULT_URL}/cAN, CVV2) |
| GET | `{VAULT_URL}/cards/{id}/secure-data/number` | Get card number via Secure Proxy |
| GET | `{VAULT_URL}/cards/{id}/secure-data/cvv2` | Get CVV2 via Secure Proxy |
| GET | `{VAULT_URL}/cards/{id}/secure-data/defaultPin` | Get default PIN via Secure Proxy |

---

## Customers (Cardholders)

### Create Customer
```
POST /customers
```

```json
{
  "type": "individual",
  "name": "John Doe",
  "status": "active",
  "phoneNumber": "+2348012345678",
  "emailAddress": "john@example.com",
  "individual": {
    "firstName": "John",
    "lastName": "Doe",
    "dob": "1990/01/15",
    "identity": {
      "type": "BVN",
      "number": "22490148602"
    }
  },
  "billingAddress": {
    "line1": "4 Barnawa Close",
    "city": "Lagos",
    "state": "Lagos",
    "country": "NG",
    "postalCode": "100001"
  }
}
```

#### Customer Fields:
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| type | string | Yes | `individual` or `company` |
| name | string | Yes | Full name |
| sing | Yes | `active` |
| phoneNumber | string | No | Use `phoneNumber` NOT `phone` |
| emailAddress | string | No | Use `emailAddress` NOT `email` |
| individual.firstName | string | Yes (individual) | |
| individual.lastName | string | Yes (individual) | |
| individual.dob | string | No | Format: `YYYY/MM/DD` |
| individual.identity.type | string | No | `BVN` or `NIN` |
| individual.identity.number | string | No | BVN or NIN number |
| billingAddress.line1 | string | Yes | |
|| Yes | |
| billingAddress.state | string | Yes | |
| billingAddress.country | string | Yes | `NG` for Nigeria |
| billingAddress.postalCode | string | Yes | |

> ⚠️ Field names are `phoneNumber` and `emailAddress` — NOT `phone`/`email`

### Update Customer
```
PUT /customers/{id}
```

> ⚠️ Use `PUT` (full replace) — NOT `PATCH`

Same payload as Create Customer. Always send all fields even if unchanged.

### Get Customer
```
GET /customers/{id}
```

### Generate Document Upload URL
```
PUT /customers/{id}/documents/url
```
```json
{
  "fileName": "id_front.jpg",
  "fileType": "image/jpeg"
}
```

---

## Cards

### Create Virtual USD Card (using Card Program)
```
POST /cards
```

```json
{
  "customerId": "670cf0ad25852ba485d7590d",
  "programId": "69bda183144d27053ad6b6ad",
  "status": "active",
  "metadata": {}
}
```

> When `programId` is provided, `type`, `currency`, `cardBrand`, `issuerCountry` are inherited from the program. No need to specify them.

### Create Virtual USD Card (without Card Program)
```
POST /cards
```

```json
{
  "customerId": "670cf0ad25852ba485d7590d",
  "type": "virtual",
  "currency": "USD",
  "issuerCountry": "USA",
  "status": "active",
  "enable2FA": true,
  "debitAccountId": "69bd13df144d27053ad6911b",
  "metadata": {}
}
```

#### Card Fields:
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| customerId | string | Yes | Customer ID |
| programId | string | No* | Card program ID |
| type | string | No* | `virtual` or `physical` |
| currency | string | No* | `USD`, `NGN` |
| issuerCountry | string | No* | `USA` for USD cards, `NGA` for NGN |
| status | string | Yes | `active` or `inactive` |
| enable2FA | boolean | No | Enable 2FA for card |
| debitAccountId | string | No | Settlement account ID |
| metadata | object | No | Must be `{}` object — NOT string `'{}'` |

> *If `programId` provided, `type`/`currency`/`issuerCountry` are inherited from program.

### Update Card (Freeze/Unfreeze/Terminate)
```
PUT /cards/{id}
```

**Freeze:**
```json
{ "status": "inactive" }
```

freeze:**
```json
{ "status": "active" }
```

**Terminate/Cancel:**
```json
{
  "status": "canceled",
  "cancellationReason": "lost"
}
```

Cancellation reasons: `lost`, `stolen`, `damaged`, `expired`, `other`

**Update Spending Controls:**
```json
{
  "spendingControls": {
    "channels": { "atm": true, "pos": true, "web": true, "mobile": true },
    "allowedCategories": [],
    "blockedCategories": [],
    "spendingLimits": [{ "amount": 1000, "interval": "monthly" }]
  }
}
```

### Get Cards for Customer
```
GET /cards/customer/{customerId}?page=0&limit=25
```

### Generate Card Token (for Secure Proxy)
```
GET /cards/{id}/token
```

> ⚠️ This is `GET` — NOT `POST`

Returns a short-lived token used with Secure Proxy Show to display card details in browser without data passing through your server.

### Fund Card
```
POST /cards/{id}/fund
```
```json
{ "amount": 50.00 }
```

### Withdraw from Card
```
POST /cards/{id}/withdraw
```
```json
{ "amount": 25.00 }
```

### Change Card PIN
```
PUT /cards/{id}/pin
```
```json
{
  "oldPin": "1234",
  "newPin": "5678"
}
```

---

## Card Programs

A Card Program is a centralized configuration governing how cards are issued, funded, and controlled. Cards inherit rules from their program.

### Create Card Program
```
POST /card-programs
```

```json
{
  "name": "Virtual USD Cards",
  "description": "For Virtual USD Cards",
  "status": "active",
  "debitAccountId": "69bd13df144d27053ad6911b",
  "fundingSourceId": "687a7c019d6f5695f5f4dafe",
  "issuerCountry": "USA",
",
  "cardBrand": "Visa",
  "cardType": "virtual",
  "spendingControls": {
    "channels": { "atm": false, "pos": true, "web": true, "mobile": true },
    "allowedCategories": [],
    "blockedCategories": [],
    "spendingLimits": []
  }
}
```

### Get Card Programs
```
GET /card-programs
```

Query params: `searchTerm`, `cardType`, `cardBrand`, `currency`, `status`, `fromDate`, `toDate`, `limit`, `page`

### Get Single Card Program
```
GET /card-programs/{id}
```

### Update Card Program
```
PATCH /card-programs{id}
```

### Get Cards in Program
```
GET /card-programs/{id}/cards
```

---

## Funding Sources

A funding source represents where funds are drawn from when a card transaction occurs.

> `fundingSourceId` = where transaction funds come from at authorization time
> `debitAccountId` = your business settlement account charged during card creation/funding

### 3 Types:

| Type | Description |
|------|-------------|
| `default` | Funds from customer's wallet at authorization time |
| `account` | Funds from business settlement account |
| `gateway` | Business account charged, but you approve/decline in real-time via webhook |

### Create Funding Source
```
POST /fundingsources
```

```json
{
  "type": "gateway",
  "status": "active",
  "jitGateway": {
    "url": "https://yourdomain.com/api/sudo/webhook",
    "authorizationHeader": "Bearer YOUR_SECRET_TOKEN",
    "authorizeByDefault": false
  }
}
```

### Update Funding Source
```
PUT /fundingsources/{id}
```

```json
{
  "status": "active",
  "jitGateway": {
    "url": "https://yourdomain.com/api/sudo/webhook",
    "authorizationHeader": "Bearer YOUR_SECRET_TOKEN",
    "authorizeByDefault": false
  }
}
```

> `jitGateway` only required if type is `gateway`

---

## Transactions

### Get Card Transactions
```
GET /cards/{id}/transactions?page=0&limit=25&fromDate=2024-01-01&toDate=2024-12-31
```

### Get All Transactions
```
GET /cards/transactions?page=0&limit=25
```

### Get Single Transaction
```
GET /cards/transactions/{id}
```

### Update Transaction Metadata
```
PUT /cards/transactions/{id}
```
```json
{ "metadata": {} }
```

---

## Retrieving Card Details (Vault Endpoint)

For PCI-DSS compliance, sensitive card data must go through the **vault endpoint**.

### Get Full Card Details (with PAN + CVV2)
```
GET {VAULT_URL}/cards/{cardId}?reveal=true
Authorization: Bearer {API_KEY}
```

| Environment | Vault URL |
|-------------|-----------|
| Sandbox | `https://vault.sandbox.sudo.cards` |
| Production | `https://vault.sudo.cards` |

Without `?reveal=true`, PAN and CVV2 are redacted.

---

## Displaying Sensitive Card Data (Secure Proxy Show)

For web — displays card number, CVV2, PIN in iframes without data passing through your server.

### Step 1: Import library
```html
<script src="https://js.securepro.xyz/sudo-show/1.1/ACiWvWF9tYAez4M498DHs.min.js"></script>
```

### Step 2: Get Card Token (via API)
```
GET /cards/{id}/token
Authorization: Bearer {API_KEY}
```

> ⚠️ This is `GET` not `POST`

### Step 3: Display via Secure Proxy
```javascript
const vaultId = "we0dsa28svdl2xefo5"; // sandbox
const show = SecureProxy.create(vaultId);
const cardToken = "<TOKEN_FROM_API>";
const cardId = "<CARD_ID>";

// Card Number
show.request({
  name: 'pan-text',
  method: 'GET',
  path: '/cards/' + cardId + '/secure-data/number',
  headers: { "Authorization": "Bearer " + cardToken },
  htmlWrapper: 'text',
  jsonPathSelector: 'data.number',
  serializers: [
    show.SERIALIZERS.replace('(\\d{4})(\\d{4})(\\d{4})(\\d{4})', '$1 $2 $3 $4')
  ]
}).render('#cardNumber');

// CVV2
show.request({
  name: 'cvv-text',
  method: 'GET',
  path: '/cards/' + cardId + '/secure-data/cvv2',
  headers: { "Authorization": "Bearer " + cardToken },
  htmlWrapper: 'text',
  jsonPathSelector: 'data.cvv2',
  serializers: []
}).render('#cvv2');
```

### Secure Data Paths:
| Path | Data |
|------|------|
| `/cards/{id}/secure-data/number` | Full card PAN |
| `/cards/{id}/secure-data/cvv2` | CVV2 |
| `/cards/{id}/secure-data/defaultPin` | Default PIN |

> For Flutter mobile: use vaultsplay in app. Secure Proxy is web/JS only.

---

## Spending Controls

### Spending Controls Object:
| Field | Type | Description |
|-------|------|-------------|
| channels.atm | boolean | ATM withdrawals |
| channels.pos | boolean | POS payments |
| channels.web | boolean | Online payments |
| channels.mobile | boolean | Mobile payments |
| allowedCategories | array | MCC codes to allow (empty = all) |
| blockedCategories | array | MCC codes to block |
| spendingLimits[].amount | number | Max amount |
| spendingLimits[].interval | enum | `per_authorization`, `daily`, `weekly`, `monthly`, `yearly`, `all_time` |
| spendingLimits[].categories | array | MCC codes to limit (empty = all) |

### Default Limits for USD Cards (if not set):
| Type | ATM | POS/WEB |
|------|-----|---------|
| Single Transaction | $0.00 | $0.00 |
| Daily | $0.00 | $0.00 |

> USD cards default to $0 limits — you must explicitly set spending limits when creating the card program or updating the card.

---

## Webhooks

Sudo seto your configured URL. For Gateway Funding Source cards, you must respond to `authorization.request` and `card.balance` within 4 seconds.

### Event Types:

| Event | Description | Response Required |
|-------|-------------|-------------------|
| `authorization.request` | Transaction attempted — approve or decline | ✅ Yes (4s timeout) |
| `authorization.updated` | Authorization status changed | ❌ No |
| `authorization.declined` | Card charge failed | ❌ No |
|ompleted | ❌ No |
| `transaction.refund` | Refund/reversal processed | ❌ No |
| `card.balance` | Balance inquiry on card | ✅ Yes (return balance) |
| `card.terminated` | Card terminated/canceled | ❌ No |

### authorization.request — Approve:
```json
{
  "statusCode": 200,
  "data": { "responseCode": "00" }
}
```

### authorization.request — Decline:
```json
{
  "statusCode": 400,
  "data": { "responseCode": "51" }
}
```
> `51` = Insufficient Balance (ISO 8583)

### card.balance — Return Balance:
```json
{
  "statusCode": 200,
  "data": { "responseCode": "00", "balance": 1234.56 }
}
```

### Key Webhook Payload Fields:

| Path | Description |
|------|-------------|
| `type` | Event type |
| `data.object._id` | Card ID (for `card.balance`) |
| `data.object.card._id` | Card ID (for `authorization.request`) |
| `data.object.pendingRequest.amount` | Total amount to approve |
| `data.object.pendingRequest.merchantAmount` | Merchant charge |
| `data.object.pendingRequest.currency` | Currency |
| `data.object.merant name |
| `data.object.merchant.category` | MCC code |
| `data.object.verification.cvv` | CVV match result |
| `data.object.verification.pin` | PIN match result |
| `data.object.transactionMetadata.channel` | `pos`/`web`/`atm`/`mobile` |
| `data.object.fee` | Transaction fee |

### Decline Reasons (`requestHistory.reason`):
| Reason | Description |
|--------|-------------|
| `not_allowed` | Insufficient funds, blocked category, etc. |
| `suspected_fraud` | Declined by Sudo fraud protection |
| `webhook_declined` | You declined via webhook |
| `spending_control` | Spending limit hit |

### card.terminated Payload Note:
The `data` object is the card itself (flat), not nested under `data.object`. Includes `balance` field showing remaining funds.

---

## Authorization Flow

1. User attempts transaction
2. Sudo checks balance, card/cardholder status, spending controls
3. Sends `authorization.request` webhook → you approve or decline
4. No response within 4s → auto-approved/declined based on `authorizeByDefault`
5. If approved → transaction created → `authorization.updated` sent

### Authorization Statuses:
| Status | Description |
|--------|-------------|
| `pending` | Awaiting response |
| `approved` | Approved, amount deducted |
| `declined` | Declined |
| `closed` | Transaction created, complete |

---

## Sandbox Testing

### 1. Fund Default Account
```
GET /accounts?page=0&limit=25
```
Note the account `_id`, then:
```
POST /accounts/simulator/fund
{ "accountId": "{{accountId}}", "amount": 5000.00 }
```

### 2. Simulate Card Traction
```
POST /cards/simulator/authorization
{
  "cardId": "{{cardId}}",
  "channel": "web",
  "type": "purchase",
  "amount": 25.00,
  "currency": "USD",
  "merchant": {
    "category": "7399",
    "merchantId": "000000001",
    "name": "Test Merchant",
    "city": "Lagos",
    "state": "LA",
    "country": "NG"
  }
}
```

### 3. Generate Sample Card Number
```
GET /cards/simulator/generate
```

---

## Merchant Category Codes (MCC)

| MCC | Description |
|-----|-------------|
| 5411 | Grocery Stores |
| 581| Restaurants |
| 5912 | Drug Stores / Pharmacies |
| 6011 | ATM Cash Disbursements |
| 6051 | Money Orders / Foreign Currency |
| 7399 | Business Services (General) |
| 5732 | Electronics Stores |
| 4511 | Airlines |
| 7011 | Hotels / Motels |
| 5541 | Gas Stations |
| 7995 | Betting / Casino |

---

## Our Integration (Vendlike)

### Current Config (Sandbox):
```env
SUDO_ENVIRONMENT=sandbox
SUDO_API_KEY=<from .env>
SUDO_VAULT_ID=we0dsa28svdl2xefo5
SUDO_CARD_PROGRAM_ID=69bda183144d27053ad6b6ad
SUDO_DEBIT_ACCOUNT_ID=69bd13df144d27053ad6911b
SUDO_FUNDING_SOURCE_ID=687a7c019d6f5695f5f4dafe
```

### Card Type: Virtual USD only
- `type: virtual`, `currency: USD`, `issuerCountry: USA`
- Cards linked to customer via `customerId`
- Customer stored in `user.sudo_customer_id`
- Cards stored in `virtual_cards` table

### Flow — Create Card:
1. Check `sudo_card_lock` setting
2. Check user has no existing active card
3. Verify transaction PIN
4. Check creation fee balance (fee i_rate`)
5. Create Sudo customer if `sudo_customer_id` is null
6. Always call `updateCustomer()` to sync latest user data (name, phone, email, BVN/NIN, DOB)
7. Call `createVirtualCard(customerId)`
8. Debit creation fee from user wallet
9. Save card to `virtual_cards` table
10. Log to `message` table

### Flow — Fund Card:
- Amount in USD, debit in NGN = `amount × dollar_rate × (1 + funding_fee_percent/100)`
- Call `POST /cards/{id}/fund`
- Increment `virtual_cards.card_balance`

:
- Amount in USD, credit in NGN = `amount × dollar_rate × (1 - withdrawal_fee_percent/100)`
- Call `POST /cards/{id}/withdraw`
- Decrement `virtual_cards.card_balance`

### Flow — Freeze/Unfreeze:
- Freeze → `PUT /cards/{id}` with `status: inactive`
- Unfreeze → `PUT /cards/{id}` with `status: active`

### Flow — Terminate:
- `PUT /cards/{id}` with `status: canceled`, `cancellationReason: lost`
- Refund remaining balance to user wallet at current dollar rate

### Webhook Handler:
approve, log to `sudo_webhooks`
- `card.balance` → return `card_balance` from `virtual_cards` table
- `transaction.created` → decrement `card_balance`, log to `sudo_webhooks`

### Key PHP Notes:
- `metadata` must be `(object)[]` in PHP — NOT `'{}'`
- `updateCustomer()` uses `PUT` — NOT `PATCH`
- `generateCardToken()` uses `GET /cards/{id}/token` — NOT `POST`
- DOB format for Sudo: `YYYY/MM/DD` (use Carbon: `->format('Y/m/d')`)
- Customer field names: `phoneNumber`, `emailAddress` (NOT `phone`/`email`)

---

## Cloud Card (NFC / Tap-to-Pay — Future Feature)

Sudo Cloud Card enables card digitization for NFC tap-to-pay via mobile SDKs.

### Flutter SDK:
```yaml
dependencies:
  cloudcard_flutter: ^0.1.1
```

### Digitalization Endpoint:
```
GET /cards/digitalize/{id}
Accept: text/plain
platform: android
Authorization: Bearer {API_KEY}
```

> Cloud Card is optional/future. Core virtual card feature works without it.

---

## IP Whitelist



| Format | Example |
|--------|---------|
| Exact IPv4 | `192.168.1.10` |
| CIDR | `192.168.0.0/16` |
| Wildcard | `*` (allow all) |
