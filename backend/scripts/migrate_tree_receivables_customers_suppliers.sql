-- =============================================================================
-- تعديل شجرة الحسابات لتتوافق مع منطق النظام الجديد (AccountLinkingService)
-- مخصص لقاعدة بنفس معرفات الـ dump (الأصول المتداولة = 7، العملاء/الذمم = 21، …)
-- نفّذ في phpMyAdmin داخل معاملة واحدة وراجع النتيجة قبل COMMIT.
-- =============================================================================
-- ما يفعله السكربت:
--   1) إعادة تسمية حساب «العملاء» (21) إلى «المدينون» حتى يتعرّف عليه الكود الجديد.
--   2) إنشاء حسابات التجميع: عملاء أفراد / عملاء شركات / عملاء أونلاين (إن لم توجد).
--   3) نقل «عملاء محليين» و«عملاء خارجيين» و«test client» تحت «عملاء أفراد» وتصحيح المستويات.
--   4) نقل موردي الجذر الخاطئ (48،53،63،68) من «الموردين» (47) إلى «موردين محليين» (43) وحذف 47.
--   5) (اختياري) تحديث جدول settings لربط الإعدادات بالحسابات الجديدة.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

START TRANSACTION;

-- -----------------------------------------------------------------------------
-- 0) ثوابت من الـ dump (عدّلها إذا كانت معرفاتك مختلفة)
-- -----------------------------------------------------------------------------
SET @id_current_assets   := 7;   -- الأصول المتداولة
SET @id_receivables      := 21;  -- كان اسمه «العملاء» — سيصبح «المدينون»
SET @id_creditors        := 22;  -- الدائنون
SET @id_suppliers_local  := 43;  -- موردين محليين
SET @id_supplier_orphan_root := 47; -- جذر خاطئ «الموردين»

-- -----------------------------------------------------------------------------
-- 1) توحيد اسم ذمم العملاء مع الشجرة القياسية (المدينون تحت الأصول المتداولة)
-- -----------------------------------------------------------------------------
UPDATE `tree_accounts`
SET
  `name`    = 'المدينون',
  `name_en` = COALESCE(NULLIF(TRIM(`name_en`), ''), 'Accounts Receivable'),
  `updated_at` = NOW()
WHERE `id` = @id_receivables
  AND `parent_id` = @id_current_assets
  AND `name` IN ('العملاء', 'المدينون');

-- -----------------------------------------------------------------------------
-- 2) إنشاء حسابات التجميع الثلاثة تحت المدينون (أكواد جديدة لا تتعارض مع 1000231–1000233)
-- -----------------------------------------------------------------------------
INSERT INTO `tree_accounts` (
  `name`, `name_en`, `code`, `parent_id`, `main_account_id`, `type`,
  `detail_type`, `account_type`, `budget_type`, `budget_amount`, `budget_period`,
  `is_trading_account`, `level`, `balance`, `debit_balance`, `credit_balance`,
  `previous_year_amount`, `created_at`, `updated_at`
)
SELECT 'عملاء أفراد', NULL, '1000234', @id_receivables, NULL, 'asset',
  NULL, NULL, NULL, NULL, NULL,
  0, 4, 0, 0, 0,
  NULL, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `tree_accounts` t
  WHERE t.`parent_id` = @id_receivables AND t.`name` = 'عملاء أفراد'
);

INSERT INTO `tree_accounts` (
  `name`, `name_en`, `code`, `parent_id`, `main_account_id`, `type`,
  `detail_type`, `account_type`, `budget_type`, `budget_amount`, `budget_period`,
  `is_trading_account`, `level`, `balance`, `debit_balance`, `credit_balance`,
  `previous_year_amount`, `created_at`, `updated_at`
)
SELECT 'عملاء شركات', NULL, '1000235', @id_receivables, NULL, 'asset',
  NULL, NULL, NULL, NULL, NULL,
  0, 4, 0, 0, 0,
  NULL, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `tree_accounts` t
  WHERE t.`parent_id` = @id_receivables AND t.`name` = 'عملاء شركات'
);

