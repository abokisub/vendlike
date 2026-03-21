# FEZ DELIVERY API REFERENCE

## Overview
Fez Delivery is a logistics/delivery API for dispatching and tracking physical product deliveries across Nigeria (36 states + FCT).

## Base URLs
- **Sandbox**: `https://apisandbox.fezdelivery.co/v1`
- **Production**: (separate signup at fezdelivery.co)

## Authentication
All API calls require two headers:
- `Authorization: Bearer {authToken}` — obtained from login
- `secret-key: {your-secret-key}` — obtained from login response (`orgDetails.secret-key`)

Token has an expiry date returned in `authDetails.expireToken`.

---

## 1. AUTHENTICATION ENDPOINTS

### 1.1 Authenticate (Login)
```
POST /user/authenticate
```

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| user_id | String | Yes | User ID (e.g. "G-4568-3493") |
| password | String | Yes | User Password |

**Success Response (200):**
```json
{
  "status": "Success",
  "description": "Login Successfull",
  "authDetails": {
    "authToken": "PBKWY4APEAQD83FBU9GZK37NGH11SUNH-168",
    "expireToken": "2023-01-27 05:06:08"
  },
  "userDetails": {
    "userID": "G-4568-3493",
    "Full Name": "King One Admin",
    "Username": "kingOneAdmin"
  },
  "orgDetails": {
    "secret-key": "T2Y629UUeiwe7fjdj838934ooosoi82398297297482992",
    "Org Full Name": "King One Enterprise"
  }
}
```

**PHP Example:**
```php
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://apisandbox.fezdelivery.co/v1/user/authenticate',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => json_encode([
    'user_id' => 'G-4568-3493',
    'password' => 'KingOne123#'
  ]),
  CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
));
$response = curl_exec($curl);
curl_close($curl);
```

**Key Notes:**
- Admin credentials are created during onboarding
- `authToken` = Bearer token for all subsequent calls
- `secret-key` = Organization identifier, must be included in all headers
- Token expires at `expireToken` datetime

---

### 1.2 Logout
```
POST /user/logout
```

**Headers:** `Authorization: Bearer {token}`, `secret-key: {key}`

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| user_id | String | Yes | User ID |

**Success Response (200):**
```json
{
  "status": "Success",
  "description": "Logout Successfull"
}
```

---

### 1.3 Change Password
```
POST /user/changePassword
```

**Headers:** `Authorization: Bearer {token}`, `secret-key: {key}`

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| user_id | String | Yes | User ID |
| oldPassword | String | Yes | Current password |
| newPassword | String | Yes | New password |

**Success Response (200):**
```json
{
  "status": "Success",
  "description": "Password Successfully Changed"
}
```

**Error (400):**
```json
{
  "status": "Error",
  "description": "Old Password Is Incorrect"
}
```

---

## 2. ORDER ENDPOINTS

### 2.1 Create Order (Book Delivery)
```
POST /order
```
**This is the core endpoint for requesting a pickup/delivery.**

**Headers:** `Authorization: Bearer {token}`, `secret-key: {key}`

**Request Body:** Array of order objects `[{...}, {...}]`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| recipientAddress | String | Yes | Delivery destination address |
| recipientState | String | Yes | Must be any of the 36 Nigerian states or FCT |
| recipientName | String | Yes | Recipient name |
| recipientPhone | String | Yes | Recipient phone number |
| recipientEmail | Email | No | Recipient email |
| uniqueID | String | Yes | Client-side unique identifier for this delivery |
| BatchID | String | Yes | Groups multiple deliveries in one API call |
| CustToken | String | No | Token sent to recipient for delivery confirmation |
| itemDescription | String | No | Description of the order |
| additionalDetails | String | No | Extra info (landmarks, alt phone numbers, etc.) |
| valueOfItem | String | Yes | Value of items in Naira |
| weight | Integer | Yes | Weight in Kg |
| pickUpState | String | No | Pickup state (defaults to org address state) |
| pickUpAddress | String | No | Pickup address (defaults to org business address) |
| waybillNumber | String | No | 3rd party logistics tracking number |
| pickUpDate | Date | No | Requested pickup date |
| isItemCod | Boolean | No | Cash on delivery flag (default: false) |
| cashOnDeliveryAmount | Decimal/Int | No | COD amount |
| fragile | Boolean | No | Fragile item flag (default: false) |
| lockerID | String | No | Locker delivery ID |

