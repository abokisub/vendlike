# Vendlike Issues & Corrections Tracker

**Date**: April 20, 2026  
**Status**: In Progress

---

## PRIORITY 1: CRITICAL BUGS (Fix First)

### ✅ Issue #1: Biometrics Enabling Using Wrong PIN
**Status**: ✅ **FIXED** (Not Pushed)  
**Priority**: HIGH  
**Description**: User could enable biometrics with incorrect PIN  
**Impact**: Security vulnerability  
**Fixed**: April 20, 2026  
**Details**: Updated biometrics settings screen to properly verify PIN with backend before enabling biometrics. Only enables if PIN is successfully verified.  
**Files**: `Vendlike Mobile/lib/modules/profile/screens/biometrics_settings_screen.dart`  
**Note**: Flutter changes NOT pushed per user request (backend only push)

---

### ⏳ Issue #2: Push Notifications Not Working on iOS
**Status**: 🟡 **BACKEND FIXED - NEEDS APNs KEY UPLOAD**  
**Priority**: HIGH  
**Description**: Push notifications stopped working on iOS (possibly after hosting upgrade to Stellar Plus)  
**Impact**: Users don't receive important notifications  
**Backend Status**: ✅ Already fixed (APNs configuration added to FirebaseService)  
**Action Required**: Upload APNs Authentication Key to Firebase Console  
**Documentation**: `IOS_PUSH_NOTIFICATIONS_FIX.md`

---

## PRIORITY 2: ALREADY FIXED ✅

### ✅ Issue #3: 0707 Detected as GLO Instead of MTN
**Status**: ✅ **ALREADY FIXED**  
**Fixed**: April 16, 2026  
**Commit**: b4aa563  
**Details**: Updated all Nigerian network prefixes to 2026 accurate list  
**Files Updated**:
- `app/Http/Controllers/Purchase/AirtimePurchase.php`
- `app/Http/Controllers/Purchase/DataPurchase.php`
- `app/Http/Controllers/Purchase/AirtimeCash.php`
- `NIGERIAN_NETWORK_PREFIXES_2026.md`

**Correct Prefixes Now**:
- MTN: 0702, 0703, 0704, 0706, 0707, 0803, 0806, 0810, 0813, 0814, 0816, 0903, 0906, 0913, 0916
- GLO: 0705, 0715, 0805, 0807, 0811, 0815, 0905, 0915
- AIRTEL: 0701, 0708, 0802, 0808, 0812, 0901, 0902, 0904, 0907, 0912
- 9MOBILE: 0809, 0817, 0818, 0909, 0908

---

### ✅ Issue #4: Vendor Pickup Address for FEZ Delivery
**Status**: ✅ **ALREADY FIXED**  
**Fixed**: April 16, 2026  
**Commit**: 7f9a84a  
**Details**: Added pickup address fields to vendors table  
**Migration**: `2026_04_16_172415_add_pickup_address_to_marketplace_vendors_table.php`  
**Fields Added**:
- `pickup_address` (required)
- `pickup_city` (optional)
- `pickup_state` (required)
- `pickup_phone` (optional)

**Documentation**: `PICKUP_ADDRESS_IMPLEMENTATION.md`

---

### ✅ Issue #5: App Logo Update for iOS App Store
**Status**: ✅ **ALREADY DONE** (Not Pushed)  
**Fixed**: April 16, 2026  
**Details**: New logo.png uploaded and all icon sizes generated  
**Action**: Icons generated but NOT pushed to GitHub per your request  
**Note**: SMD will update when ready  
**Files Ready**:
- `Vendlike Mobile/assets/images/logo.png`
- All iOS icon sizes generated
- All Android icon sizes generated

---

### ✅ Issue #6: Rebrand "Aboki" and "Kobopoint" to "Vendlike"
**Status**: ✅ **ALREADY FIXED** (Backend Only)  
**Fixed**: April 16, 2026  
**Commit**: eba9d7d  
**Details**: Changed "Aboki AI" to "Vendlike AI" in support chat  
**Files Updated**:
- `app/Http/Controllers/API/SupportController.php`
- `VENDLIKE_AI_TRAINING.md`
- Frontend files (NOT pushed per your request)

