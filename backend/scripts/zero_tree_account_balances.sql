-- =============================================================================
-- تصفير أرصدة شجرة الحسابات + مسح القيود المحاسبية (بداية نظيفة للاستيراد)
-- تحذير: عملية لا يمكن التراجع عنها. اعمل Backup قبل التنفيذ.
-- الاستخدام: phpMyAdmin / MySQL CLI على قاعدة المشروع.
-- =============================================================================

-- 0) نسخة احتياطية سريعة للجداول الحساسة (اختياري لكن مُفضّل)
-- CREATE TABLE account_entries_bak_YYYYMMDD AS SELECT * FROM account_entries;
-- CREATE TABLE daily_entry_items_bak_YYYYMMDD AS SELECT * FROM daily_entry_items;
-- CREATE TABLE daily_entries_bak_YYYYMMDD AS SELECT * FROM daily_entries;
-- CREATE TABLE tree_accounts_bak_YYYYMMDD AS SELECT id, code, name, balance, debit_balance, credit_balance FROM tree_accounts;

START TRANSACTION;

SET FOREIGN_KEY_CHECKS = 0;

-- 1) مسح بنود القيود اليومية
DELETE FROM daily_entry_items;

-- 2) مسح رأس القيود اليومية
DELETE FROM daily_entries;

-- 3) مسح قيود الأستاذ (مصدر ميزان المراجعة)
DELETE FROM account_entries;

-- 4) تصفير الأرصدة المخزّنة على الشجرة (بدون حذف الحسابات نفسها)
UPDATE tree_accounts
SET
    balance = 0,
    debit_balance = 0,
    credit_balance = 0,
    updated_at = NOW();

SET FOREIGN_KEY_CHECKS = 1;

COMMIT;

-- =============================================================================
-- تحقق بعد التنفيذ:
-- SELECT COUNT(*) AS entries_left FROM account_entries;          -- لازم 0
-- SELECT COUNT(*) AS daily_left FROM daily_entries;              -- لازم 0
-- SELECT SUM(ABS(balance))+SUM(ABS(debit_balance))+SUM(ABS(credit_balance)) AS bal_sum
-- FROM tree_accounts;                                            -- لازم 0
-- =============================================================================