**Third-Party Sender Override (Optional):**
If you want a different sender than the org default, add:
| Field | Type | Description |
|-------|------|-------------|
| thirdparty | String | Set to "true" |
| senderName | String | Sender's name |
| senderAddress | String | Sender's address |
| senderPhone | String | Sender's phone |

**Success Response (201):**
```json
{
  "status": "Success",
  "description": "Order Successfully Created",
  "orderNos": {
    "KingOne-1234": "ASAC27012319",
    "KingOne-1235": "JHAZ27012319"
  }
}
```
- Keys are the `uniqueID` values, values are the Fez `orderNo` assigned.

**PHP Example:**
```php
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://apisandbox.fezdelivery.co/v1/order',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => json_encode([
    [
      'recipientAddress' => 'Idumota',
      'recipientState' => 'Lagos',
      'recipientName' => 'Femi',
      'recipientPhone' => '08000000000',
      'uniqueID' => 'MP_20260319001',
      'BatchID' => 'BATCH_001',
      'valueOfItem' => '20000',
      'weight' => 2
    ]
  ]),
  CURLOPT_HTTPHEADER => array(
    'Content-Type: application/json',
    'Authorization: Bearer {authToken}',
    'secret-key: {secretKey}'
  ),
));
$response = curl_exec($curl);
curl_close($curl);
```

---

### 2.2 Get Order Details
```
GET /orders/{orderNo}
```

**Headers:** `Authorization: Bearer {token}`, `secret-key: {key}`

**Success Response (200):**
```json
{
  "status": "Success",
  "description": "Details Fetched Successfully",
  "orderDetails": [
    {
      "orderNo": "JHAZ27012319",
      "recipientName": "Femi2",
      "recipientEmail": "",
      "recipientAddress": "Idumota3",
      "recipientPhone": "08000000000000",
      "orderStatus": "Pending Pick-Up",
      "statusDescription": null,
      "cost": "900",
      "createdBy": "C-39006-611",
      "OrgRep": "G-ylNt-c7xD",
      "pickUpDate": "0000-00-00 00:00:00",
      "dispatchDate": null,
      "deliveryDate": null,
      "returnDate": null,
      "dropZoneName": null,
      "returnReason": null
    }
  ]
}
```

**Key Fields:**
- `orderStatus` — Current status of the delivery
- `cost` — Delivery cost charged by Fez
- `pickUpDate` — When item was picked up
- `dispatchDate` — When item was dispatched
- `deliveryDate` — When item was delivered
- `returnDate` — When item was returned (if applicable)
- `returnReason` — Why item was returned

---

### 2.3 Update Order
```
PUT /order
```
(Actual URL used: `/order/multiple`)

**Headers:** `Authorization: Bearer {token}`, `secret-key: {key}`

**Request Body:** Array of order objects `[{...}, {...}]`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| orderNo | String | Yes | Fez order number |
| recipientAddress | String | No | Updated address |
| recipientState | String | No | Updated state |
| recipientName | String | No | Updated name |
| recipientPhone | String | No | Updated phone |

**Success Response (201):**
```json
{
  "status": "Success",
  "description": "Update Batch Request Processed",
  "Response": {
    "37P727012321": "Order Successfully Updated",
    "JHAZ27012319": "Order Successfully Updated"
  }
}
```

---

### 2.4 Delete Order
```
DELETE /order
```

