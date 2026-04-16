# Nigerian Network Prefixes (2026 Updated)

## MTN Nigeria
```
0702, 0703, 0704, 0706, 0707
0803, 0806, 0810, 0813, 0814, 0816
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

## Key Changes Made (Latest Update):
1. **MTN prefixes corrected**: Removed 0708, 0713, 0716, 0802, 0804 (these were incorrect)
2. **0707 confirmed as MTN** (not GLO)
3. **0708 confirmed as Airtel** (not MTN)
4. **0802 confirmed as Airtel** (not MTN)
5. All prefixes now match official 2026 Nigerian network allocations

## Files Updated:
- `app/Http/Controllers/Purchase/AirtimePurchase.php`
- `app/Http/Controllers/Purchase/DataPurchase.php`
- `app/Http/Controllers/Purchase/AirtimeCash.php`

## Note:
Some prefixes like 0708 appear in multiple networks due to number portability. The system validates based on the network selected by the user.
