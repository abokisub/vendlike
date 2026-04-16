# Nigerian Network Prefixes (2026 Updated)

## MTN Nigeria
```
0702, 0703, 0704, 0706, 0707, 0708, 0713, 0716
0802, 0803, 0804, 0806, 0810, 0813, 0814, 0816
0903, 0906, 0913, 0916
```

## GLO Nigeria
```
0705, 0715
0805, 0807, 0811, 0815
0905, 0915
```

## Airtel Nigeria
```
0701, 0708
0802, 0808, 0812
0901, 0902, 0904, 0907, 0912
```

## 9Mobile (Etisalat)
```
0809, 0817, 0818
0909, 0908
```

## Key Changes Made:
1. **0707 moved from GLO to MTN** (was incorrectly listed in both)
2. Removed duplicate/invalid prefixes
3. Cleaned up overlapping prefixes between networks
4. Updated to 2026 active prefixes only

## Files Updated:
- `app/Http/Controllers/Purchase/AirtimePurchase.php`
- `app/Http/Controllers/Purchase/DataPurchase.php`
- `app/Http/Controllers/Purchase/AirtimeCash.php`

## Note:
Some prefixes like 0708 appear in multiple networks due to number portability. The system validates based on the network selected by the user.
