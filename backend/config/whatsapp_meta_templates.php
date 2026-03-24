<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Meta WhatsApp approved template names (created in Meta Business Manager)
    | Use the exact template name as in Meta. body_params = labels for {{1}}, {{2}}...
    | phone_number_id: optional - filter templates by WhatsApp number (null = all numbers)
    |--------------------------------------------------------------------------
    */
    'templates' => [
        [
            'name' => 'order_update',
            'language' => 'ar',
            'body_params' => ['اسم العميل', 'رقم الطلب', 'حالة الطلب'],
            'body_param_keys' => ['customer_name', 'id', 'order_status'],
            'phone_number_id' => null,
        ],
        [
            'name' => 'order_confirmation',
            'language' => 'en',
            'body_params' => ['اسم العميل', 'رقم الطلب', 'المبلغ الإجمالي'],
            'body_param_keys' => ['customer_name', 'id', 'net_total'],
            'phone_number_id' => null,
        ],
        [
            'name' => 'order_confirmation_flow',
            'language' => 'ar',
            'body_params' => ['اسم العميل', 'رقم الطلب'],
            'body_param_keys' => ['customer_name', 'id'],
            'phone_number_id' => null,
            'button_ids' => ['confirm_order', 'postpone_order', 'cancel_order'],
        ],
        [
            'name' => 'hello_world',
            'language' => 'ar',
            'body_params' => [],
            'body_param_keys' => [],
            'phone_number_id' => null,
        ],
        [
            'name' => 'review_request',
            'language' => 'ar',
            'body_params' => ['اسم العميل'],
            'body_param_keys' => ['customer_name'],
            'phone_number_id' => null,
        ],
    ],
];
