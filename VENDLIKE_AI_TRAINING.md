# Vendlike AI Training Data - TRANSACTIONAL MODE

## Overview
This document contains the comprehensive training data for Vendlike AI assistant. The AI is designed to help users understand Vendlike services AND execute transactions on their behalf.

## AI Capabilities
- ✅ **Execute airtime purchases** (extracts phone & amount from natural language)
- ✅ **Check wallet balance** instantly
- ✅ **Show transaction history**
- ✅ Explain how services work
- ✅ Provide fee information
- ✅ Guide on account limits and KYC
- ✅ Answer general questions
- ❌ Cannot access sensitive account data
- ❌ Cannot modify account settings
- ❌ Cannot resolve disputes (escalates to human support)

## Transactional Features

### 1. Airtime Purchase (LIVE)
**User says:** "Buy 500 airtime for 08012345678"  
**AI does:** Extracts phone (08012345678) and amount (500), executes purchase immediately

**Supported patterns:**
- "Buy ₦500 airtime for 08012345678"
- "Purchase airtime 1000 for 07012345678"
- "Send 200 airtime to 09012345678"
- "Get airtime for 08012345678 amount 500"

**Smart extraction:**
- Phone: Detects Nigerian format (0701234567 - 0909999999)
- Amount: Extracts reasonable amounts (₦50 - ₦50,000)
- Network: Auto-detects from phone prefix

**Response:**
```
✅ Airtime purchase successful!

📱 Phone: 08012345678
💰 Amount: ₦500.00
🎯 Status: Delivered

Anything else I can help with?
```

### 2. Balance Check (LIVE)
**User says:** "Check my balance"  
**AI does:** Shows current wallet balance instantly

**Supported patterns:**
- "Check my balance"
- "What is my wallet balance"
- "Show balance"
- "How much do I have"

**Response:**
```
💰 Your Wallet Balance:

₦5,000.00

What would you like to do?
```

### 3. Transaction History (LIVE)
**User says:** "Show my transactions"  
**AI does:** Displays last 5 transactions with status

**Supported patterns:**
- "Show my transactions"
- "View transaction history"
- "My recent purchases"
- "Transaction history"

**Response:**
```
📋 Your Recent Transactions:

✅ Airtime Purchase - 08012345678
   ₦500.00 - Apr 16, 2026

✅ Data Bundle - 07012345678
   ₦1,000.00 - Apr 15, 2026

⏳ Marketplace Order - MP_001
   ₦5,000.00 - Apr 14, 2026

Need anything else?
```

### 4. Data Purchase (COMING SOON)
**User says:** "Buy 2GB data for 08012345678"  
**AI does:** Will extract phone and data size, execute purchase

**Currently:** AI acknowledges intent and guides user to manual process

## Service Categories

### 1. Wallet & Funding

**How to Fund Wallet:**
- Method 1: Bank Transfer (Free, Instant)
  - Get dedicated account number
  - Transfer from any bank
  - Funds reflect in 1-5 minutes
- Method 2: Card Payment (Free, Instant)
  - Pay with debit/credit card
  - Instant credit
- Minimum: ₦100
- Maximum: Based on KYC level
- Fee: ₦0 (Free!)

**Wallet Features:**
- Check balance anytime
- Transaction history
- Download statements
- Multiple funding options

### 2. Airtime Purchase

**How to Buy:**
1. Go to Services → Airtime
2. Select network (MTN, Glo, Airtel, 9mobile)
3. Enter phone number
4. Enter amount (₦50 - ₦50,000)
5. Confirm with PIN

**Features:**
- Instant delivery (1-5 seconds)
- Save beneficiaries
- Bulk purchase
- Discount rates for resellers

**Fees:**
- ₦50-₦500: Free
- ₦501-₦5,000: ₦5
- Above ₦5,000: ₦10

### 3. Data Bundles

**How to Buy:**
1. Go to Services → Data
2. Select network
3. Choose data plan (SME, Gifting, Corporate)
4. Enter phone number
5. Confirm purchase

**Popular Plans:**
- 1GB: ₦250-₦300
- 2GB: ₦500-₦600
- 5GB: ₦1,200-₦1,500
- 10GB: ₦2,400-₦3,000

**Features:**
- All networks supported
- Instant activation (1-5 mins)
- Multiple plan types
- Fee: ₦0-₦20

### 4. Airtime to Cash (A2Cash)

**How it Works:**
1. Go to A2Cash section
2. Select network
3. Enter amount to convert
4. Get OTP from network
5. Enter OTP to confirm
6. Cash credited to wallet

**Conversion Rates:**
- MTN: 80-85%
- Glo: 75-80%
- Airtel: 75-80%
- 9mobile: 70-75%

**Limits:**
- Minimum: ₦100
- Maximum: ₦50,000 per transaction
- Processing: 5-15 minutes

### 5. Bills Payment

**Electricity:**
- All DISCOs supported (EKEDC, IKEDC, AEDC, etc.)
- Prepaid & Postpaid
- Instant token delivery
- Fee: ₦50-₦100

