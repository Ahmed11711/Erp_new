-- =============================================================================
-- إعادة ضبط شجرة الحسابات — استخدم بحذر شديد
-- =============================================================================
-- قبل التشغيل: خذ نسخة احتياطية كاملة من قاعدة البيانات (mysqldump).
-- هذا السكربت يمسح القيود والسندات المرتبطة بالحسابات، ويمسح شجرة الحسابات،
-- ثم يعيد إنشاء هيكل افتراضي مطابق تقريباً لـ TreeAccountSeeder مع detail_type
-- للحسابات التي يبحث عنها الكود (مبيعات، مخزون، تكلفة مبيعات، إلخ).
--
-- تشغيل من MySQL:
--   mysql -u USER -p DB_NAME < reset_chart_of_accounts.sql
-- أو من phpMyAdmin: لصق وتنفيذ.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- 1) تفريغ / حذف البيانات المحاسبية المرتبطة بالحسابات
-- ---------------------------------------------------------------------------
TRUNCATE TABLE `account_entries`;
TRUNCATE TABLE `daily_entry_items`;
TRUNCATE TABLE `daily_entries`;
TRUNCATE TABLE `vouchers`;

-- إن وُجد جدول قيود مرتبط (عدّل الاسم إن اختلف عندك)
-- TRUNCATE TABLE `journal_entry_lines`;

-- فك ارتباط الحسابات من جداول تشغيلية (قيم nullable)
UPDATE `safes` SET `account_id` = NULL WHERE `account_id` IS NOT NULL;
UPDATE `banks` SET `asset_id` = NULL WHERE `asset_id` IS NOT NULL;
UPDATE `service_accounts` SET `account_id` = NULL WHERE `account_id` IS NOT NULL;
UPDATE `customer_companies` SET `tree_account_id` = NULL WHERE `tree_account_id` IS NOT NULL;
UPDATE `suppliers` SET `tree_account_id` = NULL WHERE `tree_account_id` IS NOT NULL;

-- جدول الالتزامات (اسم الجدول كما في المشروع: cimmitments)
UPDATE `cimmitments` SET `expense_account_id` = NULL, `liability_account_id` = NULL
WHERE `expense_account_id` IS NOT NULL OR `liability_account_id` IS NOT NULL;

-- أصول ثابتة: أعمدة حسابات بدون FK في بعض الإصدارات — تصفير آمن
UPDATE `assets` SET
  `asset_account_id` = NULL,
  `depreciation_account_id` = NULL,
  `expense_account_id` = NULL
WHERE 1 = 1;

-- ---------------------------------------------------------------------------
-- 2) مسح شجرة الحسابات
-- ---------------------------------------------------------------------------
TRUNCATE TABLE `tree_accounts`;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- 3) إدراج الشجرة (نفس منطق TreeAccountSeeder + detail_type للأوراق المهمة)
--    الأعمدة الاختيارية: إذا فشل الإدراج لغياب عمود، احذف name_en أو detail_type
--    من القائمة أو أضف الأعمدة من migration.
-- ---------------------------------------------------------------------------

-- المستوى 1
INSERT INTO `tree_accounts` (`name`, `name_en`, `code`, `parent_id`, `type`, `level`, `balance`, `debit_balance`, `credit_balance`, `detail_type`, `created_at`, `updated_at`) VALUES
('الأصول', 'Assets', '1000', NULL, 'asset', 1, 0, 0, 0, NULL, NOW(), NOW()),
('الخصوم', 'Liabilities', '2000', NULL, 'liability', 1, 0, 0, 0, NULL, NOW(), NOW()),
('حقوق الملكية', 'Equity', '3000', NULL, 'equity', 1, 0, 0, 0, NULL, NOW(), NOW()),
('الإيرادات', 'Revenue', '4000', NULL, 'revenue', 1, 0, 0, 0, NULL, NOW(), NOW()),
('المصروفات', 'Expenses', '5000', NULL, 'expense', 1, 0, 0, 0, NULL, NOW(), NOW());

