-- ============================================================================
-- صلاحية مراجعة طلبات Shopify المستوردة (تشغيل تدريجي على قاعدة موجودة)
--   orders.shopify.review — مراجعة وتأكيد بيانات الطلب + تسجيل المراجع
-- ============================================================================
-- قبل التنفيذ: نسخة احتياطية.
-- بعد التنفيذ: php artisan cache:clear  (أو انتظر انتهاء TTL لكاش RBAC)
-- guard الافتراضي = api
-- ============================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @guard := CAST('api' AS CHAR(64) CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 1) إدراج الصلاحية في جدول permissions (إن لم تكن موجودة)
-- ----------------------------------------------------------------------------
INSERT INTO permissions (name, guard_name, module, slug, description, created_at, updated_at)
SELECT 'Review imported Shopify orders', @guard, 'orders', 'orders.shopify.review', NULL, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM permissions p
    WHERE (p.slug COLLATE utf8mb4_unicode_ci) = 'orders.shopify.review'
      AND (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
);

-- ----------------------------------------------------------------------------
-- 2) ربط بالأدوار الإدارية الأساسية
-- ----------------------------------------------------------------------------
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard
WHERE (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
  AND (p.slug COLLATE utf8mb4_unicode_ci) = 'orders.shopify.review'
  AND (r.slug COLLATE utf8mb4_unicode_ci) IN (
    'super-admin',
    'admin-preset',
    'dept-admin'
  )
  AND NOT EXISTS (
    SELECT 1 FROM role_has_permissions rp
    WHERE rp.permission_id = p.id AND rp.role_id = r.id
  );

-- ----------------------------------------------------------------------------
-- 3) منح الصلاحية تلقائياً لأي دور لديه بالفصل صلاحية Shopify (لوحة أو كامل)
-- ----------------------------------------------------------------------------
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p_new.id, r.id
FROM permissions p_new
INNER JOIN role_has_permissions rp ON rp.role_id = r.id
INNER JOIN permissions p_old ON p_old.id = rp.permission_id
INNER JOIN roles r ON r.id = rp.role_id AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard
WHERE (p_new.guard_name COLLATE utf8mb4_unicode_ci) = @guard
  AND (p_new.slug COLLATE utf8mb4_unicode_ci) = 'orders.shopify.review'
  AND (p_old.slug COLLATE utf8mb4_unicode_ci) IN ('nav.shopify.dashboard', 'nav.shopify')
  AND NOT EXISTS (
    SELECT 1 FROM role_has_permissions rp2
    WHERE rp2.permission_id = p_new.id AND rp2.role_id = r.id
  );

-- ----------------------------------------------------------------------------
-- 4) تحقق سريع (اختياري)
-- ----------------------------------------------------------------------------
-- SELECT slug, name, module FROM permissions
-- WHERE slug = 'orders.shopify.review' AND guard_name = 'api';
--
-- SELECT r.slug AS role_slug, p.slug AS permission_slug
-- FROM role_has_permissions rp
-- JOIN roles r ON r.id = rp.role_id
-- JOIN permissions p ON p.id = rp.permission_id
-- WHERE p.slug = 'orders.shopify.review';