**Headers:** `Authorization: Bearer {token}`, `secret-key: {key}`

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| orderNo | String | Yes | Fez order number to delete |

**Success Response (200):**
```json
{
  "status": "Success",
  "description": "Order Successfully Deleted"
}
```

---

### 2.5 Search Orders
```
POST /orders/search
```

**Headers:** `Authorization: Bearer {token}`, `secret-key: {key}`

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| startDate | String | Yes | Start date (YYYY-MM-DD) |
| endDate | String | Yes | End date (YYYY-MM-DD) |
| page | Integer | Yes | Page number (50 results per page) |
| orderNo | String | No | Filter by order number |
| recipientName | String | No | Filter by recipient name |
| recipientPhone | String | No | Filter by recipient phone |
| orderStatus | String | No | Filter by status (see Order Statuses below) |
| OrgRep | String | No | Filter by user_id who created the order |

**Success Response (200):**
```json
{
  "status": "Success",
  "description": "Order Fetched Successfully",
  "orders": {
    "current_page": 1,
    "data": [
      {
        "orderNo": "O41027012333",
        "recipientName": "Femi",
        "recipientEmail": "",
        "recipientAddress": "Idumota",
        "recipientPhone": "08000000000000",
        "orderStatus": "Pending Pick-Up",
        "statusDescription": null,
        "cost": "900",
        "createdBy": "C-39006-611",
        "OrgRep": "G-ylNt-c7xD",
        "orderDate": "2023-01-27 02:46:33",
        "pickUpDate": "0000-00-00 00:00:00",
        "dispatchDate": null,
        "deliveryDate": null,
        "returnReason": null,
        "returnDate": null
      }
    ],
    "per_page": 50,
    "total": 6,
    "last_page": 1,
    "next_page_url": null
  }
}
```

---

### 2.6 Search by Waybill Number
```
GET /orders/search/{waybillNumber}
```

**Headers:** `Authorization: Bearer {token}`, `secret-key: {key}`

**Success Response (200):**
```json
{
  "status": "Success",
  "description": "Order Fetch Successfully",
  "data": {
    "orderNumber": "KMXH15082440",
    "waybillNumber": "74U839SUSY7"
  }
}
```

---

### 2.7 Stats With Date Range
```
POST /orders/statsWithDateRange
```

**Headers:** `Authorization: Bearer {token}`, `secret-key: {key}`

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| startDate | String | Yes | Start date (YYYY-MM-DD) |
| endDate | String | No | End date (YYYY-MM-DD) |

Returns order counts per status within the date range.

---

## 3. DELIVERY COST ENDPOINT

### 3.1 Fetch Delivery Cost
```
POST /order/cost
```
**This is the pricing endpoint — use it to calculate delivery fee before placing an order.**

**Headers:** `Authorization: Bearer {token}`, `secret-key: {key}`

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| state | String | No | Destination state (if empty, returns cost to ALL 36 states + FCT) |
| pickUpState | String | No | Pickup state (defaults to org profile state) |
| weight | Numeric | No | Weight in Kg (default pricing is 0-5kg if omitted) |
| locker | Boolean | No | Deliver via locker (default: false) |

**Key Notes:**
- Cost is based on: pickup state → destination state + weight
- Pickup state defaults to the state configured in your org profile
- If `state` is omitted, returns pricing for ALL states
- **Total cost = `cost` + `vatAmount`** (use `totalCost` field)
- Use lowercase `cost` key in response (not `Cost`)

**Success Response (200):**
```json
{
  "status": "Success",
  "description": "Cost Fetched Successfully",
  "Cost": {
    "state": "Kano",
    "cost": 6000
  },
  "cost": {
    "state": "Kano",
    "cost": 6000
  },
  "vat": {
    "vatAmount": 450,
    "vatPercent": "7.50"
  },
  "totalCost": 6450
}
```

