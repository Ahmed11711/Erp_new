<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ربط وسيلة الدفع في Shopify بشركة التحصيل
    |--------------------------------------------------------------------------
    |
    | عند سحب/استيراد طلب مدفوع من Shopify نقرأ اسم بوابة الدفع
    | (payment_gateway_names / gateway / payment_details) ونحوّلها لاسم شركة
    | تحصيل مطابق. تُسجَّل مديونية المبلغ المدفوع على حساب ذمم تلك الشركة
    | (هي من حصّلت النقدية وستُسدّدها للنظام لاحقاً عند التسوية).
    |
    | إن لم تُوجد شركة تحصيل بنفس الاسم يتم إنشاؤها تلقائياً مع حساب ذمم لها.
    |
    */

    /**
     * بوابات مميزة تُفحص أولاً (قبل اكتشاف البطاقة).
     * المطابقة بالاحتواء على نص اسم البوابة (غير حساس لحالة الأحرف).
     * الترتيب مهم: الأكثر تحديداً أولاً (مثلاً sympl قبل paymob لأن
     * "Paymob Sympl" يحتوي الاثنين والمقصود Sympl).
     */
    'gateway_map' => [
        'sympl' => 'Sympl',
        'valu' => 'valU',
        'value' => 'valU',
        'souhoola' => 'Souhoola',
        'sohoola' => 'Souhoola',
        'aman' => 'Aman',
        'contact' => 'Contact',
        'forsa' => 'Forsa',
        'halan' => 'Halan',
        'fawry' => 'Fawry',
        'instapay' => 'InstaPay',
        'meeza' => 'Meeza',
        'vodafone' => 'Vodafone Cash',
    ],

    /**
     * مؤشرات الدفع بالبطاقة → تُسجَّل تحت شركة "Visa".
     * (طالما الدفع تم ببطاقة برقم — فيزا/ماستر/أمكس — يُعتبر تحصيل بطاقة.)
     */
    'card_aliases' => [
        'visa',
        'mastercard',
        'master card',
        'maestro',
        'amex',
        'american express',
        'credit card',
        'debit card',
        'bankcard',
        'shopify_payments',
        'shopify payments',
    ],

    /** اسم شركة التحصيل لمدفوعات البطاقة. */
    'card_company' => 'Visa',

    /**
     * بوابات قد تُستخدم للدفع بالبطاقة؛ تُفحص بعد اكتشاف البطاقة فقط
     * (إن لم يظهر برند بطاقة يُنسب التحصيل لاسم البوابة نفسها).
     */
    'ambiguous_gateway_map' => [
        'paymob' => 'Paymob',
        'accept' => 'Paymob',
        'kashier' => 'Kashier',
        'paytabs' => 'PayTabs',
        'fawaterk' => 'Fawaterak',
    ],

    /**
     * اسم شركة التحصيل الافتراضي لطلب مدفوع تعذّر التعرف على بوابته.
     */
    'default_company' => 'Visa',

    /**
     * مفتاح إعداد (settings.key) للحساب الأب لشركات التحصيل في شجرة الحسابات.
     * إن لم يُضبط يُنشأ/يُستخدم حساب تجميعي باسم parent_account_name تحت المدينون.
     */
    'parent_account_setting_key' => 'collection_companies_parent_account_id',

    /** اسم الحساب التجميعي الافتراضي تحت المدينون لشركات التحصيل. */
    'parent_account_name' => 'شركات التحصيل',
];
