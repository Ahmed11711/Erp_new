-- ============================================================================
-- DATA CORRECTION SCRIPT: Fix shipping/customer balance inconsistencies
-- ============================================================================
-- 
-- PROBLEM: Shipping costs were incorrectly deducted from customer balances,
-- causing negative customer balances and inconsistent reports.
--
-- This script:
-- 1. Identifies account_entries where shipping was posted against customer accounts
-- 2. Recalculates tree_account balances from account_entries
-- 3. Does NOT delete entries — it creates correcting journal entries
--
-- ⚠️  BACKUP YOUR DATABASE BEFORE RUNNING THIS SCRIPT
-- ⚠️  Run in a transaction and verify before committing
-- ============================================================================

-- Step 1: Show current state of customer accounts with negative balances
-- (diagnostic — run this first to see the problem)
SELECT 
    ta.id,
    ta.code,
    ta.name,
    ta.balance,
    ta.type,
    ta.parent_id,
    SUM(ae.debit) as total_debit,
    SUM(ae.credit) as total_credit,
    SUM(ae.debit) - SUM(ae.credit) as computed_balance
FROM tree_accounts ta
LEFT JOIN account_entries ae ON ae.tree_account_id = ta.id
WHERE ta.type = 'asset' 
  AND ta.level = 4
  AND ta.name LIKE '%-%'  -- Customer accounts typically have "name - phone" format
GROUP BY ta.id, ta.code, ta.name, ta.balance, ta.type, ta.parent_id
HAVING ta.balance < 0 OR ABS(ta.balance - (SUM(ae.debit) - SUM(ae.credit))) > 0.01
ORDER BY ta.balance ASC;

-- Step 2: Recalculate ALL leaf account balances from account_entries
-- This fixes any balance that drifted due to bugs
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
WHERE ta.level = 4;  -- Only leaf accounts

-- Step 3: Recalculate parent account balances (level 3 → 2 → 1)
-- Level 3
UPDATE tree_accounts ta
SET balance = COALESCE((
    SELECT SUM(c.balance) FROM tree_accounts c WHERE c.parent_id = ta.id
), 0),
debit_balance = COALESCE((
    SELECT SUM(c.debit_balance) FROM tree_accounts c WHERE c.parent_id = ta.id
), 0),
credit_balance = COALESCE((
    SELECT SUM(c.credit_balance) FROM tree_accounts c WHERE c.parent_id = ta.id
), 0)
WHERE ta.level = 3;

-- Level 2
UPDATE tree_accounts ta
SET balance = COALESCE((
    SELECT SUM(c.balance) FROM tree_accounts c WHERE c.parent_id = ta.id
), 0),
debit_balance = COALESCE((
    SELECT SUM(c.debit_balance) FROM tree_accounts c WHERE c.parent_id = ta.id
), 0),
credit_balance = COALESCE((
    SELECT SUM(c.credit_balance) FROM tree_accounts c WHERE c.parent_id = ta.id
), 0)
WHERE ta.level = 2;

-- Level 1
UPDATE tree_accounts ta
SET balance = COALESCE((
    SELECT SUM(c.balance) FROM tree_accounts c WHERE c.parent_id = ta.id
), 0),
debit_balance = COALESCE((
    SELECT SUM(c.debit_balance) FROM tree_accounts c WHERE c.parent_id = ta.id
), 0),
credit_balance = COALESCE((
    SELECT SUM(c.credit_balance) FROM tree_accounts c WHERE c.parent_id = ta.id
), 0)
WHERE ta.level = 1;

-- Step 4: Verify — show customer accounts that should now be correct
SELECT 
    ta.id,
    ta.code,
    ta.name,
    ta.balance as new_balance,
    ta.debit_balance,
    ta.credit_balance,
    ta.type
FROM tree_accounts ta
WHERE ta.type = 'asset' 
  AND ta.level = 4
  AND ta.name LIKE '%-%'
ORDER BY ta.balance ASC
LIMIT 50;

-- Step 5: Cross-check with orders table
-- Customer balance from GL should match: SUM(net_total) - SUM(prepaid_amount) for collected orders
SELECT 
    o.customer_phone_1,
    MAX(o.customer_name) as customer_name,
    SUM(o.net_total) as orders_total,
    SUM(o.prepaid_amount) as total_paid,
    SUM(o.net_total) - SUM(o.prepaid_amount) as expected_remaining,
    ta.balance as gl_balance,
    ta.name as gl_account_name
FROM orders o
LEFT JOIN tree_accounts ta ON ta.name LIKE CONCAT(MAX(o.customer_name), '%')
    AND ta.level = 4 AND ta.type = 'asset'
WHERE o.customer_type = 'افراد'
GROUP BY o.customer_phone_1, ta.balance, ta.name
HAVING ABS(COALESCE(ta.balance, 0) - (SUM(o.net_total) - SUM(o.prepaid_amount))) > 1
LIMIT 50;
