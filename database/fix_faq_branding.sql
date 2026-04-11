-- Fix FAQ branding: Replace Kobopoint and Aboki with VendLike
-- Run this on live database to fix all branding issues

-- 1. Fix "What is Kobopoint?" question
UPDATE faqs 
SET question = 'What is VendLike?'
WHERE question LIKE '%Kobopoint%' OR question LIKE '%kobopoint%';

-- 2. Fix "What is the 'Aboki' AI Chat?" question
UPDATE faqs 
SET question = 'What is the ''VendLike AI'' Chat?'
WHERE question LIKE '%Aboki%';

-- 3. Fix answers that mention Kobopoint
UPDATE faqs 
SET answer = REPLACE(REPLACE(answer, 'Kobopoint', 'VendLike'), 'kobopoint', 'VendLike')
WHERE answer LIKE '%obopoint%';

-- 4. Fix answers that mention Aboki (including the contact support answer)
UPDATE faqs 
SET answer = REPLACE(answer, 'Aboki', 'VendLike AI')
WHERE answer LIKE '%Aboki%';

-- 5. Fix "Fund Wallet" to "Add Cash" to match app UI
UPDATE faqs 
SET answer = 'You can add cash to your wallet via bank transfer, USSD, or credit/debit card. Navigate to the "Add Cash" section on your dashboard for more details.'
WHERE question LIKE '%fund%wallet%';

-- Verify all changes
SELECT id, question, answer FROM faqs WHERE status = 1 ORDER BY id;
