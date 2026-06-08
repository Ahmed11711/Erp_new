-- ============================================================================
-- ربط أقسام المستخدمين (users.department) بأدوار Spatie + الصلاحيات
-- ============================================================================
-- قبل التنفيذ:
--   1) اعمل نسخة احتياطية من قاعدة البيانات.
--   2) نفّذ أولاً (من مشروع Laravel): php artisan db:seed --class=RbacFoundationSeeder
--      حتى تُملأ جدول permissions بالـ slugs المستخدمة هنا.
--   3) تأكد أن guard الصلاحيات والأدوار = api (كما في config/auth.php عندك).
--
-- ماذا يفعل هذا السكربت؟
--   • ينشئ أدواراً بأسماء dept-* ويربط كل دور بمجموعة صلاحيات (من جدول permissions).
--   • يملأ department_role_templates بحيث أي مستخدم يملك department مطابقاً
--     يأخذ صلاحيات ذلك الدور تلقائياً (بدون إدراج صف في model_has_roles لكل مستخدم).
--
-- ملاحظات مهمة:
--   • يجب أن تطابق القيم في عمود department تماماً ما في users.department (حسّاس لمسافات).
--   • القائمة القديمة في الواجهة كانت أدقّ من مجموعات الصلاحيات الحالية؛ تم تقريب
--     الصلاحيات حسب أقسامك الشائعة — عدّل قوائم p.slug IN (...) حسب احتياجك.
--   • بعد التنفيذ: php artisan cache:clear (أو انتظر انتهاء TTL للـ RBAC cache).
-- ============================================================================

-- توحيد ترميز الاتصال مع أعمدة Laravel الشائعة (utf8mb4_unicode_ci) وتجنب #1267 مع general_ci
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @guard := CAST('api' AS CHAR(64) CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 1) إنشاء الأدوار (مرة واحدة؛ لا يكرر إن وُجد slug نفسه)
-- ملاحظة: تجنب SELECT * مع عمودين NOW() — MySQL يسميهما بنفس الاسم فيخطأ #1060.
-- ----------------------------------------------------------------------------
INSERT INTO roles (name, guard_name, slug, description, created_at, updated_at)
SELECT 'Department role: Admin', @guard, 'dept-admin', 'Legacy menu ~ Admin', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-admin' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard);

INSERT INTO roles (name, guard_name, slug, description, created_at, updated_at)
SELECT 'Department role: Account / Logistics', @guard, 'dept-am-logistics', 'Legacy: Account Management + Logistics Specialist', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-am-logistics' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard);

INSERT INTO roles (name, guard_name, slug, description, created_at, updated_at)
SELECT 'Department role: Data Entry', @guard, 'dept-data-entry', 'Legacy: Data Entry', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-data-entry' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard);

INSERT INTO roles (name, guard_name, slug, description, created_at, updated_at)
SELECT 'Department role: Customer Service', @guard, 'dept-customer-service', 'Legacy: Customer Service', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-customer-service' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard);

INSERT INTO roles (name, guard_name, slug, description, created_at, updated_at)
SELECT 'Department role: Financial Accounts', @guard, 'dept-financial', 'Legacy: Financial Accounts', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-financial' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard);

INSERT INTO roles (name, guard_name, slug, description, created_at, updated_at)
SELECT 'Department role: Operation Management', @guard, 'dept-operation-mgmt', 'Legacy: Operation Management (+ HR في القائمة القديمة)', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-operation-mgmt' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard);

INSERT INTO roles (name, guard_name, slug, description, created_at, updated_at)
SELECT 'Department role: Finance & Operations', @guard, 'dept-finance-ops', 'Legacy: Finance and operations management', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-finance-ops' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard);

INSERT INTO roles (name, guard_name, slug, description, created_at, updated_at)
SELECT 'Department role: Shipping Management', @guard, 'dept-shipping-mgmt', 'Legacy: Shipping Management', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-shipping-mgmt' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard);

