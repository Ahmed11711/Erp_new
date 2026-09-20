-- ============================================================================
-- صلاحيات عملاء الشركات (تشغيل تدريجي على قاعدة موجودة)
--   customer_companies.view       — عرض قائمة عملاء الشركات
--   customer_companies.manage     — إضافة / تعديل / ربط بالحساب
--   customer_companies.statement  — كشف حساب عميل شركة
--   customer_companies.collect    — تحصيل من عميل شركة
-- ============================================================================
-- قبل التنفيذ: نسخة احتياطية.
-- بعد التنفيذ: php artisan cache:clear
-- guard الافتراضي = api
-- ============================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @guard := CAST('api' AS CHAR(64) CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci;

INSERT INTO permissions (name, guard_name, module, slug, description, created_at, updated_at)
SELECT 'View customer companies list', @guard, 'customers', 'customer_companies.view', NULL, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM permissions p
    WHERE (p.slug COLLATE utf8mb4_unicode_ci) = 'customer_companies.view'
      AND (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
);

INSERT INTO permissions (name, guard_name, module, slug, description, created_at, updated_at)
SELECT 'Add, edit and link customer companies', @guard, 'customers', 'customer_companies.manage', NULL, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM permissions p
    WHERE (p.slug COLLATE utf8mb4_unicode_ci) = 'customer_companies.manage'
      AND (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
);

INSERT INTO permissions (name, guard_name, module, slug, description, created_at, updated_at)
SELECT 'Customer company account statement', @guard, 'customers', 'customer_companies.statement', NULL, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM permissions p
    WHERE (p.slug COLLATE utf8mb4_unicode_ci) = 'customer_companies.statement'
      AND (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
);

INSERT INTO permissions (name, guard_name, module, slug, description, created_at, updated_at)
SELECT 'Collect from customer company', @guard, 'customers', 'customer_companies.collect', NULL, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM permissions p
    WHERE (p.slug COLLATE utf8mb4_unicode_ci) = 'customer_companies.collect'
      AND (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
);

-- أدوار تحصل على كل صلاحيات عملاء الشركات
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard
WHERE (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
  AND (p.slug COLLATE utf8mb4_unicode_ci) IN (
    'customer_companies.view',
    'customer_companies.manage',
    'customer_companies.statement',
    'customer_companies.collect'
  )
  AND (r.slug COLLATE utf8mb4_unicode_ci) IN (
    'super-admin',
    'admin-preset',
    'dept-am-logistics',
    'dept-operation-mgmt',
    'dept-financial',
    'dept-finance-ops',
    'dept-shipping-mgmt'
  )
  AND NOT EXISTS (
    SELECT 1 FROM role_has_permissions rp
    WHERE rp.permission_id = p.id AND rp.role_id = r.id
  );

-- كشف حساب فقط (بدون إدارة)
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard
WHERE (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
  AND (p.slug COLLATE utf8mb4_unicode_ci) IN ('customer_companies.view', 'customer_companies.statement')
  AND (r.slug COLLATE utf8mb4_unicode_ci) IN ('dept-operation-specialist')
  AND NOT EXISTS (
    SELECT 1 FROM role_has_permissions rp
    WHERE rp.permission_id = p.id AND rp.role_id = r.id
  );

-- dept-admin
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-admin' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard
WHERE (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
  AND (p.slug COLLATE utf8mb4_unicode_ci) IN (
    'customer_companies.view',
    'customer_companies.manage',
    'customer_companies.statement',
    'customer_companies.collect'
  )
  AND NOT EXISTS (
    SELECT 1 FROM role_has_permissions rp
    WHERE rp.permission_id = p.id AND rp.role_id = r.id
  );
