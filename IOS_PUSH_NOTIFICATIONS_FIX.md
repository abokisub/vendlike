# iOS Push Notifications Fix

## Issue
Push notifications were not working on iOS devices after server upgrade from Starter to Stellar Plus.

## Root Cause
The backend `FirebaseService.php` only had Android configuration (`AndroidConfig`) but was missing iOS/APNs configuration.

## Solution Applied ✅

### 1. Backend Fix (Already Completed)
Added APNs configuration to `app/Services/FirebaseService.php`:

```php
// iOS/APNs Configuration (CRITICAL for iOS notifications)
$apnsConfig = [
    'headers' => [
        'apns-priority' => '10', // High priority
    ],
    'payload' => [
        'aps' => [
            'alert' => [
                'title' => $title,
                'body' => $body,
            ],
            'sound' => 'default',
            'badge' => 1,
            'mutable-content' => 1, // Allows notification service extensions
        ],
    ],
];

// Add image to iOS if provided
if ($image && !$is_data_only) {
    $apnsConfig['payload']['aps']['alert']['image'] = $image;
    $apnsConfig['fcm_options'] = ['image' => $image];
}

$message = $message->withApnsConfig($apnsConfig);
```

This was added to both:
- `sendNotification()` method (single notifications)
- `sendMulticastNotification()` method (bulk notifications)

### 2. What You Still Need to Do

#### Upload APNs Authentication Key to Firebase Console

**Step 1: Get APNs Key from Apple Developer Portal**
1. Go to https://developer.apple.com/account
2. Sign in with your Apple Developer account
3. Navigate to: **Certificates, Identifiers & Profiles**
4. Click **Keys** in the sidebar
5. Click the **+** button to create a new key
6. Name it: "Vendlike Push Notifications"
7. Check: **Apple Push Notifications service (APNs)**
8. Click **Continue** → **Register** → **Download**
9. Save the `.p8` file (⚠️ You can only download it once!)
10. Note your **Key ID** (10 characters, shown after download)
11. Note your **Team ID** (top right corner of developer portal)

**Step 2: Upload to Firebase Console**
1. Go to https://console.firebase.google.com
2. Select project: **kobopoint-46cc2**
3. Click gear icon → **Project Settings**
4. Go to **Cloud Messaging** tab
5. Scroll to **Apple app configuration**
6. Under **APNs Authentication Key**, click **Upload**
7. Upload your `.p8` file
8. Enter your **Key ID** and **Team ID**
9. Click **Upload**

### 3. Verification

After uploading the APNs key, iOS notifications will work immediately. Test by:

1. **Send a test notification** from Firebase Console:
   - Go to Cloud Messaging → Send test message
   - Enter an iOS device token
   - Send notification

2. **Trigger a real notification**:
   - Make a transaction on the app
   - Fund wallet via PointWave
   - Check if notification appears on iOS device

### 4. Why It Wasn't Working

**Before Fix:**
```php
// Only Android config
$message = $message->withAndroidConfig($androidConfig);
$this->messaging->send($message);
```

**After Fix:**
```php
// Both Android AND iOS config
$message = $message->withAndroidConfig($androidConfig);
$message = $message->withApnsConfig($apnsConfig);  // ✅ Added
$this->messaging->send($message);
```

### 5. Files Modified

- ✅ `app/Services/FirebaseService.php` - Added APNs configuration
- ✅ Committed to Git (commit: c775467)
- ✅ Pushed to GitHub

### 6. Testing Without a Mac

You can still test iOS notifications without a Mac:

1. **Physical iOS Device**: Connect via USB to Windows with iTunes
2. **TestFlight**: Use for beta testing
3. **Firebase Console**: Send test notifications directly
4. **Ask iOS Users**: Have them test and report back

### 7. Common Issues & Solutions

**Issue**: Notifications still not working after uploading APNs key
**Solution**: 
- Verify the `.p8` file is correct
- Check Key ID and Team ID are accurate
- Ensure iOS app bundle ID matches: `com.vendlike.mobile`
- Rebuild and reinstall the iOS app

**Issue**: "Invalid APNs credentials" error
**Solution**:
- Re-download APNs key from Apple Developer Portal
- Upload again to Firebase Console
- Wait 5-10 minutes for propagation

**Issue**: Android works but iOS doesn't
**Solution**:
- This was the exact issue - APNs config was missing
- Already fixed in the code
- Just need to upload APNs key to Firebase

## Status

- ✅ Backend code fixed and deployed
- ⏳ Waiting for APNs key upload to Firebase Console
- ⏳ Testing on iOS devices

## Next Steps

1. Upload APNs Authentication Key to Firebase (see Step 2 above)
2. Test on iOS device
3. Monitor Firebase Console for delivery reports
4. Check Laravel logs for any FCM errors

## Support

If issues persist after uploading APNs key:
1. Check Firebase Console → Cloud Messaging → Logs
2. Check Laravel logs: `tail -f storage/logs/laravel.log | grep FCM`
3. Verify iOS app has notification permissions enabled
4. Ensure iOS device has internet connection