INSERT INTO roles (name, guard_name, slug, description, created_at, updated_at)
SELECT 'Department role: Operation Specialist', @guard, 'dept-operation-specialist', 'Legacy: Operation Specialist', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-operation-specialist' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard);

INSERT INTO roles (name, guard_name, slug, description, created_at, updated_at)
SELECT 'Department role: Review Management', @guard, 'dept-review-mgmt', 'Legacy: Review Management', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-review-mgmt' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard);

INSERT INTO roles (name, guard_name, slug, description, created_at, updated_at)
SELECT 'Department role: Corporate Sales', @guard, 'dept-corporate', 'Legacy: Corparates', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-corporate' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard);

-- ----------------------------------------------------------------------------
-- 2) إزالة صلاحيات الأدوار dept-* فقط (لإعادة المزامنة بأمان)
-- ----------------------------------------------------------------------------
DELETE rhp FROM role_has_permissions rhp
INNER JOIN roles r ON r.id = rhp.role_id
WHERE (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard AND (r.slug COLLATE utf8mb4_unicode_ci) LIKE 'dept-%';

-- ----------------------------------------------------------------------------
-- 3) حزم الصلاحيات (مطابقة تقريبية للقائمة القديمة ضمن slugs الموجودة في المشروع)
-- ----------------------------------------------------------------------------

