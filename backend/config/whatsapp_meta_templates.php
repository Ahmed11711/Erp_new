<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Meta WhatsApp approved template names (created in Meta Business Manager)
    | Use the exact template name as in Meta. body_params = labels for {{1}}, {{2}}...
    |--------------------------------------------------------------------------
    */
    'templates' => [
        [
            'name' => 'order_update',
            'language' => 'ar',
            'body_params' => ['اسم العميل', 'رقم الطلب', 'حالة الطلب'],
            'body_param_keys' => ['customer_name', 'id', 'order_status'],
        ],
        [
            'name' => 'order_confirmation',
            'language' => 'en',
            'body_params' => ['اسم العميل', 'رقم الطلب', 'المبلغ الإجمالي'],
            'body_param_keys' => ['customer_name', 'id', 'net_total'],
        ],
        [
            'name' => 'hello_world',
            'language' => 'ar',
            'body_params' => [],
            'body_param_keys' => [],
        ],
    ],
];