SET @id_assets := (SELECT id FROM tree_accounts WHERE code = '1000' LIMIT 1);
SET @id_liab := (SELECT id FROM tree_accounts WHERE code = '2000' LIMIT 1);
SET @id_equity := (SELECT id FROM tree_accounts WHERE code = '3000' LIMIT 1);
SET @id_rev := (SELECT id FROM tree_accounts WHERE code = '4000' LIMIT 1);
SET @id_exp := (SELECT id FROM tree_accounts WHERE code = '5000' LIMIT 1);

-- المستوى 2
INSERT INTO `tree_accounts` (`name`, `name_en`, `code`, `parent_id`, `type`, `level`, `balance`, `debit_balance`, `credit_balance`, `detail_type`, `created_at`, `updated_at`) VALUES
('الأصول الثابتة', 'Fixed Assets', '10001', @id_assets, 'asset', 2, 0, 0, 0, NULL, NOW(), NOW()),
('الأصول المتداولة', 'Current Assets', '10002', @id_assets, 'asset', 2, 0, 0, 0, NULL, NOW(), NOW()),
('الخصوم المتداولة', 'Current Liabilities', '20001', @id_liab, 'liability', 2, 0, 0, 0, NULL, NOW(), NOW()),
('الخصوم طويلة الأجل', 'Long-term Liabilities', '20002', @id_liab, 'liability', 2, 0, 0, 0, NULL, NOW(), NOW()),
('رأس المال', 'Capital Stock', '30001', @id_equity, 'equity', 2, 0, 0, 0, NULL, NOW(), NOW()),
('الأرباح المحتجزة', 'Retained Earnings', '30002', @id_equity, 'equity', 2, 0, 0, 0, NULL, NOW(), NOW()),
('إيرادات المبيعات', 'Sales Revenue', '40001', @id_rev, 'revenue', 2, 0, 0, 0, NULL, NOW(), NOW()),
('إيرادات أخرى', 'Other Revenue', '40002', @id_rev, 'revenue', 2, 0, 0, 0, NULL, NOW(), NOW()),
('مصروفات تشغيلية', 'Operating Expenses', '50001', @id_exp, 'expense', 2, 0, 0, 0, NULL, NOW(), NOW()),
('مصروفات إدارية', 'Administrative Expenses', '50002', @id_exp, 'expense', 2, 0, 0, 0, NULL, NOW(), NOW());

SET @id_fa := (SELECT id FROM tree_accounts WHERE code = '10001' LIMIT 1);
SET @id_ca := (SELECT id FROM tree_accounts WHERE code = '10002' LIMIT 1);
SET @id_cl := (SELECT id FROM tree_accounts WHERE code = '20001' LIMIT 1);
SET @id_ll := (SELECT id FROM tree_accounts WHERE code = '20002' LIMIT 1);
SET @id_cap := (SELECT id FROM tree_accounts WHERE code = '30001' LIMIT 1);
SET @id_re := (SELECT id FROM tree_accounts WHERE code = '30002' LIMIT 1);
SET @id_sales_grp := (SELECT id FROM tree_accounts WHERE code = '40001' LIMIT 1);
SET @id_oth_rev := (SELECT id FROM tree_accounts WHERE code = '40002' LIMIT 1);
SET @id_opex := (SELECT id FROM tree_accounts WHERE code = '50001' LIMIT 1);
SET @id_adm := (SELECT id FROM tree_accounts WHERE code = '50002' LIMIT 1);