**Response Fields:**
| Field | Description |
|-------|-------------|
| cost.state | Destination state |
| cost.cost | Base delivery cost (Naira) |
| vat.vatAmount | VAT amount |
| vat.vatPercent | VAT percentage (e.g. 7.50%) |
| totalCost | **Final amount = cost + VAT** (this is what to charge) |

**PHP Example:**
```php
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://apisandbox.fezdelivery.co/v1/order/cost',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => json_encode([
    'state' => 'Kano',
    'weight' => 2
  ]),
  CURLOPT_HTTPHEADER => array(
    'Content-Type: application/json',
    'Authorization: Bearer {authToken}',
    'secret-key: {secretKey}'
  ),
));
$response = curl_exec($curl);
curl_close($curl);
```

**Integration Plan:**
- Mobile app: When user selects delivery state at checkout → call our backend → backend calls Fez `/order/cost` → return `totalCost` to app as delivery fee
- Replace flat `marketplace_delivery_fee` with dynamic Fez pricing
- Cache pricing per state for short TTL (5 min) to avoid excessive API calls

---

## 4. TRACKING ENDPOINT

### 4.1 Track Order
```
GET /order/track/{orderNumber}
```
**Get the status and full delivery timeline/history of an order.**

**Headers:** `Authorization: Bearer {token}`, `secret-key: {key}`

**Success Response (200):**
```json
{
  "status": "Success",
  "description": "History Fetched",
  "order": {
    "orderNo": "1T7002122309",
    "orderStatus": "Dispatched",
    "recipientAddress": "10",
    "recipientName": "ferferf",
    "senderAddress": "3A SULAIMON SHODERU STREET, HARUNA BUS STOP, IKORODU",
    "senderName": "  KIDS PLACE",
    "recipientState": "Lagos",
    "createdAt": "2023-12-02 06:14:09"
  },
  "history": [
    {
      "orderStatus": "Dispatched",
      "statusCreationDate": "2023-12-02 13:14:40",
      "statusDescription": "Your package is on its way to the delivery address and is en route to the customer"
    },
    {
      "orderStatus": "Picked-Up",
      "statusCreationDate": "2023-12-02 13:14:09",
      "statusDescription": "Our rider has picked-up your item and is heading back to the office"
    }
  ]
}
```

**Key Fields:**
- `order` — Current order info (status, sender/recipient details)
- `history` — Array of timeline events, newest first
- Each history entry has `orderStatus`, `statusCreationDate`, `statusDescription`

**PHP Example:**
```php
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://apisandbox.fezdelivery.co/v1/order/track/1T7002122309',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CUSTOMREQUEST => 'GET',
  CURLOPT_HTTPHEADER => array('secret-key: {{Your Secret Key}}'),
));
$response = curl_exec($curl);
curl_close($curl);
```

**Integration Plan:**
- Use this to show delivery timeline on mobile order detail screen
- Poll periodically or on user pull-to-refresh to update delivery status
- Map `history` array to a visual timeline widget in Flutter

---

## 5. UTILITY ENDPOINTS

### 5.1 Delivery Time Estimate
```
POST /delivery-time-estimate
```
**Get estimated delivery time between two states.**

**Headers:** `Authorization: Bearer {token}`, `secret-key: {key}`

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| delivery_type | String (Enum) | Yes | One of: `import`, `export`, `local` |
| pick_up_state | String | No | Valid state within Fez delivery coverage |
| drop_off_state | String | No | Valid state within Fez delivery coverage |

**Success Response (200):**
```json
{
  "status": "Success",
  "description": "Update Batch Request Processed",
  "data": {
    "eta": "2 - 5 day(s)"
  }
}
```

**Error (422):**
```json
{
  "status": "Error",
  "description": "The delivery type field is required."
}
```

