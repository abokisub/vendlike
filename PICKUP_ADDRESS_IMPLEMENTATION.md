# Vendor Pickup Address Implementation

## Overview
Every vendor registered on Vendlike now has a pickup address that will be included in the FEZ Delivery API payload when orders are placed. This ensures FEZ knows where to collect items from each vendor for delivery to customers.

## Changes Made

### 1. Database Migration
**File**: `database/migrations/2026_04_16_172415_add_pickup_address_to_marketplace_vendors_table.php`

Added the following fields to `marketplace_vendors` table:
- `pickup_address` (text, nullable) - Full pickup address
- `pickup_city` (string, nullable) - Pickup city
- `pickup_state` (string, nullable) - Pickup state (required for FEZ)
- `pickup_phone` (string, nullable) - Contact phone for pickup coordination

### 2. Model Update
**File**: `app/Models/MarketplaceVendor.php`

Updated `$fillable` array to include:
```php
'pickup_address', 'pickup_city', 'pickup_state', 'pickup_phone'
```

### 3. Backend API Updates
**File**: `app/Http/Controllers/API/MarketplaceController.php`

#### adminCreateVendor()
- Added validation for pickup address fields
- `pickup_address` and `pickup_state` are **required**
- `pickup_city` and `pickup_phone` are optional but recommended

#### adminUpdateVendor()
- Added support for updating pickup address fields
- All pickup fields can be updated independently

### 4. Frontend Admin Panel Updates
**File**: `frontend/src/pages/admin/MarketPlace.js`

#### Vendor Form State
- Added pickup address fields to `vendorForm` state
- Fields: `pickup_address`, `pickup_city`, `pickup_state`, `pickup_phone`

#### Vendor Dialog UI
- Expanded dialog from `maxWidth="sm"` to `maxWidth="md"` for better layout
- Added new section: "Pickup Address (For FEZ Delivery)"
- Added 4 new form fields:
  1. **Pickup Address** (multiline, required) - Full address where FEZ will collect items
  2. **Pickup City** (text, required)
  3. **Pickup State** (text, required) - Must match FEZ state names
  4. **Pickup Contact Phone** (text, required) - For pickup coordination

#### Form Validation
- Save button is disabled if:
  - `name` is empty
  - `pickup_address` is empty
  - `pickup_state` is empty

## FEZ Delivery Integration

### How Pickup Address Will Be Used

When an order is placed, the system will:

1. **Get vendor from product** → Each product has a `vendor_id`
2. **Retrieve vendor pickup address** → From `marketplace_vendors` table
3. **Send to FEZ API** → Include in `POST /order` payload:

```php
[
    'recipientAddress' => $order->delivery_address,
    'recipientState' => $order->delivery_state,
    'recipientName' => $order->delivery_name,
    'recipientPhone' => $order->delivery_phone,
    'pickUpAddress' => $vendor->pickup_address,  // ✅ Vendor pickup address
    'pickUpState' => $vendor->pickup_state,      // ✅ Vendor pickup state
    'uniqueID' => $order->reference,
    'BatchID' => 'BATCH_' . date('Ymd'),
    'valueOfItem' => (string) $order->total_amount,
    'weight' => $product->weight ?? 1,
]
```

### FEZ API Fields Mapping

| Vendlike Field | FEZ API Field | Required | Description |
|----------------|---------------|----------|-------------|
| `vendor->pickup_address` | `pickUpAddress` | No* | Pickup location (defaults to org address if omitted) |
| `vendor->pickup_state` | `pickUpState` | No* | Pickup state (defaults to org state if omitted) |
| `vendor->pickup_phone` | N/A | No | For internal coordination only |
| `vendor->pickup_city` | N/A | No | For internal reference only |

*While FEZ marks these as optional (defaults to org address), we make them **required** in Vendlike because each vendor has their own location.

### State Name Validation

The `pickup_state` must match one of the 37 FEZ-supported states:
- 36 Nigerian states + FCT
- Examples: "Lagos", "Kano", "Rivers", "FCT"
- See `FEZ_DELIVERY_API_REFERENCE.md` section 5.2 for full list

## Migration Instructions

### On Production Server

1. **Run migration**:
```bash
php artisan migrate
```

2. **Update existing vendors**:
```bash
php artisan tinker
```
```php
// Check vendors without pickup address
DB::table('marketplace_vendors')->whereNull('pickup_address')->get();

// Update manually if needed
DB::table('marketplace_vendors')->where('id', 1)->update([
    'pickup_address' => '123 Main Street, Ikeja',
    'pickup_city' => 'Ikeja',
    'pickup_state' => 'Lagos',
    'pickup_phone' => '08012345678'
]);
```

3. **Clear cache**:
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

