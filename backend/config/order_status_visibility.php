<?php

/**
 * ربط حالات الطلب بصلاحيات RBAC (orders.view_status.*).
 *
 * المنطق وقت التشغيل قائم بالكامل على الصلاحيات:
 *   - المستخدم يرى الطلب فقط إذا كان يملك صلاحية حالته (orders.view_status.<key>).
 *   - لا توجد استثناءات حسب القسم؛ حتى Super Admin يخضع لمربعات الصلاحيات
 *     (دوره مزروع بكل الحالات افتراضياً ويمكن إلغاء أيٍّ منها من شاشة الأدوار).
 *   - super_admin_emails في config/rbac.php تبقى مَخرج طوارئ يتجاوز الفلترة.
 *
 * department_default_status_keys تُستخدم وقت التهيئة (Seeder) فقط لمنح كل قسم
 * حالاته الافتراضية على دوره، ولا تُقرأ وقت التشغيل.
 */
return [
    'statuses' => [
        'new' => [
            'permission' => 'orders.view_status.new',
            'name' => 'View New Orders',
            'labels' => ['طلب جديد', 'جديد'],
            'filter_value' => 'طلب جديد',
            'filter_label' => 'جديد',
        ],
        'confirmed' => [
            'permission' => 'orders.view_status.confirmed',
            'name' => 'View Confirmed Orders',
            'labels' => ['طلب مؤكد'],
            'filter_value' => 'طلب مؤكد',
            'filter_label' => 'طلب مؤكد',
        ],
        'partial_ship' => [
            'permission' => 'orders.view_status.partial_ship',
            'name' => 'View Partially Shipped Orders',
            // يشمل «تسليم جزئي» حتى تظهر الطلبات المفتوحة لاستكمال الشحن بدون صلاحية منفصلة
            'labels' => ['شحن جزئي', 'تسليم جزئي'],
            'filter_value' => 'شحن جزئي',
            'filter_label' => 'شحن جزئي',
        ],
        'partial_deliver' => [
            'permission' => 'orders.view_status.partial_deliver',
            'name' => 'View Partially Delivered Orders',
            'labels' => ['تسليم جزئي'],
            'filter_value' => 'تسليم جزئي',
            'filter_label' => 'تسليم جزئي',
        ],
        'shipped' => [
            'permission' => 'orders.view_status.shipped',
            'name' => 'View Shipped Orders',
            'labels' => ['تم شحن'],
            'filter_value' => 'تم شحن',
            'filter_label' => 'تم شحن',
        ],
        'delivered' => [
            'permission' => 'orders.view_status.delivered',
            'name' => 'View Delivered Orders',
            'labels' => ['تم التسليم'],
            'filter_value' => 'تم التسليم',
            'filter_label' => 'تم التسليم',
        ],
        'received' => [
            'permission' => 'orders.view_status.received',
            'name' => 'View Received Orders',
            'labels' => ['تم الاستلام'],
            'filter_value' => 'تم الاستلام',
            'filter_label' => 'تم الاستلام',
        ],
        'collected' => [
            'permission' => 'orders.view_status.collected',
            'name' => 'View Collected Orders',
            'labels' => ['تم التحصيل'],
            'filter_value' => 'تم التحصيل',
            'filter_label' => 'تم التحصيل',
        ],
        'postponed' => [
            'permission' => 'orders.view_status.postponed',
            'name' => 'View Postponed Orders',
            'labels' => ['مؤجل'],
            'filter_value' => 'مؤجل',
            'filter_label' => 'مؤجل',
        ],
        'archived' => [
            'permission' => 'orders.view_status.archived',
            'name' => 'View Archived Orders',
            'labels' => ['أرشيف'],
            'filter_value' => 'أرشيف',
            'filter_label' => 'أرشيف',
        ],
        'maintained' => [
            'permission' => 'orders.view_status.maintained',
            'name' => 'View Maintained Orders',
            'labels' => ['تم الصيانة'],
            'filter_value' => 'تم الصيانة',
            'filter_label' => 'تم الصيانة',
        ],
        'refused' => [
            'permission' => 'orders.view_status.refused',
            'name' => 'View Refused Orders',
            'labels' => ['رفض استلام'],
            'filter_value' => 'رفض استلام',
            'filter_label' => 'رفض استلام',
        ],
        'cancelled' => [
            'permission' => 'orders.view_status.cancelled',
            'name' => 'View Cancelled Orders',
            'labels' => ['ملغي'],
            'filter_value' => 'ملغي',
            'filter_label' => 'ملغي',
        ],
    ],

    /**
     * حالات افتراضية لكل قسم — تُزرع على دور القسم وقت التهيئة فقط.
     * '*' تعني كل الحالات.
     *
     * @var array<string, string|list<string>>
     */
    'department_default_status_keys' => [
        // «طلب جديد» (new) مقصورة على خدمة العملاء فقط — لا تُمنح لأي قسم آخر افتراضياً.
        'Admin' => ['confirmed', 'partial_ship', 'partial_deliver', 'shipped', 'delivered', 'received', 'collected', 'postponed', 'archived', 'maintained', 'refused', 'cancelled'],
        'Customer Service' => '*',
        'Data Entry' => ['confirmed', 'partial_ship', 'partial_deliver', 'shipped', 'delivered', 'received', 'collected', 'postponed', 'archived', 'maintained', 'refused', 'cancelled'],
        'Account Management' => ['confirmed', 'partial_ship', 'partial_deliver', 'shipped', 'delivered', 'received', 'collected', 'postponed', 'archived', 'maintained', 'refused', 'cancelled'],
        'Finance and operations management' => ['confirmed', 'partial_ship', 'partial_deliver', 'shipped', 'delivered', 'received', 'collected', 'postponed', 'archived', 'maintained', 'refused', 'cancelled'],
        'Corparates' => ['confirmed', 'partial_ship', 'partial_deliver', 'shipped', 'delivered', 'received', 'collected', 'postponed', 'archived', 'maintained', 'refused', 'cancelled'],
        'Employee' => ['confirmed', 'partial_ship', 'partial_deliver', 'shipped', 'delivered', 'received', 'collected', 'postponed', 'archived', 'maintained', 'refused', 'cancelled'],
        'Operation Management' => ['confirmed', 'partial_ship', 'partial_deliver', 'shipped', 'delivered', 'received', 'postponed', 'maintained', 'refused'],
        'Operation Specialist' => ['confirmed', 'partial_ship', 'partial_deliver', 'shipped', 'delivered', 'received', 'postponed', 'maintained', 'refused'],
        'Logistics Specialist' => ['confirmed', 'partial_ship', 'partial_deliver', 'shipped', 'delivered', 'received', 'postponed', 'maintained', 'refused'],
        'Shipping Management' => ['confirmed', 'partial_ship', 'partial_deliver', 'shipped', 'delivered', 'received', 'collected', 'postponed', 'maintained', 'refused', 'cancelled'],
        'Review Management' => ['shipped', 'delivered', 'collected', 'postponed', 'cancelled', 'refused'],
        'Financial Accounts' => ['shipped', 'partial_ship', 'partial_deliver', 'collected', 'received', 'delivered'],
    ],
];
