# VendLike Account Tier System

## Overview
VendLike uses a **3-tier account system** that determines transaction limits based on KYC (Know Your Customer) verification level.

---

## Tier Levels

### **Tier 0 - No KYC** (Default)
- **Badge Color**: Grey
- **Icon**: Lock
- **Status**: No verification submitted
- **Single Transaction Limit**: ₦0
- **Daily Transaction Limit**: ₦0
- **Features**: Cannot make bank transfers until KYC is completed

---

### **Tier 1 - Bronze Level** (NIN Verification)
- **Badge Color**: Bronze (#CD7F32)
- **Icon**: Shield
- **Verification Required**: National Identity Number (NIN)
- **Single Transaction Limit**: ₦50,000
- **Daily Transaction Limit**: ₦200,000
- **How to Upgrade**: Submit KYC with NIN + ID card + utility bill

---

### **Tier 2 - Silver Level** (BVN Verification)
- **Badge Color**: Silver (#C0C0C0)
- **Icon**: Verified User
- **Verification Required**: Bank Verification Number (BVN)
- **Single Transaction Limit**: ₦500,000
- **Daily Transaction Limit**: ₦2,000,000
- **How to Upgrade**: Submit KYC with BVN + ID card + utility bill

---

## How Users Move Between Tiers

### **Automatic Tier Assignment**
The tier is **automatically assigned** based on the ID type submitted during KYC:

1. **User submits KYC** through the app (Profile → Account Limits → Complete KYC)
2. **System checks ID type**:
   - If `id_type === 'bvn'` → Assign **Tier 2** (Silver)
   - If `id_type === 'nin'` → Assign **Tier 1** (Bronze)
3. **Admin reviews and approves** KYC submission
4. **Tier is activated** immediately after approval

### **Code Logic** (Backend)
Located in: `app/Http/Controllers/API/KYCController.php`

```php
private function getTierDataForIdType($idType)
{
    if ($idType === 'bvn') {
        return [
            'tier' => 'tier_2',
            'single_limit' => 500000.00,
            'daily_limit' => 2000000.00
        ];
    }
    
    // NIN or default
    return [
        'tier' => 'tier_1',
        'single_limit' => 50000.00,
        'daily_limit' => 200000.00
    ];
}
```

---

## Who Sets the Criteria?

### **Hardcoded in Backend**
The tier criteria are **hardcoded** in the `KYCController.php` file by the developer.

### **Admin Cannot Change Tiers**
- Admins can only **approve or reject** KYC submissions
- Admins **cannot manually change** tier levels or limits
- The system automatically assigns tiers based on ID type

### **To Change Tier Limits**
You must edit the code in `app/Http/Controllers/API/KYCController.php`:

```php
// Example: Increase Tier 1 limits
return [
    'tier' => 'tier_1',
    'single_limit' => 100000.00,  // Change from 50,000
    'daily_limit' => 500000.00     // Change from 200,000
];
```

---

## Visual Display

### **Mobile App**
- **Location**: Profile → Account Limits
- **Shows**:
  - Tier badge with color (Bronze/Silver)
  - Circular progress meter showing daily usage
  - Single transaction limit card
  - Daily transaction limit card
  - Upgrade CTA (if not max tier)

### **Dashboard Screen**
- Shows "Tier 1 (NIN)" badge next to user greeting
- Displays current tier status

---

## Database Storage

### **User Table Columns**
- `kyc_tier` - Stores tier level (tier_1, tier_2)
- `single_limit` - Maximum per transaction
- `daily_limit` - Maximum per day
- `kyc_status` - Verification status (pending, submitted, verified, rejected)

### **PointWave KYC Table**
- `tier` - Stores tier for PointWave provider
- `daily_limit` - Daily limit
- `transaction_limit` - Single transaction limit

---

## Summary

| Tier | Level | ID Type | Single Limit | Daily Limit | Badge Color |
|------|-------|---------|--------------|-------------|-------------|
| 0 | No KYC | None | ₦0 | ₦0 | Grey |
| 1 | Bronze | NIN | ₦50,000 | ₦200,000 | Bronze |
| 2 | Silver | BVN | ₦500,000 | ₦2,000,000 | Silver |

**Note**: There is no Tier 3 or Gold level in the current system. Only 3 tiers exist (0, 1, 2).
