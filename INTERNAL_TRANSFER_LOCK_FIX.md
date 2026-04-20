# Internal Transfer Lock Fix - April 20, 2026

## Issue
User reported that after disabling "Internal Transfer (Vendlike to Vendlike)" in the admin panel Other Lock page, the Flutter app still showed the "Vendlike Account" transfer option and allowed users to click it.

## Root Cause
The Flutter app was not checking the `internal_transfer_enabled` setting before displaying the internal transfer option. The option was hardcoded to always show in `send_money_options_screen.dart`.

## Solution

### Backend (Already Working)
The backend was already properly configured:
- ✅ `internal_transfer_enabled` column exists in `settings` table (migration: `2026_04_20_090234_add_internal_transfer_enabled_to_settings.php`)
- ✅ `InternalTransferController` checks the setting and returns 403 error if disabled
- ✅ `APPLOAD` endpoint (`/app/secure/check/login/details`) returns ALL settings including `internal_transfer_enabled`
- ✅ Admin panel can toggle the setting via `/secure/toggle/internal/transfer/{id}/habukhan/secure`

### Flutter App Fix
Updated `send_money_options_screen.dart` to:
1. Import `Provider` and `AuthService` to access app settings
2. Read `internal_transfer_enabled` from `authService.appSettings`
3. Conditionally show/hide the "Vendlike Account" option based on the setting
4. Default to `true` (enabled) if setting is not found for backward compatibility

## Code Changes

### File: `Vendlike Mobile/lib/modules/transactions/screens/send_money_options_screen.dart`

**Added imports:**
```dart
import 'package:provider/provider.dart';
import '../../../services/auth_service.dart';
```

**Added setting check in build method:**
```dart
final authService = Provider.of<AuthService>(context);
final settings = authService.appSettings;

// Check if internal transfer is enabled (default to true if setting not found)
final internalTransferEnabled = settings?['internal_transfer_enabled'] == 1 || 
                               settings?['internal_transfer_enabled'] == '1' || 
                               settings?['internal_transfer_enabled'] == true ||
                               settings?['internal_transfer_enabled'] == null;
```

**Wrapped Vendlike Account option with conditional:**
```dart
// Only show Vendlike Account option if internal transfer is enabled
if (internalTransferEnabled) ...[
  _buildOptionCard(
    context,
    imagePath: 'assets/images/logo.png',
    title: 'Vendlike Account',
    subtitle: 'Transfer money to other Vendlike users',
    color: AppColors.primary,
    delay: 100,
    onTap: () => context.push('/internal-transfer'),
  ),
  
  const SizedBox(height: 16),
],
```

## How It Works

1. **Admin disables internal transfer** in admin panel (Other Lock page)
2. **Backend updates** `settings.internal_transfer_enabled` to `0`
3. **Flutter app refreshes** user data via `APPLOAD` endpoint
4. **AuthService stores** settings in `appSettings` map
5. **Send Money screen** reads the setting and hides "Vendlike Account" option
6. **If user tries direct API call**, backend returns 403 error (double protection)

## Testing

### Test Case 1: Internal Transfer Enabled (Default)
1. Admin panel: Internal Transfer is ON
2. Flutter app: "Vendlike Account" option is visible
3. User can click and proceed to internal transfer screen
4. Backend processes the transfer successfully

### Test Case 2: Internal Transfer Disabled
1. Admin panel: Internal Transfer is OFF
2. Flutter app: "Vendlike Account" option is hidden
3. Only "Other Banks" and "Crossboarder Transfer" options are visible
4. If user somehow bypasses UI, backend returns 403 error

## Files Modified

### Flutter (NOT PUSHED - Ready for Testing)
- `Vendlike Mobile/lib/modules/transactions/screens/send_money_options_screen.dart`

### Backend (Already Pushed in Previous Commits)
- `database/migrations/2026_04_20_090234_add_internal_transfer_enabled_to_settings.php`
- `app/Http/Controllers/Purchase/InternalTransferController.php`
- `app/Http/Controllers/API/AdminController.php`

## API Reference

### Get Settings (APPLOAD)
**Endpoint:** `POST /api/app/secure/check/login/details`

**Response includes:**
```json
{
  "status": "success",
  "user": {...},
  "setting": {
    "internal_transfer_enabled": 1,
    "transfer_charge_type": "FLAT",
    "transfer_charge_value": 25,
    ...
  },
  "system_locks": [...]
}
```

### Toggle Internal Transfer (Admin Only)
**Endpoint:** `POST /api/secure/toggle/internal/transfer/{admin_id}/habukhan/secure`

**Request Body:**
```json
{
  "action": "enable"  // or "disable"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Internal Transfer Enabled"  // or "Disabled"
}
```

## Notes

- The setting defaults to `true` (enabled) for backward compatibility
- The Flutter app checks the setting on every screen load (reactive to changes)
- The backend provides double protection by checking the setting during API calls
- No app restart required - changes take effect after next user data refresh (every 25 seconds)

## Status
✅ **COMPLETED** - Flutter changes ready for testing (NOT pushed per user request)
