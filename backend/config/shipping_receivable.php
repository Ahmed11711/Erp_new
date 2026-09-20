<?php

return [

    /**
     * مفتاح إعداد (settings.key) للحساب الأب لذمم شركات الشحن والمناديب في شجرة الحسابات.
     * إن لم يُضبط يُنشأ/يُستخدم حساب تجميعي باسم parent_account_name تحت المدينون.
     */
    'parent_account_setting_key' => 'shipping_receivable_parent_account_id',

    /** اسم الحساب التجميعي الافتراضي تحت المدينون لشركات الشحن والمناديب. */
    'parent_account_name' => 'شركات الشحن والمناديب',

];
