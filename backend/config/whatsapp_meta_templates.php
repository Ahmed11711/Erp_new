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
      
        // ترتيب العرض: 1) تجهيز 2) تأكيد الطلب 3) تقييم العميل — داخل كل مجموعة عربي ثم إنجليزي.
        // في واجهة Meta يظهر أحياناً كـ «order_confirmation_flow - Arabic» — اسم الـ API فقط: order_confirmation_flow واللغة ar.
        [
            'name' => 'order_confirmation_flow',
            'language' => 'ar',
            'ui_label' => 'تجهيز الطلب بالعربية',
            'body_params' => ['اسم العميل', 'رقم الطلب'],
            'body_param_keys' => ['customer_name', 'id'],
            'phone_number_id' => null,
            'button_ids' => ['confirm_order', 'postpone_order', 'cancel_order'],
        ],
        // order_flow (en) — رمز اللغة في الـ API يجب أن يطابق ما هو معتمد في Meta لنفس حساب واتساب (نفس الرقم/التوكن).
        // إن ظهر 132001 «does not exist in en_US»: أضف سطر api_language_code بالقيمة الفعلية من Meta (مثلاً 'en' أو 'en_GB').
        [
            'name' => 'order_flow',
            'language' => 'en_US',
            'api_language_code' => 'en',
            'ui_label' => 'تجهيز الطلب بالإنجليزية',
            'body_params' => ['اسم العميل', 'رقم الطلب'],
            'body_param_keys' => ['customer_name', 'id'],
            'phone_number_id' => null,
        ],
        // confirm_order — نسختان في Meta (عربي / إنجليزي) لمراحل التجهيز والشحن؛ نفس أسماء الأزرار المدعومة في OrderConfirmationFlowService.
        [
            'name' => 'confirm_order',
            'language' => 'ar',
            'ui_label' => 'تأكيد الطلب بالعربية',
            'body_params' => ['اسم العميل', 'رقم الطلب'],
            'body_param_keys' => ['customer_name', 'id'],
            'phone_number_id' => null,
            'button_ids' => ['confirm_order', 'postpone_order', 'cancel_order'],
        ],
        [
            'name' => 'confirm_order',
            'language' => 'en_US',
            'ui_label' => 'تأكيد الطلب بالإنجليزية',
            // 'api_language_code' => 'en',
            'body_params' => ['اسم العميل', 'رقم الطلب'],
            'body_param_keys' => ['customer_name', 'id'],
            'phone_number_id' => null,
        ],

        // client_review — نسختان في Meta (عربي ثم إنجليزي في الملف؛ العرض يُرتَّب من الواجهة).
        [
            'name' => 'client_review',
            'language' => 'ar',
            'ui_label' => 'تقييم العميل بالعربية',
            'body_params' => ['اسم العميل'],
            'body_param_keys' => ['customer_name'],
            'phone_number_id' => null,
        ],
        [
            'name' => 'client_review',
            'language' => 'en_US',
            'ui_label' => 'تقييم العميل بالإنجليزية',
            'body_params' => ['اسم العميل'],
            'body_param_keys' => ['customer_name'],
            'phone_number_id' => null,
        ],
    ],
];
