-- ============================================================
-- استعلامات شجرة الحسابات (tree_accounts) — MySQL/MariaDB
-- ============================================================

-- 1) كل الحسابات مع اسم الحساب الأب
SELECT
    a.id,
    a.code,
    a.name,
    a.name_en,
    a.type,
    a.detail_type,
    a.level,
    a.parent_id,
    p.name AS parent_name,
    a.balance,
    COALESCE(a.debit_balance, 0)  AS debit_balance,
    COALESCE(a.credit_balance, 0) AS credit_balance,
    (COALESCE(a.debit_balance, 0) - COALESCE(a.credit_balance, 0)) AS net_balance_dr_minus_cr,
    a.is_trading_account,
    a.created_at
FROM tree_accounts AS a
LEFT JOIN tree_accounts AS p ON p.id = a.parent_id
ORDER BY CAST(a.code AS UNSIGNED), a.code;

-- 2) حسابات المخزون والمبيعات والتكلفة (detail_type كما يستخدمها النظام)
SELECT
    id, code, name, type, detail_type, level,
    COALESCE(debit_balance, 0) AS dr, COALESCE(credit_balance, 0) AS cr,
    (COALESCE(debit_balance, 0) - COALESCE(credit_balance, 0)) AS net
FROM tree_accounts
WHERE detail_type IN ('inventory', 'cogs', 'sales')
   OR (type = 'asset' AND name LIKE '%مخزون%')
ORDER BY detail_type, code;

-- 3) تجميع بعدد الحسابات حسب نوع الحساب (type)
SELECT type, COUNT(*) AS cnt
FROM tree_accounts
GROUP BY type
ORDER BY type;

-- 4) الحسابات الورقية فقط (بدون أبناء — أوراق في الشجرة)
SELECT a.id, a.code, a.name, a.type, a.detail_type,
       (COALESCE(a.debit_balance, 0) - COALESCE(a.credit_balance, 0)) AS net
FROM tree_accounts AS a
WHERE NOT EXISTS (
    SELECT 1 FROM tree_accounts c WHERE c.parent_id = a.id
)
ORDER BY a.type, CAST(a.code AS UNSIGNED);
