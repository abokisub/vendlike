-- Fix FAQ: Change "Fund Wallet" to "Add Cash" to match app UI
-- Run this on the live database

-- Update the FAQ answer to use "Add Cash" instead of "Fund Wallet"
UPDATE faqs 
SET answer = 'You can add cash to your wallet via bank transfer, USSD, or credit/debit card. Navigate to the "Add Cash" section on your dashboard for more details.'
WHERE question = 'How do I fund my wallet?';

-- Verify the change
SELECT id, question, answer FROM faqs WHERE question LIKE '%fund%wallet%';