-- المستوى 3
INSERT INTO `tree_accounts` (`name`, `name_en`, `code`, `parent_id`, `type`, `level`, `balance`, `debit_balance`, `credit_balance`, `detail_type`, `created_at`, `updated_at`) VALUES
('الأراضي', 'Land', '100011', @id_fa, 'asset', 3, 0, 0, 0, NULL, NOW(), NOW()),
('المباني', 'Buildings', '100012', @id_fa, 'asset', 3, 0, 0, 0, NULL, NOW(), NOW()),
('المعدات', 'Equipment', '100013', @id_fa, 'asset', 3, 0, 0, 0, NULL, NOW(), NOW()),
('النقدية', 'Cash', '100021', @id_ca, 'asset', 3, 0, 0, 0, NULL, NOW(), NOW()),
('المخزون', 'Inventory', '100022', @id_ca, 'asset', 3, 0, 0, 0, 'inventory', NOW(), NOW()),
('المدينون', 'Accounts Receivable', '100023', @id_ca, 'asset', 3, 0, 0, 0, NULL, NOW(), NOW()),
('الدائنون', 'Accounts Payable', '200011', @id_cl, 'liability', 3, 0, 0, 0, NULL, NOW(), NOW()),
('المصروفات المستحقة', 'Accrued Expenses', '200012', @id_cl, 'liability', 3, 0, 0, 0, NULL, NOW(), NOW()),
('قروض طويلة الأجل', 'Long-term Loans', '200021', @id_ll, 'liability', 3, 0, 0, 0, NULL, NOW(), NOW()),
('رأس مال مؤسسين', 'Founders Capital', '300011', @id_cap, 'equity', 3, 0, 0, 0, NULL, NOW(), NOW()),
('رأس مال إضافي', 'Additional Paid-in Capital', '300012', @id_cap, 'equity', 3, 0, 0, 0, NULL, NOW(), NOW()),
('أرباح سنوات سابقة', 'Prior Years Earnings', '300021', @id_re, 'equity', 3, 0, 0, 0, NULL, NOW(), NOW()),
('مبيعات منتجات', 'Product Sales', '400011', @id_sales_grp, 'revenue', 3, 0, 0, 0, 'sales', NOW(), NOW()),
('مبيعات خدمات', 'Service Sales', '400012', @id_sales_grp, 'revenue', 3, 0, 0, 0, 'sales', NOW(), NOW()),
('فوائد بنكية', 'Bank Interest Income', '400021', @id_oth_rev, 'revenue', 3, 0, 0, 0, NULL, NOW(), NOW()),
('رواتب وأجور', 'Salaries and Wages', '500011', @id_opex, 'expense', 3, 0, 0, 0, NULL, NOW(), NOW()),
('صيانة', 'Maintenance', '500012', @id_opex, 'expense', 3, 0, 0, 0, NULL, NOW(), NOW()),
('كهرباء ومياه', 'Utilities', '500013', @id_opex, 'expense', 3, 0, 0, 0, NULL, NOW(), NOW()),
('إيجار المقر', 'Rent Expense', '500021', @id_adm, 'expense', 3, 0, 0, 0, NULL, NOW(), NOW()),
('معدات مكتبية', 'Office Equipment Expense', '500022', @id_adm, 'expense', 3, 0, 0, 0, NULL, NOW(), NOW()),
('تكلفة المبيعات', 'Cost of Goods Sold', '500014', @id_opex, 'expense', 3, 0, 0, 0, 'cogs', NOW(), NOW());

SET @id_build := (SELECT id FROM tree_accounts WHERE code = '100012' LIMIT 1);
SET @id_cash := (SELECT id FROM tree_accounts WHERE code = '100021' LIMIT 1);
SET @id_ar := (SELECT id FROM tree_accounts WHERE code = '100023' LIMIT 1);
SET @id_ap := (SELECT id FROM tree_accounts WHERE code = '200011' LIMIT 1);
SET @id_sal := (SELECT id FROM tree_accounts WHERE code = '500011' LIMIT 1);

-- المستوى 4 (تفاصيل)
INSERT INTO `tree_accounts` (`name`, `name_en`, `code`, `parent_id`, `type`, `level`, `balance`, `debit_balance`, `credit_balance`, `detail_type`, `created_at`, `updated_at`) VALUES
('مبنى الإدارة', 'Admin Building', '1000121', @id_build, 'asset', 4, 0, 0, 0, NULL, NOW(), NOW()),
('مبنى المصنع', 'Factory Building', '1000122', @id_build, 'asset', 4, 0, 0, 0, NULL, NOW(), NOW()),
('خزينة الشركة', 'Company Safe', '1000211', @id_cash, 'asset', 4, 0, 0, 0, 'cash', NOW(), NOW()),
('حساب البنك', 'Bank Account', '1000212', @id_cash, 'asset', 4, 0, 0, 0, NULL, NOW(), NOW()),
('عملاء محليين', 'Local Customers', '1000231', @id_ar, 'asset', 4, 0, 0, 0, NULL, NOW(), NOW()),
('عملاء خارجيين', 'Foreign Customers', '1000232', @id_ar, 'asset', 4, 0, 0, 0, NULL, NOW(), NOW()),
('موردين محليين', 'Local Suppliers', '2000111', @id_ap, 'liability', 4, 0, 0, 0, NULL, NOW(), NOW()),
('موردين خارجيين', 'Foreign Suppliers', '2000112', @id_ap, 'liability', 4, 0, 0, 0, NULL, NOW(), NOW()),
('رواتب موظفين', 'Employee Salaries', '5000111', @id_sal, 'expense', 4, 0, 0, 0, NULL, NOW(), NOW()),
('حوافز ومكافآت', 'Bonuses and Incentives', '5000112', @id_sal, 'expense', 4, 0, 0, 0, NULL, NOW(), NOW());