**Note**: Frontend React and Flutter changes ready but not pushed

---

## PRIORITY 3: NEW FEATURES TO ADD

### ✅ Issue #7: Add Internal Transfer Lock/Disable Feature
**Status**: ✅ **COMPLETE** (Backend Pushed, Flutter Ready)  
**Priority**: MEDIUM  
**Description**: "From admin dashboard react we should add internal Transfer lock. Ability to disable vendlike to Vendlike fund transfer...I no be bank."  
**Fixed**: April 20, 2026  
**Commits**: 4b8432f (backend), Current (Flutter fix)  
**Details**: 
- Added `internal_transfer_enabled` column to `settings` table (default: true)
- Created API endpoint: `POST /secure/toggle/internal/transfer/{id}/habukhan/secure`
- Updated `InternalTransferController` to check setting before processing transfers
- Updated `getTransferSettings()` to include `internal_transfer_enabled`
- Migration ran successfully
- **NEW**: Flutter app now checks setting and hides "Vendlike Account" option when disabled

**Backend Status**: ✅ Complete and pushed  
**Frontend Status**: ⏳ Needs UI in admin dashboard to toggle setting  
**Flutter Status**: ✅ Complete - conditionally shows/hides internal transfer option based on setting

**Files Modified**:
- `database/migrations/2026_04_20_090234_add_internal_transfer_enabled_to_settings.php` (NEW)
- `app/Http/Controllers/Purchase/InternalTransferController.php` (PUSHED)
- `app/Http/Controllers/API/AdminController.php` (PUSHED)
- `routes/api.php` (PUSHED)
- `Vendlike Mobile/lib/modules/transactions/screens/send_money_options_screen.dart` (NOT PUSHED)

**Documentation**: `INTERNAL_TRANSFER_LOCK_FIX.md`

---

### 🟡 Issue #8: Gift Card Countries - Add "Select All" / "Unselect All"
**Status**: 🟡 NEEDS IMPLEMENTATION  
**Priority**: MEDIUM  
**Description**: "When creating gift cards for users to select from when they want to sell, I should be able to select all countries instead of selecting the countries one by one me as an admin please. I should also be able to unselect all"  
**Current**: Admin must click 66 countries individually  
**Required**: Add two buttons:
- "Select All Countries" button
- "Unselect All Countries" button

**Files to Modify**:
- `frontend/src/pages/admin/NewGiftCard.js` - Add select all/unselect all buttons
- `frontend/src/pages/admin/GiftCards.js` - Same for edit mode

---

### ✅ Issue #9: Kobopoint Logo Still Showing in Emails
**Status**: ✅ **FIXED** (Backend Already Fixed, Frontend Not Pushed)  
**Priority**: HIGH  
**Description**: "Kobopoint logo still showing in email notification for gift card to Admin and customer. Please confirm all emails only carry Vendlike logo."  
**Fixed**: April 16, 2026 (Backend), April 20, 2026 (Frontend)  
**Details**: 
- Backend email templates already use `logo.png` (fixed April 16)
- Frontend invoice/receipt files updated to use `logo.png` (April 20)
- Replaced ALL `welcome.png` references across 27 frontend files

**Backend Status**: ✅ Already fixed (commit: 5524d82)  
**Frontend Status**: ✅ Fixed but NOT pushed per user request

**Files Updated (Frontend - Not Pushed)**:
- All invoice PDFs (airtime, data, cable, bills, transfers, etc.)
- All transaction receipts
- Gift card invoices
- Marketplace invoices
- 27 files total

---

### ✅ Issue #10: Kobopoint Logo in Transaction Receipt (Web)
**Status**: ✅ **FIXED** (Not Pushed)  
**Priority**: HIGH  
**Description**: "Kobopoint logo still showing in transaction receipt on web"  
**Fixed**: April 20, 2026  
**Details**: Same fix as Issue #9 - all frontend files now use `logo.png`  
**Status**: ✅ Fixed but NOT pushed per user request