**PHP Example:**
```php
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://apisandbox.fezdelivery.co/v1/delivery-time-estimate',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => json_encode([
    'delivery_type' => 'local',
    'pick_up_state' => 'edo',
    'drop_off_state' => 'lagos'
  ]),
  CURLOPT_HTTPHEADER => array(
    'Content-Type: application/json',
    'secret-key: {{Your Secret Key}}'
  ),
));
$response = curl_exec($curl);
curl_close($curl);
```

**Integration Plan:**
- Show ETA on mobile checkout screen after user selects delivery state
- Call alongside `/order/cost` to show both price and time estimate
- Use `delivery_type: 'local'` for all Nigerian deliveries

---

### 5.2 Get States
```
GET /states
```
**Get list of all 36 Nigerian states + FCT with their IDs.**

**Headers:** `Authorization: Bearer {token}`, `secret-key: {key}`

**Success Response (200):**
```json
{
  "status": "Success",
  "description": "states fetched successfully",
  "states": [
    {"id": 1, "state": "Kano"},
    {"id": 2, "state": "Lagos"},
    {"id": 3, "state": "Kaduna"},
    {"id": 4, "state": "Katsina"},
    {"id": 5, "state": "Oyo"},
    {"id": 6, "state": "Rivers"},
    {"id": 7, "state": "Bauchi"},
    {"id": 8, "state": "Jigawa"},
    {"id": 9, "state": "Benue"},
    {"id": 10, "state": "Anambra"},
    {"id": 11, "state": "Borno"},
    {"id": 12, "state": "Delta"},
    {"id": 13, "state": "Imo"},
    {"id": 14, "state": "Niger"},
    {"id": 15, "state": "Akwa Ibom"},
    {"id": 16, "state": "Ogun"},
    {"id": 17, "state": "Sokoto"},
    {"id": 18, "state": "Ondo"},
    {"id": 19, "state": "Osun"},
    {"id": 20, "state": "Kogi"},
    {"id": 21, "state": "Zamfara"},
    {"id": 22, "state": "Enugu"},
    {"id": 23, "state": "Kebbi"},
    {"id": 24, "state": "Edo"},
    {"id": 25, "state": "Plateau"},
    {"id": 26, "state": "Adamawa"},
    {"id": 27, "state": "Cross River"},
    {"id": 28, "state": "Abia"},
    {"id": 29, "state": "Ekiti"},
    {"id": 30, "state": "Kwara"},
    {"id": 31, "state": "Gombe"},
    {"id": 32, "state": "Yobe"},
    {"id": 33, "state": "Taraba"},
    {"id": 34, "state": "Ebonyi"},
    {"id": 35, "state": "Nasarawa"},
    {"id": 36, "state": "Bayelsa"},
    {"id": 37, "state": "FCT"}
  ]
}
```

**Key Notes:**
- State IDs are used for the `/hubs/{stateId}` endpoint
- 37 entries total (36 states + FCT)

**PHP Example:**
```php
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://apisandbox.fezdelivery.co/v1/states',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CUSTOMREQUEST => 'GET',
  CURLOPT_HTTPHEADER => array('secret-key: {{Your Secret Key}}'),
));
$response = curl_exec($curl);
curl_close($curl);
```

---

### 5.3 Get Hubs by State
```
GET /hubs/{stateId}
```
**Get pickup/drop-off hub locations for a specific state.**

**Headers:** `Authorization: Bearer {token}`, `secret-key: {key}`

**Params:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| stateId | Integer | Yes | State ID (from `/states` endpoint) |

**Success Response (200):**
```json
{
  "status": "Success",
  "description": "Hubs fetched successfully",
  "hubs": [
    {"id": 1, "name": "Lagoswa", "address": "Lagos Nigeria"},
    {"id": 15, "name": "C-98100-753", "address": "custom road"}
  ]
}
```

**Error (401):**
```json
{
  "status": "Error",
  "description": "No hubs found for the specified state."
}
```