-- =============================================================================
-- 4) ضمان حسابي COGS (500014) والمخزون (100022) — نفس منطق seed_cogs_inventory_new_tree.sql
--    يُكمّل الشجرة إذا نُقِص صف (أو شجرة قديمة 3091001/1061001)؛ إن وُجدت الأكواد يتخطى الإدراج.
-- =============================================================================

DELETE FROM `tree_accounts` WHERE `code` IN ('3091001', '1061001');

SET @cogs_parent := (SELECT `id` FROM `tree_accounts` WHERE `code` = '50001' LIMIT 1);
SET @cogs_parent := IFNULL(@cogs_parent, (SELECT `id` FROM `tree_accounts` WHERE `code` = '5000' LIMIT 1));

INSERT INTO `tree_accounts` (
    `name`, `name_en`, `code`, `parent_id`, `type`, `level`,
    `balance`, `debit_balance`, `credit_balance`, `detail_type`,
    `is_trading_account`, `created_at`, `updated_at`
)
SELECT
    'تكلفة البضاعة المباعة',
    'Cost of goods sold',
    '500014',
    @cogs_parent,
    'expense',
    IFNULL((SELECT `level` + 1 FROM `tree_accounts` WHERE `id` = @cogs_parent LIMIT 1), 3),
    0, 0, 0,
    'cogs',
    0,
    NOW(),
    NOW()
FROM (SELECT 1 AS `_`) AS `_row`
WHERE @cogs_parent IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `tree_accounts` t WHERE t.`code` = '500014');

SET @inv_parent := (SELECT `id` FROM `tree_accounts` WHERE `code` = '10002' LIMIT 1);
SET @inv_parent := IFNULL(@inv_parent, (SELECT `id` FROM `tree_accounts` WHERE `code` = '1000' LIMIT 1));

INSERT INTO `tree_accounts` (
    `name`, `name_en`, `code`, `parent_id`, `type`, `level`,
    `balance`, `debit_balance`, `credit_balance`, `detail_type`,
    `is_trading_account`, `created_at`, `updated_at`
)
SELECT
    'مخزون البضاعة',
    'Inventory',
    '100022',
    @inv_parent,
    'asset',
    IFNULL((SELECT `level` + 1 FROM `tree_accounts` WHERE `id` = @inv_parent LIMIT 1), 3),
    0, 0, 0,
    'inventory',
    0,
    NOW(),
    NOW()
FROM (SELECT 1 AS `_`) AS `_row`
WHERE @inv_parent IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `tree_accounts` t WHERE t.`code` = '100022');

UPDATE `tree_accounts`
SET
    `detail_type` = 'inventory',
    `name_en` = COALESCE(NULLIF(`name_en`, ''), 'Inventory')
WHERE `code` = '100022'
  AND (`detail_type` IS NULL OR `detail_type` = '');

SELECT `id`, `code`, `name`, `parent_id`, `level`, `detail_type`
FROM `tree_accounts`
WHERE `code` IN ('500014', '100022');

-- =============================================================================
-- انتهى. راجع شجرة الحسابات من الواجهة، ثم اربط الخزن/البنوك/العملاء بالحسابات المناسبة.
-- بديل: php artisan db:seed --class=TreeAccountSeeder ثم قسم (4) أعلاه أو ملف seed_cogs_inventory_new_tree.sql منفرداً.
-- =============================================================================
