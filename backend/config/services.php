<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'meta_whatsapp' => [
        'phone_number_id' => env('META_PHONE_NUMBER_ID'),
        'access_token' => env('META_ACCESS_TOKEN'),
        'verify_token' => env('META_VERIFY_TOKEN'),
        'phone_number_id_2' => env('META_PHONE_NUMBER_ID_2'),
        'access_token_2' => env('META_ACCESS_TOKEN_2'),
        'verify_token_2' => env('META_VERIFY_TOKEN_2'),
    ],

    /*
    | Shopify: Webhook secret من تطبيق Shopify (Admin → Apps → your app → API credentials).
    | سجّل Webhook: Topic = orders/create → URL = {APP_URL}/api/shopify/webhook
    | مثال: APP_URL=http://test.mag-opt.com/backend/public → POST …/api/shopify/webhook
    | GET …/api/shopify/webhook يعيد JSON للتحقق من أن الرابط شغال.
    | Admin API: نطاق المتجر my-store.myshopify.com + Admin API access token (صلاحيات read_products على الأقل للمزامنة).
    */
    'shopify' => [
        'shop_domain' => env('SHOPIFY_SHOP_DOMAIN'),
        'admin_access_token' => env('SHOPIFY_ADMIN_ACCESS_TOKEN'),
        'api_version' => env('SHOPIFY_API_VERSION', '2024-10'),
        'http_timeout' => (int) env('SHOPIFY_HTTP_TIMEOUT', 120),
        'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET'),
        'allowed_shop_domains' => array_values(array_filter(array_map('trim', explode(',', (string) env('SHOPIFY_ALLOWED_SHOP_DOMAINS', ''))))),
        'accepted_topics' => [
            'orders/create',
            'orders/updated',
            'products/create',
            'products/update',
            'inventory_levels/update',
        ],
        'default_order_source_id' => (int) env('SHOPIFY_DEFAULT_ORDER_SOURCE_ID', 0),
        'default_shipping_method_id' => (int) env('SHOPIFY_DEFAULT_SHIPPING_METHOD_ID', 0),
        'fallback_category_id' => (int) env('SHOPIFY_FALLBACK_CATEGORY_ID', 0),
        'default_governorate' => env('SHOPIFY_DEFAULT_GOVERNORATE', 'غير محدد'),
        'default_address' => env('SHOPIFY_DEFAULT_ADDRESS', '-'),
        'default_customer_type' => env('SHOPIFY_DEFAULT_CUSTOMER_TYPE', 'فرد'),
        'default_customer_name' => env('SHOPIFY_DEFAULT_CUSTOMER_NAME', 'عميل Shopify'),
        'order_type' => env('SHOPIFY_ORDER_TYPE', 'جديد'),
        'placeholder_phone' => env('SHOPIFY_PLACEHOLDER_PHONE', '0000000000'),
        'tracking_user_id' => ($uid = env('SHOPIFY_TRACKING_USER_ID')) !== null && $uid !== ''
            ? (int) $uid
            : null,
    ],

    'shipping_partner' => [
        'api_base_url' => rtrim((string) env('SHIPPING_API_BASE_URL', ''), '/'),
        'api_token' => env('SHIPPING_API_TOKEN'),
        'webhook_secret' => env('SHIPPING_WEBHOOK_SECRET'),
        'timeout' => (int) env('SHIPPING_API_TIMEOUT', 60),
    ],

];
