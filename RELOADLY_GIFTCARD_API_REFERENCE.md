# Reloadly Gift Card API Reference

## Provider: Reloadly
## Purpose: Buy Gift Card (Automated) — User pays from wallet, gets gift card instantly via API

---

## Environment URLs

| Environment | Base URL |
|-------------|----------|
| Production  | `https://giftcards.reloadly.com` |
| Sandbox     | `https://giftcards-sandbox.reloadly.com` |
| Auth        | `https://auth.reloadly.com` |

## Common Headers

```
Accept: application/com.reloadly.giftcards-v1+json
Authorization: Bearer <ACCESS_TOKEN>
Content-Type: application/json  (for POST requests)
```

---

## 1. Authentication

**POST** `https://auth.reloadly.com/oauth/token`

### Request Body
```json
{
  "client_id": "YOUR_CLIENT_ID",
  "client_secret": "YOUR_CLIENT_SECRET",
  "grant_type": "client_credentials",
  "audience": "https://giftcards.reloadly.com"
}
```
- Sandbox audience: `https://giftcards-sandbox.reloadly.com`

### Response (200)
```json
{
  "access_token": "eyJraXXXXX...",
  "scope": "developer",
  "expires_in": 5184000,
  "token_type": "Bearer"
}
```
- Token expires in ~60 days (5,184,000 seconds)
- Gift card token ONLY works on gift card endpoints

---

## 2. Account Balance

**GET** `/accounts/balance`

### Response (200)
```json
{
  "balance": 6000.23,
  "currencyCode": "USD",
  "currencyName": "US Dollar",
  "updatedAt": "2022-02-04 17:45:51"
}
```

---

## 3. Categories

**GET** `/product-categories`

### Response (200)
```json
[
  { "id": 1, "name": "Finance" },
  { "id": 2, "name": "Software" }
]
```

---

## 4. Countries

### Get All Countries
**GET** `/countries`

### Response (200)
```json
[
  {
    "isoName": "AF",
    "name": "Afghanistan",
    "continent": "Asia",
    "currencyCode": "AFN",
    "currencyName": "Afghan Afghani",
    "currencySymbol": "؋",
    "flag": "https://s3.amazonaws.com/rld-flags/af.svg",
    "callingCodes": ["+93"]
  }
]
```

### Get Country by ISO Code
**GET** `/countries/{countrycode}`

Returns single country object (same structure).

---

## 5. Products (Gift Cards)

### Get All Products
**GET** `/products?size=10&page=1&productName=Amazon&countryCode=US&productCategoryId=2&includeRange=true&includeFixed=true`

### Query Parameters
| Param | Type | Description |
|-------|------|-------------|
| size | int | Products per page |
| page | int | Page number |
| productName | string | Filter by name |
| countryCode | string | Filter by country ISO |
| productCategoryId | string | Filter by category ID |
| includeRange | bool | Include RANGE denomination products |
| includeFixed | bool | Include FIXED denomination products |

### Response (200) — Product Object
```json
{
  "productId": 1,
  "productName": "1-800-PetSupplies",
  "global": false,
  "supportsPreOrder": false,
  "status": "ACTIVE",
  "senderFee": 205.29,
  "senderFeePercentage": 1,
  "discountPercentage": 7.5,
  "denominationType": "FIXED",
  "recipientCurrencyCode": "USD",
  "recipientCurrencyToSenderCurrencyExchangeRate": 570,
  "minRecipientDenomination": null,
  "maxRecipientDenomination": null,
  "senderCurrencyCode": "NGN",
  "minSenderDenomination": null,
  "maxSenderDenomination": null,
  "fixedRecipientDenominations": [25, 50],
  "fixedSenderDenominations": [10264.5, 20529],
  "fixedRecipientToSenderDenominationsMap": [
    {"25.00": 10264.5},
    {"50.00": 20529}
  ],
  "logoUrls": ["https://cdn.reloadly.com/giftcards/xxx.jpg"],
  "brand": {
    "brandId": 1,
    "brandName": "1-800-PetSupplies"
  },
  "category": {
    "id": 5,
    "name": "Fashion and Retails"
  },
  "country": {
    "isoName": "US",
    "name": "United States",
    "flagUrl": "https://s3.amazonaws.com/rld-flags/us.svg"
  },
  "redeemInstruction": {
    "concise": "Short instructions...",
    "verbose": "Detailed instructions..."
  },
  "additionalRequirements": {
    "userIdRequired": false
  }
}
```