**Cable TV:**
- DSTV (all packages)
- GOtv (all packages)
- Startimes
- Showmax
- Instant activation
- Fee: ₦50-₦100

**How to Pay:**
1. Go to Services → Bills
2. Select service type
3. Enter meter/smartcard number
4. Verify details
5. Confirm payment

### 6. Money Transfer

**To Other Banks:**
- Select bank from list
- Enter account number
- System verifies account name
- Enter amount and confirm
- Fee: ₦25-₦50
- Instant delivery

**Internal Transfer (Vendlike to Vendlike):**
- Enter recipient's username/phone
- Enter amount and confirm
- Fee: FREE! ₦0
- Instant delivery

**Transfer Limits:**
Based on KYC level

### 7. Gift Cards

**Buy Gift Cards:**
- Amazon, iTunes, Google Play, Steam, Xbox, PlayStation
- Choose denomination
- Instant code delivery
- Secure payment

**Sell Gift Cards:**
- Trade unused cards for cash
- Competitive rates
- Fast processing
- Upload card image
- Get instant quote

### 8. Marketplace

**How to Shop:**
1. Browse products by category
2. Add items to cart
3. Enter delivery address
4. Complete payment
5. Track your order

**Delivery:**
- Powered by FEZ Delivery
- All 36 states + FCT
- Real-time tracking
- Delivery fee at checkout

**Order Status:**
- Pending → Processing → Shipped → Delivered

### 9. KYC Levels & Limits

**Level 1 (Basic) - No verification:**
- Daily: ₦50,000
- Monthly: ₦200,000
- Withdrawal: ₦20,000/day

**Level 2 (Verified) - BVN + ID:**
- Daily: ₦200,000
- Monthly: ₦1,000,000
- Withdrawal: ₦100,000/day

**Level 3 (Premium) - Full KYC:**
- Daily: ₦1,000,000
- Monthly: ₦5,000,000
- Withdrawal: ₦500,000/day

**How to Upgrade:**
1. Go to Profile → KYC Verification
2. Upload required documents
3. Wait for approval (24-48 hours)

### 10. Security & PIN

**Transaction PIN:**
- 4-digit code
- Required for all transactions
- Keep it secret!

**Forgot PIN:**
1. Profile → Security → Forgot PIN
2. Verify with OTP
3. Set new PIN

**Change PIN:**
1. Profile → Security → Change PIN
2. Enter old PIN
3. Enter new PIN

**Security Tips:**
- Don't share PIN
- Use strong password
- Enable biometric login
- Log out on shared devices

### 11. Referral & Earning

**Referral Program:**
- Get referral code in Profile
- Share with friends
- Earn when they transact

**Reseller Program:**
- Buy at discounted rates
- Sell to customers
- Earn profit margins
- Upgrade to Agent/Reseller account

**Benefits:**
- Lower service fees
- Higher profit margins
- Bulk discounts
- Priority support

## Transaction Status Guide

**Status Types:**
- **Pending:** Processing (1-5 mins normal)
- **Success:** Completed ✅
- **Failed:** Refunded automatically

**What to Do:**
- Pending >30 mins: Contact support
- Failed but not refunded: Contact support
- Success but not received: Wait 15 mins, then contact support

## Service Fees Summary

| Service | Fee |
|---------|-----|
| Wallet Funding | Free |
| Airtime (₦50-₦500) | Free |
| Airtime (₦501-₦5,000) | ₦5 |
| Airtime (>₦5,000) | ₦10 |
| Data Bundles | ₦0-₦20 |
| Electricity | ₦50-₦100 |
| Cable TV | ₦50-₦100 |
| Internal Transfer | Free |
| External Transfer | ₦25-₦50 |
| Withdrawal | ₦25-₦50 |
| A2Cash Commission | 15-30% |

## Common Issues & Solutions

**Transaction Pending:**
- Normal processing time: 1-5 minutes
- If >30 minutes: Contact support

**Transaction Failed:**
- Money refunded automatically
- Check wallet balance
- If not refunded: Contact support

**Service Not Received:**
- Wait 15 minutes
- Check transaction status
- Contact support with reference

**Login Issues:**
- Reset password via email
- Clear app cache
- Update app to latest version

**PIN Issues:**
- Use Forgot PIN feature
- Verify with OTP
- Contact support if stuck

## When to Escalate to Human Support

**Account-Specific Issues:**
- Missing funds
- Wrong deduction
- Account locked
- KYC rejection

**Urgent Issues:**
- Fraud/scam report
- Security breach
- Large transaction issues

**Complex Queries:**
- Business partnerships
- Bulk pricing
- Custom solutions

## AI Response Guidelines

1. **Be Helpful:** Provide clear, actionable information
2. **Be Honest:** Admit when you can't help with account-specific issues
3. **Be Friendly:** Use emojis and warm language
4. **Be Concise:** Keep responses focused and scannable
5. **Offer Actions:** Provide quick action buttons
6. **Escalate When Needed:** Direct to human support for account issues

## Last Updated
April 16, 2026
