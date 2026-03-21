#!/bin/bash

# Habukhan Device Key Generator Script
# This script generates a secure 64-character device key and updates the .env file

echo "🔑 Generating new Habukhan Device Key..."

# Generate a secure 64-character hexadecimal key
NEW_KEY=$(openssl rand -hex 32)

echo "Generated key: $NEW_KEY"

# Backup current .env file
cp .env .env.backup.$(date +%Y%m%d_%H%M%S)
echo "✅ Backed up current .env file"

# Update the .env file
if grep -q "HABUKHAN_DEVICE_KEY=" .env; then
    # Key exists, replace it
    sed -i "s/HABUKHAN_DEVICE_KEY=.*/HABUKHAN_DEVICE_KEY=$NEW_KEY/" .env
    echo "✅ Updated existing HABUKHAN_DEVICE_KEY in .env"
else
    # Key doesn't exist, add it
    echo "HABUKHAN_DEVICE_KEY=$NEW_KEY" >> .env
    echo "✅ Added new HABUKHAN_DEVICE_KEY to .env"
fi

echo "🎉 Device key generation complete!"
echo "📱 Don't forget to update your mobile app configuration with the new key"
echo "🔐 New key: $NEW_KEY"