-- dept-admin ≈ admin-preset كامل + صلاحيات إضافية منجدول الصلاحيات
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-admin' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard
WHERE (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
  AND (p.slug COLLATE utf8mb4_unicode_ci) IN (
    'orders.view','orders.create','orders.edit','orders.delete','orders.change_status','orders.export','orders.assign_driver',
    'finance.view','finance.create','finance.edit','finance.delete','finance.approve',
    'employees.view','employees.create','employees.edit','employees.delete','employees.attendance','employees.salary',
    'inventory.view','inventory.create','inventory.edit','inventory.delete','inventory.transfer',
    'settings.view','settings.edit',
    'system.rbac',
    'categories.view','categories.manage',
    'suppliers.view','purchases.view','manufacturing.view',
    'nav.receipts','nav.receipts.quotes','nav.receipts.admin',
    'nav.corporate','nav.shopify','nav.shipping.master','nav.whatsapp_chat',
    'notifications.review_filter',
    'whatsapp.assign_numbers'
  );

-- Account Management + Logistics Specialist: نفس حزمة admin-preset بدون إدارة RBAC
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-am-logistics' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard
WHERE (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
  AND (p.slug COLLATE utf8mb4_unicode_ci) IN (
    'orders.view','orders.create','orders.edit','orders.change_status','orders.export',
    'finance.view','finance.edit',
    'employees.view','employees.create','employees.attendance',
    'inventory.view','inventory.transfer',
    'settings.view',
    'categories.view','categories.manage',
    'suppliers.view','purchases.view','manufacturing.view',
    'nav.receipts','nav.receipts.quotes','nav.receipts.admin',
    'nav.corporate','nav.shopify','nav.shipping.master','nav.whatsapp_chat',
    'shipping.companies.manage','shipping.companies.statement',
    'notifications.review_filter',
    'whatsapp.assign_numbers'
  );

-- Data Entry
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-data-entry' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard
WHERE (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
  AND (p.slug COLLATE utf8mb4_unicode_ci) IN (
    'orders.view','orders.create',
    'categories.view',
    'nav.shopify'
  );

-- Customer Service
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-customer-service' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard
WHERE (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
  AND (p.slug COLLATE utf8mb4_unicode_ci) IN (
    'orders.view','orders.edit','orders.change_status',
    'categories.view',
    'nav.receipts','nav.receipts.quotes',
    'nav.shopify'
  );

-- Financial Accounts: تقريباً حزمة تشغيل بدون HR وبدون system.rbac
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-financial' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard
WHERE (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
  AND (p.slug COLLATE utf8mb4_unicode_ci) IN (
    'orders.view','orders.create','orders.edit','orders.change_status','orders.export',
    'finance.view','finance.edit','finance.create','finance.approve',
    'inventory.view','inventory.transfer',
    'settings.view',
    'categories.view','categories.manage',
    'suppliers.view','purchases.view','manufacturing.view',
    'nav.receipts','nav.receipts.quotes','nav.receipts.admin',
    'nav.shopify','nav.shipping.master','nav.shipping.accounts_report','nav.whatsapp_chat',
    'shipping.companies.manage','shipping.companies.statement',
    'notifications.review_filter'
  );

-- Operation Management (+ HR في القائمة القديمة)
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-operation-mgmt' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard
WHERE (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
  AND (p.slug COLLATE utf8mb4_unicode_ci) IN (
    'employees.view','employees.create','employees.edit','employees.delete','employees.attendance','employees.salary',
    'orders.view','orders.create','orders.edit','orders.change_status','orders.export',
    'finance.view',
    'categories.view',
    'nav.shipping.master','nav.shopify','nav.receipts','nav.receipts.quotes',
    'shipping.companies.manage','shipping.companies.statement'
  );

-- Finance and operations management
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-finance-ops' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard
WHERE (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
  AND (p.slug COLLATE utf8mb4_unicode_ci) IN (
    'orders.view','orders.edit','orders.change_status','orders.export',
    'finance.view','finance.edit',
    'categories.view',
    'nav.shipping.master','nav.shopify'
  );

-- Shipping Management
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-shipping-mgmt' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard
WHERE (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
  AND (p.slug COLLATE utf8mb4_unicode_ci) IN (
    'orders.view','orders.change_status','orders.export',
    'nav.shipping.master','nav.shopify','nav.corporate',
    'shipping.companies.manage','shipping.companies.statement'
  );

-- Operation Specialist
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-operation-specialist' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard
WHERE (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
  AND (p.slug COLLATE utf8mb4_unicode_ci) IN (
    'orders.view','orders.change_status',
    'categories.view',
    'nav.shipping.master','nav.shopify',
    'shipping.companies.statement'
  );

-- Review Management
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-review-mgmt' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard
WHERE (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
  AND (p.slug COLLATE utf8mb4_unicode_ci) IN (
    'orders.view','orders.change_status',
    'notifications.review_filter'
  );

-- Corparates (كما في الواجهة)
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON (r.slug COLLATE utf8mb4_unicode_ci) = 'dept-corporate' AND (r.guard_name COLLATE utf8mb4_unicode_ci) = @guard
WHERE (p.guard_name COLLATE utf8mb4_unicode_ci) = @guard
  AND (p.slug COLLATE utf8mb4_unicode_ci) IN (
    'nav.corporate',
    'orders.view'
  );

-- ----------------------------------------------------------------------------
-- 4) ربط department → role (الجدول الذي يقرأه PermissionResolutionService)
-- ----------------------------------------------------------------------------
INSERT INTO department_role_templates (department, role_id, sample_users_count, created_at, updated_at)
SELECT 'Admin', id, 0, NOW(), NOW() FROM roles WHERE (slug COLLATE utf8mb4_unicode_ci) = 'dept-admin' AND (guard_name COLLATE utf8mb4_unicode_ci) = @guard
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), updated_at = VALUES(updated_at);

INSERT INTO department_role_templates (department, role_id, sample_users_count, created_at, updated_at)
SELECT 'Account Management', id, 0, NOW(), NOW() FROM roles WHERE (slug COLLATE utf8mb4_unicode_ci) = 'dept-am-logistics' AND (guard_name COLLATE utf8mb4_unicode_ci) = @guard
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), updated_at = VALUES(updated_at);

INSERT INTO department_role_templates (department, role_id, sample_users_count, created_at, updated_at)
SELECT 'Logistics Specialist', id, 0, NOW(), NOW() FROM roles WHERE (slug COLLATE utf8mb4_unicode_ci) = 'dept-am-logistics' AND (guard_name COLLATE utf8mb4_unicode_ci) = @guard
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), updated_at = VALUES(updated_at);

INSERT INTO department_role_templates (department, role_id, sample_users_count, created_at, updated_at)
SELECT 'Data Entry', id, 0, NOW(), NOW() FROM roles WHERE (slug COLLATE utf8mb4_unicode_ci) = 'dept-data-entry' AND (guard_name COLLATE utf8mb4_unicode_ci) = @guard
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), updated_at = VALUES(updated_at);

INSERT INTO department_role_templates (department, role_id, sample_users_count, created_at, updated_at)
SELECT 'Customer Service', id, 0, NOW(), NOW() FROM roles WHERE (slug COLLATE utf8mb4_unicode_ci) = 'dept-customer-service' AND (guard_name COLLATE utf8mb4_unicode_ci) = @guard
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), updated_at = VALUES(updated_at);

INSERT INTO department_role_templates (department, role_id, sample_users_count, created_at, updated_at)
SELECT 'Financial Accounts', id, 0, NOW(), NOW() FROM roles WHERE (slug COLLATE utf8mb4_unicode_ci) = 'dept-financial' AND (guard_name COLLATE utf8mb4_unicode_ci) = @guard
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), updated_at = VALUES(updated_at);

INSERT INTO department_role_templates (department, role_id, sample_users_count, created_at, updated_at)
SELECT 'Operation Management', id, 0, NOW(), NOW() FROM roles WHERE (slug COLLATE utf8mb4_unicode_ci) = 'dept-operation-mgmt' AND (guard_name COLLATE utf8mb4_unicode_ci) = @guard
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), updated_at = VALUES(updated_at);

INSERT INTO department_role_templates (department, role_id, sample_users_count, created_at, updated_at)
SELECT 'Finance and operations management', id, 0, NOW(), NOW() FROM roles WHERE (slug COLLATE utf8mb4_unicode_ci) = 'dept-finance-ops' AND (guard_name COLLATE utf8mb4_unicode_ci) = @guard
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), updated_at = VALUES(updated_at);

INSERT INTO department_role_templates (department, role_id, sample_users_count, created_at, updated_at)
SELECT 'Shipping Management', id, 0, NOW(), NOW() FROM roles WHERE (slug COLLATE utf8mb4_unicode_ci) = 'dept-shipping-mgmt' AND (guard_name COLLATE utf8mb4_unicode_ci) = @guard
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), updated_at = VALUES(updated_at);

