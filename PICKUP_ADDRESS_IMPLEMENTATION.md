# Pickup Address Implementation for Marketplace Orders

## Overview
Added support for storing and displaying pickup/sender address information from Fez Delivery API.

---

## Changes Made

### 1. Database Migration
**File**: `database/migrations/2026_04_11_000001_add_pickup_address_to_marketplace_orders.php`

Added 3 new columns to `marketplace_orders` table:
- `pickup_name` VARCHAR(255) - Sender/warehouse name
- `pickup_address` TEXT - Full pickup address
- `pickup_phone` VARCHAR(20) - Pickup contact number

### 2. Model Update
**File**: `app/Models/MarketplaceOrder.php`

Added new fields to `$fillable` array:
```php
'pickup_name', 'pickup_address', 'pickup_phone'
```

### 3. Controller Updates
**File**: `app/Http/Controllers/API/MarketplaceController.php`

#### Updated Methods:

**a) `trackOrder()` - User tracking endpoint**
- Now saves pickup address from Fez response when tracking
- Returns `pickup_name` and `pickup_address` in API response

**b) `adminTrackOrder()` - Admin tracking endpoint**
- Same updates as user endpoint
- Admin can see pickup address in tracking details

**Logic Added:**
```php
// Save pickup address from Fez response
if (isset($tracking['order']['senderName']) || isset($tracking['order']['senderAddress'])) {
    $order->update([
        'pickup_name' => $tracking['order']['senderName'] ?? null,
        'pickup_address' => $tracking['order']['senderAddress'] ?? null,
    ]);
}
```

---

## How It Works

### Flow:
1. **Order Created** → Fez delivery booked → `fez_order_no` saved
2. **User Tracks Order** → App calls `/api/marketplace/orders/{reference}/track`
3. **Backend Fetches Fez Data** → Calls Fez `/order/track/{orderNo}`
4. **Fez Returns**:
   ```json
   {
     "order": {
       "senderName": "KIDS PLACE",
       "senderAddress": "3A SULAIMON SHODERU STREET, HARUNA BUS STOP, IKORODU",
       "recipientName": "John Doe",
       "recipientAddress": "10 Allen Avenue, Ikeja"
     }
   }
   ```
5. **Backend Saves** → `pickup_name` and `pickup_address` stored in database
6. **Backend Returns** → Pickup address included in tracking response
7. **Mobile App Shows** → Customer sees both pickup and delivery addresses

---

## API Response Format

### Before (Old):
```json
{
  "status": "success",
  "data": {
    "order_status": "processing",
    "delivery_status": "Dispatched",
    "fez_order_no": "JHAZ27012319",
    "timeline": [...]
  }
}
```

### After (New):
```json
{
  "status": "success",
  "data": {
    "order_status": "processing",
    "delivery_status": "Dispatched",
    "fez_order_no": "JHAZ27012319",
    "pickup_name": "KIDS PLACE",
    "pickup_address": "3A SULAIMON SHODERU STREET, HARUNA BUS STOP, IKORODU",
    "timeline": [...]
  }
}
```

---

## Deployment Steps

### On Live Server:

1. **Pull latest code**:
   ```bash
   git pull origin master
   ```

2. **Run migration**:
   ```bash
   php artisan migrate --force
   ```

3. **Clear caches**:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   ```

4. **Verify columns exist**:
   ```sql
   DESCRIBE marketplace_orders;
   ```
   Should show: `pickup_name`, `pickup_address`, `pickup_phone`

---

## Mobile App Integration (Next Steps)

### Update Order Detail Screen
**File**: `Vendlike Mobile/lib/modules/marketplace/screens/order_detail_screen.dart` (or similar)

Add pickup address display:
```dart
// Pickup Information Section
if (order['pickup_address'] != null) {
  _buildInfoCard(
    title: 'Pickup Location',
    icon: Icons.store_outlined,
    children: [
      if (order['pickup_name'] != null)
        _buildInfoRow('Warehouse', order['pickup_name']),
      _buildInfoRow('Address', order['pickup_address']),
    ],
  ),
}

// Delivery Information Section
_buildInfoCard(
  title: 'Delivery Location',
  icon: Icons.location_on_outlined,
  children: [
    _buildInfoRow('Name', order['delivery_name']),
    _buildInfoRow('Phone', order['delivery_phone']),
    _buildInfoRow('Address', order['delivery_address']),
  ],
),
```

### Update Tracking Timeline
Show pickup address at the top of the delivery timeline so customers know where their package is coming from.

---

## Benefits

1. **Transparency** - Customers see full delivery journey (pickup → delivery)
2. **Trust** - Knowing the warehouse location builds confidence
3. **Support** - Easier to resolve delivery issues when pickup location is known
4. **Tracking** - Complete visibility of package movement

---

## Notes

- Pickup address is fetched from Fez on first tracking call
- Data is cached in database for faster subsequent loads
- If Fez doesn't return pickup address, fields remain NULL (no errors)
- Existing orders without pickup address will get it populated on next tracking call

---

## Testing

### Test Scenario:
1. Place a new marketplace order
2. Wait for Fez to assign pickup location
3. Track the order via mobile app
4. Verify pickup address appears in order details
5. Check database to confirm data is saved

### SQL Query to Check:
```sql
SELECT 
  reference, 
  fez_order_no, 
  pickup_name, 
  pickup_address, 
  delivery_address 
FROM marketplace_orders 
WHERE fez_order_no IS NOT NULL 
ORDER BY created_at DESC 
LIMIT 10;
```

---

**Status**: ✅ Backend implementation complete
**Next**: Mobile app UI updates (NOT DONE YET - remember you don't push mobile changes)

**Last Updated**: April 11, 2026
