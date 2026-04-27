-- =============================================================================
-- حساب مجموعة «تسوية» (نوع settlement) — MySQL
-- شغّل migration إضافة settlement للـ ENUM أولاً إن لم يكن مطبّقاً:
--   php artisan migrate
-- أو يدوياً:
-- =============================================================================

-- (اختياري) إن كان عمود type لا يزال بدون settlement فقط:
-- ALTER TABLE `tree_accounts`
--   MODIFY COLUMN `type` ENUM('asset','liability','equity','revenue','expense','settlement') NOT NULL;

-- إضافة حساب رئيسي مستوى 1 نوعه settlement — لا يُكرّر إذا وُجد كود 6000
INSERT INTO `tree_accounts` (
    `name`,
    `name_en`,
    `code`,
    `parent_id`,
    `type`,
    `level`,
    `balance`,
    `debit_balance`,
    `credit_balance`,
    `is_trading_account`,
    `created_at`,
    `updated_at`
)
SELECT
    'حسابات التسوية',
    'Settlement accounts',
    6000,
    NULL,
    'settlement',
    1,
    0,
    0,
    0,
    0,
    NOW(),
    NOW()
WHERE NOT EXISTS (SELECT 1 FROM `tree_accounts` AS t WHERE t.`code` = 6000);

-- مثال: حساب فرعي تحت المجموعة (عدّل parent_id ليطابق id المجموعة بعد الإدراج)
-- INSERT INTO `tree_accounts` (`name`, `name_en`, `code`, `parent_id`, `type`, `level`, `balance`, `debit_balance`, `credit_balance`, `is_trading_account`, `created_at`, `updated_at`)
-- SELECT 'تسوية مطابقة أرصدة', 'Balance alignment', 60001, id, 'settlement', 2, 0, 0, 0, 0, NOW(), NOW()
-- FROM `tree_accounts` WHERE `code` = 6000 LIMIT 1;