INSERT INTO department_role_templates (department, role_id, sample_users_count, created_at, updated_at)
SELECT 'Operation Specialist', id, 0, NOW(), NOW() FROM roles WHERE (slug COLLATE utf8mb4_unicode_ci) = 'dept-operation-specialist' AND (guard_name COLLATE utf8mb4_unicode_ci) = @guard
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), updated_at = VALUES(updated_at);

INSERT INTO department_role_templates (department, role_id, sample_users_count, created_at, updated_at)
SELECT 'Review Management', id, 0, NOW(), NOW() FROM roles WHERE (slug COLLATE utf8mb4_unicode_ci) = 'dept-review-mgmt' AND (guard_name COLLATE utf8mb4_unicode_ci) = @guard
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), updated_at = VALUES(updated_at);

INSERT INTO department_role_templates (department, role_id, sample_users_count, created_at, updated_at)
SELECT 'Corparates', id, 0, NOW(), NOW() FROM roles WHERE (slug COLLATE utf8mb4_unicode_ci) = 'dept-corporate' AND (guard_name COLLATE utf8mb4_unicode_ci) = @guard
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), updated_at = VALUES(updated_at);

-- ----------------------------------------------------------------------------
-- 5) تحقق سريع (اختياري)
-- ----------------------------------------------------------------------------
-- SELECT d.department, r.slug AS role_slug, COUNT(rhp.permission_id) AS perm_count
-- FROM department_role_templates d
-- JOIN roles r ON r.id = d.role_id
-- LEFT JOIN role_has_permissions rhp ON rhp.role_id = r.id
-- GROUP BY d.department, r.slug
-- ORDER BY d.department;
