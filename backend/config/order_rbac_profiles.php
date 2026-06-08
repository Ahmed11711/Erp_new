<?php

/**
 * ملفات تعريف لمجموعات مسارات الشحن/الطلبات:
 * departments = الأقسام السابقة كما في api.php
 * permissions = أي slug يكفي للسماح عند عدم تطابق القسم (canAny)
 */
return [
    'ship_collect' => [
        'departments' => ['Admin', 'Operation Management', 'Operation Specialist', 'Logistics Specialist'],
        'permissions' => ['orders.change_status', 'orders.assign_driver', 'orders.view'],
    ],
    'part_shipment' => [
        'departments' => ['Admin', 'Operation Management'],
        'permissions' => ['orders.edit', 'orders.change_status', 'orders.view'],
    ],
    'orders_create' => [
        'departments' => ['Admin', 'Data Entry'],
        'permissions' => ['orders.create', 'orders.view'],
    ],
    'companies_main' => [
        'departments' => ['Admin', 'Operation Management', 'Account Management', 'Logistics Specialist', 'Financial Accounts', 'Data Entry'],
        'permissions' => ['orders.view', 'finance.edit', 'customer_companies.view', 'customer_companies.manage'],
    ],
    'companies_balance' => [
        'departments' => ['Admin', 'Operation Management', 'Account Management', 'Logistics Specialist', 'Financial Accounts'],
        'permissions' => ['orders.view', 'finance.view', 'finance.edit', 'customer_companies.view', 'customer_companies.manage', 'customer_companies.statement', 'customer_companies.collect'],
    ],
    'edit_order' => [
        'departments' => ['Admin', 'Operation Management', 'Shipping Management'],
        'permissions' => ['orders.edit', 'orders.view'],
    ],
    'confirm_order' => [
        'departments' => ['Admin', 'Operation Management', 'Shipping Management', 'Customer Service'],
        'permissions' => ['orders.change_status', 'orders.view'],
    ],
    'refuse_maintain' => [
        'departments' => ['Admin', 'Operation Management', 'Operation Specialist', 'Logistics Specialist', 'Shipping Management'],
        'permissions' => ['orders.change_status', 'orders.view'],
    ],
    'postpone_order' => [
        'departments' => ['Admin', 'Operation Management', 'Operation Specialist', 'Logistics Specialist', 'Shipping Management'],
        'permissions' => ['orders.change_status'],
    ],
    'shipping_company_crud' => [
        'departments' => ['Admin', 'Operation Management', 'Operation Specialist', 'Account Management', 'Logistics Specialist', 'Shipping Management'],
        'permissions' => ['nav.shipping.master', 'orders.view', 'system.rbac'],
    ],
    'shipping_company_manage' => [
        'departments' => ['Admin', 'Operation Management', 'Account Management', 'Logistics Specialist', 'Shipping Management'],
        'permissions' => ['shipping.companies.manage', 'collection_companies.manage', 'nav.shipping.master', 'system.rbac'],
    ],
    'collection_settlements' => [
        'departments' => ['Admin', 'Financial Accounts', 'Account Management', 'Logistics Specialist'],
        'permissions' => ['settlements.manage', 'finance.view', 'nav.shipping.accounts_report', 'system.rbac'],
    ],
    'shipping_company_statement' => [
        'departments' => ['Admin', 'Operation Management', 'Operation Specialist', 'Account Management', 'Logistics Specialist', 'Shipping Management', 'Financial Accounts'],
        'permissions' => ['shipping.companies.statement', 'nav.shipping.master', 'orders.view', 'system.rbac'],
    ],
    'change_status' => [
        'departments' => ['Admin', 'Operation Management', 'Operation Specialist', 'Logistics Specialist', 'Shipping Management', 'Data Entry'],
        'permissions' => ['orders.change_status', 'orders.view'],
    ],
    'vip_shortage' => [
        'departments' => ['Admin', 'Data Entry', 'Shipping Management', 'Customer Service'],
        'permissions' => ['orders.view'],
    ],
    'offer_crud' => [
        'departments' => ['Admin', 'Data Entry', 'Shipping Management', 'Customer Service', 'Corparates'],
        'permissions' => ['nav.receipts.quotes', 'orders.view'],
    ],
    'review_temp' => [
        'departments' => ['Admin', 'Data Entry', 'Review Management'],
        'permissions' => ['notifications.review_filter', 'orders.view', 'system.rbac'],
    ],
    'add_note' => [
        'departments' => ['Admin', 'Operation Management', 'Operation Specialist', 'Shipping Management', 'Data Entry', 'Account Management', 'Logistics Specialist', 'Customer Service'],
        'permissions' => ['orders.edit', 'orders.view'],
    ],
];