### Key Fields Explained
- **denominationType**: `FIXED` = user picks from `fixedRecipientDenominations` array. `RANGE` = user picks any amount between `minRecipientDenomination` and `maxRecipientDenomination`
- **fixedRecipientToSenderDenominationsMap**: Maps gift card price → cost in sender's currency (NGN)
- **senderFee**: Flat fee in sender currency (~0.5 USD equivalent)
- **discountPercentage**: Discount Reloadly gives on the purchase
- **recipientCurrencyToSenderCurrencyExchangeRate**: FX rate for conversion

### Get Product by ID
**GET** `/products/{productId}`

Returns single product object (same structure).

### Get Products by Country ISO Code
**GET** `/countries/{countrycode}/products`

Returns array of product objects for that country.

---

## 6. Redeem Instructions

### Get All Redeem Instructions
**GET** `/redeem-instructions`

### Response (200)
```json
[
  {
    "brandId": 1,
    "brandName": "1-800-PetSupplies",
    "concise": "Short instructions...",
    "verbose": "Detailed instructions..."
  }
]
```

### Get Redeem Instructions by Product ID
**GET** `/products/{productId}/redeem-instructions`

### Response (200)
```json
{
  "productId": 3245,
  "productName": "Free Fire 210 + 21 Diamond IN",
  "concise": "Short instructions...",
  "verbose": "Detailed instructions..."
}
```

---

## 7. FX Rates

**GET** `/fx-rate?currencyCode=USD&amount=94.99`

### Response (200)
```json
{
  "senderCurrency": "EUR",
  "senderAmount": 88.50978,
  "recipientCurrency": "USD",
  "recipientAmount": 94.99
}
```

---

## 8. Discounts

### Get All Discounts
**GET** `/discounts?size=50&page=2`

### Response (200)
```json
[
  {
    "product": {
      "productId": 28,
      "productName": "Apple Music 12 month Canada",
      "countryCode": "CA",
      "global": false
    },
    "discountPercentage": 2
  }
]
```

### Get Discount by Product ID
**GET** `/products/{productId}/discounts`

Returns single discount object (same structure).

---

## 9. Transactions

### Get All Transactions
**GET** `/reports/transactions?size=10&page=1&customIdentifier=obucks10&startDate=2021-06-01 10:00:00&endDate=2021-07-20 19:17:02`

### Transaction Statuses
| Status | Description |
|--------|-------------|
| SUCCESSFUL | Gift card purchased successfully |
| PENDING | Order placed, queued for processing |
| PROCESSING | Order placed, still processing |
| REFUNDED | Purchase failed, funds reversed |
| FAILED | Purchase failed, funds not yet reversed |

### Response (200) — Transaction Object
```json
{
  "transactionId": 1,
  "amount": 60553.3575,
  "discount": 0,
  "currencyCode": "NGN",
  "fee": 1880,
  "customIdentifier": "obucks3",
  "status": "SUCCESSFUL",
  "product": {
    "productId": 4,
    "productName": "Amazon Spain",
    "countryCode": "ES",
    "quantity": 5,
    "unitPrice": 25,
    "totalPrice": 125,
    "currencyCode": "EUR",
    "brand": {
      "brandId": 2,
      "brandName": "Amazon"
    }
  },
  "smsFee": 185.76,
  "totalFee": 2065.76,
  "recipientPhone": 34012345678,
  "recipientEmail": "johndoe@gmail.com",
  "transactionCreatedTime": "2022-02-28 13:46:00",
  "preOrdered": false,
  "balanceInfo": {
    "oldBalance": 60582.23641,
    "newBalance": 28.86891,
    "cost": 60553.3575,
    "currencyCode": "NGN",
    "currencyName": "Nigerian Naira",
    "updatedAt": "2022-02-28 13:46:00"
  }
}
```

### Get Transaction by ID
**GET** `/reports/transactions/{transactionId}`

Returns single transaction object (same structure).

---

## 10. Order Gift Card (PURCHASE)

**POST** `/orders`

### Request Body
```json
{
  "productId": 10,
  "quantity": 2,
  "unitPrice": 5,
  "customIdentifier": "obucks10",
  "senderName": "John Doe",
  "recipientEmail": "anyone@email.com",
  "recipientPhoneDetails": {
    "countryCode": "ES",
    "phoneNumber": "012345678"
  },
  "preOrder": false,
  "productAdditionalRequirements": {
    "userId": "12"
  }
}
```

