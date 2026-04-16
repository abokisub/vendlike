# Laravel Root Cleanup - April 16, 2026

## Files Removed:

### 1. Temporary Fix Scripts
- `fix_customer.php` - One-time XixaPay customer fix for user ID 20

### 2. Development Tools
- `postman.json` - Postman API collection (moved to docs if needed)

### 3. Debug/Test Scripts Folder
- `scripts_backup/` - Contains 60+ old debug/test scripts from development

## Files Kept:

### Core Laravel Files
- `server.php` - Laravel built-in server
- `artisan` - Laravel CLI
- `composer.json/lock` - PHP dependencies
- `package.json/lock` - Node dependencies
- `webpack.mix.js` - Frontend build config

### Documentation (All kept)
- `COMPLETE_PROJECT_SUMMARY.md`
- `FEZ_DELIVERY_API_REFERENCE.md`
- `NIGERIAN_NETWORK_PREFIXES_2026.md`
- `PICKUP_ADDRESS_IMPLEMENTATION.md`
- `RELOADLY_GIFTCARD_API_REFERENCE.md`
- `SUDO_AFRICA_API_REFERENCE.md`
- `TIER_SYSTEM_EXPLAINED.md`

## Recommendation:
Move `postman.json` to `docs/` folder before deleting from root.
