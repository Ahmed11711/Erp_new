<?php

// أساس الروابط العامة للوسائط: Meta تجلب صورة الـ header من هذا الرابط (يجب أن يكون https ومتاحاً من الإنترنت).
// إذا كان APP_URL=localhost لكن التطبيق يُخدم من دومين حقيقي، عيّن WHATSAPP_META_MEDIA_BASE_URL=https://your-domain.com
$mediaBase = rtrim((string) env('WHATSAPP_META_MEDIA_BASE_URL', env('APP_URL', '')), '/');

return [
    /*
    |--------------------------------------------------------------------------
    | Meta WhatsApp approved template names (created in Meta Business Manager)
    | Use the exact template name as in Meta. body_params = labels for {{1}}, {{2}}...
    | phone_number_id: optional - filter templates by WhatsApp number (null = all numbers)
    |
    | header_format:
    |   "image" — الـ header في Meta متغير (صورة من رابط عند الإرسال)؛ الرابط يُبنى من media_base_url + مسار تحت public/images.
    |   "omit" — فقط إذا كان الـ header في القالب المعتمد بدون متغير (صورة/نص ثابت بالكامل في Meta). إن ظهر 132012 expected IMAGE فالقالب يحتاج "image".
    |   "text" — متغير نصي في الـ header.
    | default_header_image_url: media_base_url + /images/whatsapp-meta-default.png (الملف في public/images).
    | review_feedback_header_image_url: media_base_url + /images/whatsapp-meta-review-feedback-header.jpeg
    | ملاحظة: قبول Graph API (wamid) لا يعني أن الرسالة وصلت — إن كان رابط الصورة غير قابل للجلب من خوادم Meta تفشل التسليم (راجع webhook statuses failed).
    |--------------------------------------------------------------------------
    */
    'media_base_url' => $mediaBase,

    'default_header_image_url' => $mediaBase !== '' ? $mediaBase . '/images/whatsapp-meta-default.png' : '',

    'review_feedback_header_image_url' => $mediaBase !== '' ? $mediaBase . '/images/whatsapp-meta-review-feedback-header.jpeg' : '',

    'templates' => [
      
        // ترتيب العرض: 1) تجهيز 2) تأكيد الطلب 3) تقييم العميل 4) فيد باك — داخل كل مجموعة عربي ثم إنجليزي.
        // في واجهة Meta يظهر أحياناً كـ «order_confirmation_flow - Arabic» — اسم الـ API فقط: order_confirmation_flow واللغة ar.
        [
            'name' => 'order_confirmation_flow',
            'language' => 'ar',
            'ui_label' => 'تجهيز الطلب بالعربية',
            // يطابق القالب في Meta: بدون header (نص فقط). image يسبب 132018 "no parameters allowed".
            'header_format' => 'omit',
            'header_param_keys' => [],
            'header_default_image_url' => null,
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
            'header_format' => 'omit',
            'header_param_keys' => [],
            'header_default_image_url' => null,
            'body_params' => ['اسم العميل', 'رقم الطلب'],
            'body_param_keys' => ['customer_name', 'id'],
            'phone_number_id' => null,
        ],
        // confirm_order — نسختان في Meta (عربي / إنجليزي) لمراحل التجهيز والشحن؛ نفس أسماء الأزرار المدعومة في OrderConfirmationFlowService.
        [
            'name' => 'confirm_order',
            'language' => 'ar',
            'ui_label' => 'تأكيد الطلب بالعربية',
            'header_format' => 'omit',
            'header_param_keys' => [],
            'header_default_image_url' => null,
            'body_params' => ['اسم العميل', 'رقم الطلب'],
            'body_param_keys' => ['customer_name', 'id'],
            'phone_number_id' => null,
            'button_ids' => ['confirm_order', 'postpone_order', 'cancel_order'],
        ],
        [
            'name' => 'confirm_order',
            'language' => 'en_US',
            'api_language_code' => 'en',
            'ui_label' => 'تأكيد الطلب بالإنجليزية',
            'header_format' => 'omit',
            'header_param_keys' => [],
            'header_default_image_url' => null,
            'body_params' => ['اسم العميل', 'رقم الطلب'],
            'body_param_keys' => ['customer_name', 'id'],
            'phone_number_id' => null,
        ],

        // client_review — header صورة متغيرة في Meta: يُرسل image.link (الافتراضي من default_header_image_url).
        [
            'name' => 'client_review',
            'language' => 'ar',
            'ui_label' => 'تقييم العميل بالعربية',
            'header_format' => 'image',
            'header_param_keys' => [],
            'header_default_image_url' => null,
            'body_params' => ['اسم العميل'],
            'body_param_keys' => ['customer_name'],
            'phone_number_id' => null,
        ],
        [
            'name' => 'client_review',
            'language' => 'en_US',
            // في Meta معتمد غالباً كـ «English» وليس en_US — يطابق feedback / confirm_order
            'api_language_code' => 'en',
            'ui_label' => 'تقييم العميل بالإنجليزية',
            'header_format' => 'image',
            'header_param_keys' => [],
            'header_default_image_url' => null,
            'body_params' => ['اسم العميل'],
            'body_param_keys' => ['customer_name'],
            'phone_number_id' => null,
        ],

        // feedback — نفس الـ header (صورة متغيرة في القالب المعتمد).
        [
            'name' => 'feedback',
            'language' => 'ar',
            'ui_label' => 'فيد باك بالعربية',
            'header_format' => 'image',
            'header_param_keys' => [],
            'header_default_image_url' => null,
            'body_params' => [],
            'body_param_keys' => [],
            'phone_number_id' => null,
        ],
        [
            'name' => 'feedback',
            'language' => 'en_US',
            'api_language_code' => 'en',
            'ui_label' => 'فيد باك بالانجليزية',
            'header_format' => 'image',
            'header_param_keys' => [],
            'header_default_image_url' => null,
            'body_params' => [],
            'body_param_keys' => [],
            'phone_number_id' => null,
        ],
    ],
];