---

### 🟡 Issue #11: Marketplace Delivery Address Search/Scroll Improvement
**Status**: 🟡 NEEDS IMPLEMENTATION  
**Priority**: MEDIUM  
**Description**: "During shopping, to input delivery address, user should be able to search and scroll through states, just as you did for list of banks, instead of just scrolling through the list of state. Same for local government list...there should be a search that suggests as you type in search bar or u just scroll if u want."  
**Current**: Users must scroll through long lists without search  
**Required**: 
- Add search functionality to state dropdown (like bank selection)
- Add search functionality to LGA dropdown
- Autocomplete suggestions as user types

**Files to Modify**:
- `Vendlike Mobile/lib/modules/marketplace/screens/cart_sheet.dart`
- `Vendlike Mobile/lib/data/nigeria_locations.dart`

---

### ✅ Issue #12: Internal Transfer Lock Not Working in App
**Status**: ✅ **FIXED** (Not Pushed)  
**Priority**: HIGH  
**Description**: "i lock the internel transfer but the App still letting me to click an user the internel transfer"  
**Fixed**: April 20, 2026  
**Root Cause**: Flutter app was not checking `internal_transfer_enabled` setting before showing the "Vendlike Account" option  
**Solution**: 
- Updated `send_money_options_screen.dart` to read setting from `AuthService.appSettings`
- Conditionally show/hide "Vendlike Account" option based on setting
- Defaults to enabled (true) if setting not found for backward compatibility
- Backend already properly checks setting and returns 403 if disabled (double protection)

**How It Works**:
1. Admin disables internal transfer in admin panel
2. Backend updates `settings.internal_transfer_enabled` to 0
3. Flutter app refreshes via APPLOAD endpoint (every 25 seconds)
4. Send Money screen hides "Vendlike Account" option
5. If user bypasses UI, backend returns 403 error

**Files Modified**:
- `Vendlike Mobile/lib/modules/transactions/screens/send_money_options_screen.dart` (NOT PUSHED)

**Documentation**: `INTERNAL_TRANSFER_LOCK_FIX.md`

---

## SUMMARY

**Total Issues**: 12  
**Critical Bugs Fixed**: 2 (Biometrics ✅, iOS Push Notifications ✅ backend)  
**Already Fixed**: 8 (Network prefix, Vendor address, Logo, Rebranding, AI, Vendor notifications, Internal transfer lock backend, Internal transfer lock Flutter)  
**Need Implementation**: 2 (Gift card select all, Marketplace address search)  
**Needs Action**: 1 (iOS APNs key upload)

---

## ACTION PLAN

### Completed Today (April 20):
1. ✅ Fixed biometrics PIN verification bug (security critical)
2. ✅ Replaced all Kobopoint logos with Vendlike logo (27 files)
3. ✅ Implemented internal transfer lock feature (backend complete)
4. ✅ Pushed backend changes to GitHub (commit: 4b8432f)

### Pending Implementation:
1. 🟡 Gift card "Select All Countries" feature (frontend)
2. 🟡 Marketplace delivery address search (Flutter)
3. 🟡 Internal transfer lock UI in admin dashboard (frontend)

### Pending Deployment:
- Flutter biometrics fix (NOT pushed per user request)
- Frontend logo changes (NOT pushed per user request)

### Pending Action:
- ⏳ Upload APNs key to Firebase Console for iOS push notifications

---

## NEXT STEPS

**What should I do next?**

1. **Implement Gift Card "Select All Countries"** (frontend feature)
2. **Implement Marketplace Address Search** (Flutter feature)
3. **Create Internal Transfer Lock UI** (admin dashboard)
4. **Push Flutter and Frontend changes** (when user approves)

**Or should I:**
- Work on something else?
- Deploy the frontend/Flutter changes now?

**Please tell me which feature to implement next!** 🎯

---

**Last Updated**: April 20, 2026
