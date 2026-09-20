-- ============================================================================
-- صلاحيات شركات الشحن الجديدة فقط (تشغيل تدريجي على قاعدة موجودة)
--   shipping.companies.manage   — تعديل / حذف / إضافة
--   shipping.companies.statement — كشف حساب شركة أو مندوب
-- ============================================================================
-- قبل التنفيذ: نسخة احتياطية.
-- بعد التنفيذ: php artisan cache:clear  (أو انتظر انتهاء TTL لكاش RBAC)
-- guard الافتراضي = api
-- ============================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @guard := CAST('api' AS CHAR(64) CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 1) إدراج الصلاحيتين في جدول permissions (إن لم تكونا موجودتين)
-- ----------------------------------------------------------------------------
INSERT INTO permissions (name, guard_name, module, slug, description, created_at, updated_at)
SELECT 'Edit and delete shipping companies', @guard, 'shipping', 'shipping.companies.manage', NULL, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM permissions p
    WHERE (p.slug COLLATE utf8mb4_unicode_ci) = 'shipping.companies.manage'
      AND (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
);

INSERT INTO permissions (name, guard_name, module, slug, description, created_at, updated_at)
SELECT 'Shipping company account statement', @guard, 'shipping', 'shipping.companies.statement', NULL, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM permissions p
    WHERE (p.slug COLLATE utf8mb4_unicode_ci) = 'shipping.companies.statement'
      AND (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
);

-- ----------------------------------------------------------------------------
-- 2) ربط الصلاحيات بالأدوار (بدون تكرار صفوف role_has_permissions)
--    عدّل قوائم slug الأدوار حسب بيئتك
-- ----------------------------------------------------------------------------

-- أدوار تحصل على الصلاحيتين (manage + statement)
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard
WHERE (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
  AND (p.slug COLLATE utf8mb4_unicode_ci) IN ('shipping.companies.manage', 'shipping.companies.statement')
  AND (r.slug COLLATE utf8mb4_unicode_ci) IN (
    'super-admin',
    'admin-preset',
    'dept-am-logistics',
    'dept-operation-mgmt',
    'dept-shipping-mgmt',
    'dept-financial'
  )
  AND NOT EXISTS (
    SELECT 1 FROM role_has_permissions rp
    WHERE rp.permission_id = p.id AND rp.role_id = r.id
  );

-- أدوار تحصل على كشف الحساب فقط (بدون تعديل/حذف)
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard
WHERE (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
  AND (p.slug COLLATE utf8mb4_unicode_ci) = 'shipping.companies.statement'
  AND (r.slug COLLATE utf8mb4_unicode_ci) IN ('dept-operation-specialist')
  AND NOT EXISTS (
    SELECT 1 FROM role_has_permissions rp
    WHERE rp.permission_id = p.id AND rp.role_id = r.id
  );

-- dept-admin: يُفضّل إعطاؤه الصلاحيتين يدوياً أو عبر super-admin؛ إن أردت ربطه مباشرة:
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-admin' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard
WHERE (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
  AND (p.slug COLLATE utf8mb4_unicode_ci) IN ('shipping.companies.manage', 'shipping.companies.statement')
  AND NOT EXISTS (
    SELECT 1 FROM role_has_permissions rp
    WHERE rp.permission_id = p.id AND rp.role_id = r.id
  );

-- ----------------------------------------------------------------------------
-- 3) تحقق سريع (اختياري)
-- ----------------------------------------------------------------------------
-- SELECT slug, name, module FROM permissions
-- WHERE slug IN ('shipping.companies.manage', 'shipping.companies.statement') AND guard_name = 'api';
--
-- SELECT r.slug AS role_slug, p.slug AS permission_slug
-- FROM role_has_permissions rp
-- JOIN roles r ON r.id = rp.role_id
-- JOIN permissions p ON p.id = rp.permission_id
-- WHERE p.slug IN ('shipping.companies.manage', 'shipping.companies.statement');