**Error (422):**
```json
{
  "status": "Error",
  "description": "State ID is required."
}
```

**PHP Example:**
```php
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://apisandbox.fezdelivery.co/v1/hubs/2',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CUSTOMREQUEST => 'GET',
  CURLOPT_HTTPHEADER => array('secret-key: {{Your Secret Key}}'),
));
$response = curl_exec($curl);
curl_close($curl);
```

---

## 6. ORDER STATUSES

| Status | Description |
|--------|-------------|
| Pending Pick-Up | Order created, awaiting pickup |
| Picked-Up | Item has been picked up by Fez |
| Dispatched | Item is in transit to recipient |
| Delivered | Item delivered to recipient |
| Returned | Item returned (see returnReason) |

---

## 7. COMMON ERROR RESPONSES

**401 Unauthorized:**
```json
{
  "status": "Error",
  "description": "Organization Secret Key is Required"
}
```

---

## 8. INTEGRATION NOTES FOR VENDLIKE MARKETPLACE

### How We'll Use Fez:
1. **On order placement** → Call `POST /order` to book delivery with Fez
2. **Store Fez `orderNo`** in `marketplace_orders.tracking_number`
3. **Map our `reference` (MP_xxx)** to Fez `uniqueID` for cross-referencing
4. **Track delivery** → Call `GET /orders/{orderNo}` to get status updates
5. **Admin dashboard** → Show Fez delivery status alongside order status
6. **Delivery cost** → Fez returns `cost` in order details (can use for dynamic pricing)

### Status Mapping (Fez → Vendlike):
| Fez Status | Vendlike Order Status |
|------------|----------------------|
| Pending Pick-Up | processing |
| Picked-Up | processing |
| Dispatched | shipped |
| Delivered | delivered |
| Returned | cancelled (with refund) |

### ENV Variables Needed:
```
FEZ_USER_ID=
FEZ_PASSWORD=
FEZ_SECRET_KEY=
FEZ_ENVIRONMENT=sandbox
```

### Files to Create/Modify:
- `app/Services/FezDeliveryService.php` — API client (auth with token caching, auto-refresh on expiry)
- `app/Http/Controllers/API/MarketplaceController.php` — Update placeOrder to book Fez delivery
- `database/migrations/` — Add `fez_order_no`, `delivery_status`, `delivery_cost` to marketplace_orders

---

## 9. COMPLETE ENDPOINT SUMMARY

| # | Endpoint | Method | Purpose |
|---|----------|--------|---------|
| 1 | `/user/authenticate` | POST | Login, get authToken + secret-key |
| 2 | `/user/logout` | POST | Invalidate session |
| 3 | `/user/changePassword` | POST | Change password |
| 4 | `/order` | POST | Create order (book delivery) |
| 5 | `/orders/{orderNo}` | GET | Get order details |
| 6 | `/order` (PUT) | PUT | Update order(s) |
| 7 | `/order` (DELETE) | DELETE | Delete order |
| 8 | `/orders/search` | POST | Search orders with filters + pagination |
| 9 | `/orders/search/{waybillNumber}` | GET | Search by waybill number |
| 10 | `/orders/statsWithDateRange` | POST | Order stats by status |
| 11 | `/order/cost` | POST | Fetch delivery cost (dynamic pricing) |
| 12 | `/order/track/{orderNumber}` | GET | Track order + delivery timeline |
| 13 | `/delivery-time-estimate` | POST | Get delivery ETA |
| 14 | `/states` | GET | List all 36 states + FCT |
| 15 | `/hubs/{stateId}` | GET | Get hubs for a state |

---

## 10. REMAINING ENDPOINTS TO DOCUMENT
(If any additional endpoints are discovered)

- Webhook/callback for delivery status updates (if available)
- Any other utility endpoints

**✅ THIS DOCUMENT IS NOW COMPLETE — All provided endpoints documented**

**LAST UPDATED**: March 19, 2026