## Admin User Instructions

### Adding a New Vendor

1. Go to **Admin Panel** → **Plan** → **Market Place**
2. Click **Vendors** tab
3. Click **Add Vendor** button
4. Fill in vendor information:
   - Contact Name (required)
   - Business Name
   - Phone
   - Email
   - Description

5. **Fill in Pickup Address** (required for FEZ delivery):
   - **Pickup Address**: Full address where FEZ will collect items
     - Example: "Shop 45, Balogun Market, Lagos Island"
   - **Pickup City**: City name
     - Example: "Lagos Island"
   - **Pickup State**: Must be valid Nigerian state
     - Example: "Lagos"
   - **Pickup Contact Phone**: Phone for pickup coordination
     - Example: "08012345678"

6. Click **Create**

### Editing Existing Vendor

1. Click the **Edit** icon (pencil) next to the vendor
2. Update any fields including pickup address
3. Click **Update**

### Important Notes

- **Pickup State** must match FEZ state names exactly (case-sensitive)
- **Pickup Address** should be detailed enough for FEZ riders to locate
- **Pickup Phone** should be reachable during business hours
- Vendors without pickup address cannot have their products delivered via FEZ

## Testing Checklist

- [ ] Migration runs successfully
- [ ] Can create new vendor with pickup address
- [ ] Can update existing vendor's pickup address
- [ ] Form validation works (required fields)
- [ ] Pickup address appears in vendor list
- [ ] FEZ API receives correct pickup address in order payload
- [ ] Orders with multiple vendors from different locations work correctly

## Future Enhancements

1. **State Dropdown**: Replace text input with dropdown of 37 FEZ states
2. **Address Validation**: Integrate Google Maps API for address verification
3. **Multiple Pickup Locations**: Allow vendors to have multiple pickup addresses
4. **Pickup Schedule**: Add vendor operating hours for pickup coordination
5. **Vendor Dashboard**: Allow vendors to manage their own pickup addresses

## Related Files

- `FEZ_DELIVERY_API_REFERENCE.md` - FEZ API documentation
- `app/Services/FezDeliveryService.php` - FEZ API client (to be created)
- `app/Http/Controllers/API/MarketplaceController.php` - Marketplace API
- `database/migrations/2024_03_18_000001_create_marketplace_tables.php` - Original marketplace tables

## Status

- ✅ Database migration created
- ✅ Model updated
- ✅ Backend API updated
- ✅ Frontend admin panel updated
- ✅ Vendor email notifications added
- ⏳ Migration needs to run on production
- ⏳ FEZ integration needs to use pickup address in order creation
- ⏳ Testing required

## Vendor Email Notifications

Vendors now receive email notifications at three key stages:

### 1. New Order (Order Confirmed/Paid)
**Template**: `resources/views/email/vendor_new_order.blade.php`
**Trigger**: When customer payment is confirmed
**Purpose**: Notify vendor to prepare items for FEZ pickup
**Contains**:
- Order reference
- List of items to prepare (with quantities, sizes, colors)
- Vendor's pickup address
- Customer delivery details
- Action required message

### 2. Order Shipped (FEZ Picked Up)
**Template**: `resources/views/email/vendor_order_shipped.blade.php`
**Trigger**: When admin marks order as "shipped"
**Purpose**: Confirm FEZ has collected the items
**Contains**:
- Order reference
- Tracking number (if available)
- List of items shipped
- Pickup date/time
- Transit status message

### 3. Order Delivered
**Template**: `resources/views/email/vendor_order_delivered.blade.php`
**Trigger**: When admin marks order as "delivered"
**Purpose**: Confirm successful delivery to customer
**Contains**:
- Order reference
- Delivery date/time
- List of items delivered
- Customer name and location
- Success/thank you message

### Email Notification Flow

```
Customer Places Order → Payment Confirmed
    ↓
    ├─→ Customer: Order Confirmed Email (with invoice PDF)
    ├─→ Admin: Push Notification
    └─→ Vendor: New Order Email ✅ (prepare for pickup)

Admin Marks as "Shipped" (FEZ picked up)
    ↓
    ├─→ Customer: Order Shipped Email (with tracking)
    └─→ Vendor: Order Shipped Email ✅ (items in transit)

Admin Marks as "Delivered"
    ↓
    ├─→ Customer: Order Delivered Email
    └─→ Vendor: Order Delivered Email ✅ (order complete)
```

### Important Notes

- Vendors must have a valid email address in their profile to receive notifications
- If an order contains products from multiple vendors, each vendor receives their own email with only their items
- Email sending is non-blocking (wrapped in try-catch) to prevent order processing failures
- All vendor emails are logged for debugging purposes

**Last Updated**: April 16, 2026