### Required Fields
| Field | Type | Description |
|-------|------|-------------|
| productId | int | Product ID from /products |
| quantity | int | Number of gift cards |
| unitPrice | number | Price per card (must match fixedRecipientDenominations for FIXED, or be within min/max for RANGE) |
| senderName | string | Name on receipt |

### Optional Fields
| Field | Type | Description |
|-------|------|-------------|
| customIdentifier | string | Your unique reference for this order |
| recipientEmail | string | Recipient email (Reloadly sends card details) |
| recipientPhoneDetails | object | { countryCode, phoneNumber } |
| preOrder | bool | Pre-order flag (default false) |
| productAdditionalRequirements | object | Extra info if product requires it |

### Response (200) — Same as Transaction Object
Returns full transaction details including `transactionId`, `status`, `amount`, `product`, `balanceInfo`, etc.

---

## 11. Get Redeem Code (RETRIEVE CARD AFTER PURCHASE)

**GET** `/orders/transactions/{transactionId}/cards`

### Headers
Use `Accept: application/com.reloadly.giftcards-v2+json` for v2 (includes redemptionUrl)

### Response (200) — v2
```json
{
  "cardNumber": 6120200345149064,
  "pinCode": "EFSDCEAFSD",
  "redemptionUrl": "https://dashboard-stage.swype.cards/activate/verify?redemption-code=XXXXXXXXXXXXXXX"
}
```

### Response (200) — v1
```json
{
  "cardNumber": 6120200345149064,
  "pinCode": "EFSDCEAFSD"
}
```

---

## ENV Variables Needed
```
RELOADLY_CLIENT_ID=your_client_id
RELOADLY_CLIENT_SECRET=your_client_secret
RELOADLY_ENVIRONMENT=sandbox   # or production
RELOADLY_SANDBOX_URL=https://giftcards-sandbox.reloadly.com
RELOADLY_PRODUCTION_URL=https://giftcards.reloadly.com
RELOADLY_AUTH_URL=https://auth.reloadly.com
```

---

## Integration Flow (Buy Gift Card)

1. **Backend caches token** — Auth token lasts ~60 days, cache it and refresh when expired
2. **Mobile app loads products** — Backend proxies `/products` or `/countries/{iso}/products` to mobile, includes `markup_percentage` in response
3. **User selects product + amount** — FIXED: pick from fixedRecipientDenominations. RANGE: enter custom amount
4. **Mobile calculates price to show user** — Uses Reloadly's sender cost (NGN) + admin markup %
5. **User confirms purchase** — Backend deducts from user's main wallet (₦), calls `POST /orders` with Reloadly
6. **Backend retrieves redeem code** — Calls `GET /orders/transactions/{id}/cards` to get cardNumber/pinCode
7. **User sees card details** — Receipt screen shows cardNumber, pinCode, redemptionUrl, redeem instructions
8. **Transaction logged** — Inserted into `message` table with `transid` like `BG_xxxxx` (Buy Gift card)

## Pricing Model (Percentage Markup)

Admin sets a **percentage markup** on Reloadly's cost price (stored in `settings.buy_giftcard_markup`, default 5%).

### How it works:
- **FIXED denomination cards**: Reloadly provides `fixedRecipientToSenderDenominationsMap` which maps each recipient denomination (e.g. $25 USD) to the sender cost in NGN (e.g. ₦10,264.50). We add the markup % on top of that sender cost.
- **RANGE denomination cards**: Reloadly provides `recipientCurrencyToSenderCurrencyExchangeRate` (e.g. 570 NGN per 1 USD). We multiply the user's chosen amount by this rate to get sender cost, then add markup %.

### Example (FIXED, 5% markup):
- User picks $25 Amazon US gift card
- Reloadly sender cost: ₦10,264.50
- Our selling price: ₦10,264.50 × 1.05 = ₦10,777.73
- Profit: ₦513.23

### Example (RANGE, 5% markup):
- User picks $40 custom amount, FX rate = 570 NGN/$1
- Reloadly sender cost: $40 × 570 = ₦22,800
- Our selling price: ₦22,800 × 1.05 = ₦23,940
- Profit: ₦1,140

### Admin controls:
- `buy_giftcard_markup` — Percentage (0-100), e.g. 5.00 means 5%
- `buy_giftcard_status` — 1 = enabled, 0 = disabled
