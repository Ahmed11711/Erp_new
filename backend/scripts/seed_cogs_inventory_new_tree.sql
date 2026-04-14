-- =============================================================================
-- تم دمج هذا الملف في: reset_chart_of_accounts.sql (القسم 4)
--
-- للاستخدام المنفرد فقط (بدون إعادة ضبط الشجرة كاملة): انسخ القسم 4 من هناك، أو نفّذ الأسطر أدناه.
-- =============================================================================

SET NAMES utf8mb4;

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