INSERT INTO `tree_accounts` (
  `name`, `name_en`, `code`, `parent_id`, `main_account_id`, `type`,
  `detail_type`, `account_type`, `budget_type`, `budget_amount`, `budget_period`,
  `is_trading_account`, `level`, `balance`, `debit_balance`, `credit_balance`,
  `previous_year_amount`, `created_at`, `updated_at`
)
SELECT 'عملاء أونلاين', NULL, '1000236', @id_receivables, NULL, 'asset',
  NULL, NULL, NULL, NULL, NULL,
  0, 4, 0, 0, 0,
  NULL, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `tree_accounts` t
  WHERE t.`parent_id` = @id_receivables AND t.`name` = 'عملاء أونلاين'
);

SET @id_bucket_individual := (
  SELECT `id` FROM `tree_accounts`
  WHERE `parent_id` = @id_receivables AND `name` = 'عملاء أفراد'
  LIMIT 1
);
SET @id_bucket_corporate := (
  SELECT `id` FROM `tree_accounts`
  WHERE `parent_id` = @id_receivables AND `name` = 'عملاء شركات'
  LIMIT 1
);
SET @id_bucket_online := (
  SELECT `id` FROM `tree_accounts`
  WHERE `parent_id` = @id_receivables AND `name` = 'عملاء أونلاين'
  LIMIT 1
);

-- -----------------------------------------------------------------------------
-- 3) نقل مجلدات العملاء القديمة + عميل الاختبار تحت «عملاء أفراد»
--    (معرفات 41، 42، 67 من الـ dump)
-- -----------------------------------------------------------------------------
UPDATE `tree_accounts`
SET
  `parent_id` = @id_bucket_individual,
  `level` = 5,
  `updated_at` = NOW()
WHERE `id` IN (41, 42, 67)
  AND `parent_id` = @id_receivables;

-- تصحيح مستوى أي حسابات فرعية كانت تحت 41 أو 42 (إن وُجدت)
UPDATE `tree_accounts` AS child
INNER JOIN `tree_accounts` AS folder ON child.`parent_id` = folder.`id`
SET child.`level` = folder.`level` + 1, child.`updated_at` = NOW()
WHERE folder.`id` IN (41, 42);

-- -----------------------------------------------------------------------------
-- 4) إصلاح الموردين: نقل الأبناء من الجذر الخاطئ (47) إلى «موردين محليين» (43)
-- -----------------------------------------------------------------------------
UPDATE `tree_accounts`
SET
  `parent_id` = @id_suppliers_local,
  `level` = 5,
  `updated_at` = NOW()
WHERE `parent_id` = @id_supplier_orphan_root
  AND `id` IN (48, 53, 63, 68);

-- احذف الجذر اليتيم «الموردين» فقط إذا لم يبقَ له أبناء
DELETE FROM `tree_accounts`
WHERE `id` = @id_supplier_orphan_root
  AND NOT EXISTS (SELECT 1 FROM `tree_accounts` c WHERE c.`parent_id` = @id_supplier_orphan_root);

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- 5) (اختياري) ربط إعدادات التطبيق بجدول settings — شغّله فقط إذا كان الجدول موجوداً
-- -----------------------------------------------------------------------------
-- UPDATE `settings` s
-- INNER JOIN `tree_accounts` t ON t.`id` = @id_bucket_individual
-- SET s.`value` = t.`id`
-- WHERE s.`key` = 'customer_individual_parent_account_id';
--
-- UPDATE `settings` s
-- INNER JOIN `tree_accounts` t ON t.`id` = @id_bucket_corporate
-- SET s.`value` = t.`id`
-- WHERE s.`key` = 'customer_corporate_parent_account_id';
--
-- UPDATE `settings` s
-- INNER JOIN `tree_accounts` t ON t.`id` = @id_bucket_online
-- SET s.`value` = t.`id`
-- WHERE s.`key` = 'customer_online_parent_account_id';
--
-- UPDATE `settings` s
-- SET s.`value` = @id_suppliers_local
-- WHERE s.`key` = 'supplier_general_parent_id';

COMMIT;

-- =============================================================================
-- بعد التنفيذ:
--   • راجع الشجرة في الواجهة؛ إذا كان لديك حسابات عميل/مورد أخرى تحت 47 غير
--     المذكورة، أضف معرفاتها في UPDATE الخطوة 4 قبل الحذف.
--   • إذا فشل INSERT بسبب تكرار code، غيّر 1000234–1000236 إلى أكواد غير مستخدمة.
-- =============================================================================
