-- ============================================================================
-- FIX: Merge duplicate customer accounts in tree_accounts
-- ============================================================================
-- Problem: The system created two accounts for the same customer:
--   1. "تست عميل" (name only — created by old SalesOrderAccountingService)
--   2. "تست عميل - 01023232323" (name + phone — created by OrdersController)
--
-- This script:
--   1. Finds duplicate pairs
--   2. Moves all account_entries from the name-only account to the name+phone account
--   3. Deletes the empty name-only account
--   4. Recalculates balances
-- ============================================================================

-- Step 1: DIAGNOSTIC — Find duplicate customer accounts
-- Shows pairs where a "name only" account exists alongside a "name - phone" account
SELECT 
    a.id as name_only_id,
    a.code as name_only_code,
    a.name as name_only_name,
    a.balance as name_only_balance,
    b.id as with_phone_id,
    b.code as with_phone_code,
    b.name as with_phone_name,
    b.balance as with_phone_balance
FROM tree_accounts a
JOIN tree_accounts b ON b.name LIKE CONCAT(a.name, ' - %')
WHERE a.level = 4
  AND a.type = 'asset'
  AND b.level = 4
  AND b.type = 'asset'
  AND a.name NOT LIKE '% - %'
ORDER BY a.name;

-- Step 2: Move account_entries from name-only to name+phone account
-- Run this for EACH duplicate pair found in Step 1
-- Replace {NAME_ONLY_ID} and {WITH_PHONE_ID} with actual IDs

-- Example for the test data shown in the screenshot:
-- UPDATE account_entries 
-- SET tree_account_id = {WITH_PHONE_ID}
-- WHERE tree_account_id = {NAME_ONLY_ID};

-- Automated version — moves ALL entries from name-only duplicates:
UPDATE account_entries ae
JOIN tree_accounts a ON ae.tree_account_id = a.id
JOIN tree_accounts b ON b.name LIKE CONCAT(a.name, ' - %')
    AND b.level = 4 AND b.type = 'asset'
SET ae.tree_account_id = b.id
WHERE a.level = 4
  AND a.type = 'asset'
  AND a.name NOT LIKE '% - %';

-- Also move daily_entry_items
UPDATE daily_entry_items dei
JOIN tree_accounts a ON dei.account_id = a.id
JOIN tree_accounts b ON b.name LIKE CONCAT(a.name, ' - %')
    AND b.level = 4 AND b.type = 'asset'
SET dei.account_id = b.id
WHERE a.level = 4
  AND a.type = 'asset'
  AND a.name NOT LIKE '% - %';

-- Step 3: Delete the now-empty name-only duplicate accounts
DELETE a FROM tree_accounts a
JOIN tree_accounts b ON b.name LIKE CONCAT(a.name, ' - %')
    AND b.level = 4 AND b.type = 'asset'
WHERE a.level = 4
  AND a.type = 'asset'
  AND a.name NOT LIKE '% - %'
  AND NOT EXISTS (
      SELECT 1 FROM account_entries ae WHERE ae.tree_account_id = a.id
  );

-- Step 4: Recalculate ALL leaf account balances from account_entries
UPDATE tree_accounts ta
SET balance = COALESCE((
    SELECT SUM(ae.debit) - SUM(ae.credit)
    FROM account_entries ae
    WHERE ae.tree_account_id = ta.id
), 0),
debit_balance = COALESCE((
    SELECT SUM(ae.debit)
    FROM account_entries ae
    WHERE ae.tree_account_id = ta.id
), 0),
credit_balance = COALESCE((
    SELECT SUM(ae.credit)
    FROM account_entries ae
    WHERE ae.tree_account_id = ta.id
), 0)
WHERE ta.level = 4;

-- Step 5: Recalculate parent balances (level 3 → 2 → 1)
UPDATE tree_accounts ta SET
    balance = COALESCE((SELECT SUM(c.balance) FROM tree_accounts c WHERE c.parent_id = ta.id), 0),
    debit_balance = COALESCE((SELECT SUM(c.debit_balance) FROM tree_accounts c WHERE c.parent_id = ta.id), 0),
    credit_balance = COALESCE((SELECT SUM(c.credit_balance) FROM tree_accounts c WHERE c.parent_id = ta.id), 0)
WHERE ta.level = 3;

UPDATE tree_accounts ta SET
    balance = COALESCE((SELECT SUM(c.balance) FROM tree_accounts c WHERE c.parent_id = ta.id), 0),
    debit_balance = COALESCE((SELECT SUM(c.debit_balance) FROM tree_accounts c WHERE c.parent_id = ta.id), 0),
    credit_balance = COALESCE((SELECT SUM(c.credit_balance) FROM tree_accounts c WHERE c.parent_id = ta.id), 0)
WHERE ta.level = 2;

UPDATE tree_accounts ta SET
    balance = COALESCE((SELECT SUM(c.balance) FROM tree_accounts c WHERE c.parent_id = ta.id), 0),
    debit_balance = COALESCE((SELECT SUM(c.debit_balance) FROM tree_accounts c WHERE c.parent_id = ta.id), 0),
    credit_balance = COALESCE((SELECT SUM(c.credit_balance) FROM tree_accounts c WHERE c.parent_id = ta.id), 0)
WHERE ta.level = 1;

-- Step 6: Verify — no more duplicates
SELECT 
    a.id, a.name, a.balance
FROM tree_accounts a
JOIN tree_accounts b ON b.name LIKE CONCAT(a.name, ' - %')
    AND b.level = 4 AND b.type = 'asset'
WHERE a.level = 4
  AND a.type = 'asset'
  AND a.name NOT LIKE '% - %';
-- Should return 0 rows
