<?php

/**
 * تصنيف طريقة الشحن لطلبات Shopify حسب منتجات الطلب.
 *
 * الأولوية لكل سطر:
 *  1. categories.shipping_size_tier (مصدر الحقيقة على مستوى الصنف)
 *  2. قواعد keyword_rules بالترتيب (الأكثر تحديداً أولاً)
 *  3. warehouse_tiers حسب حقل warehouse في الصنف
 *  4. default_tier (كبير)
 *
 * على مستوى الطلب: يُؤخذ أكبر حجم بين السطور (Large > Medium > Small).
 */
return [
    'method_names' => [
        'small' => env('SHOPIFY_SHIPPING_METHOD_SMALL', 'Small'),
        'medium' => env('SHOPIFY_SHIPPING_METHOD_MEDIUM', 'Medium'),
        'large' => env('SHOPIFY_SHIPPING_METHOD_LARGE', 'Large'),
    ],

    'default_tier' => 'small',

    /**
     * قواعد نصية مرتبة — أول تطابق يفوز (ضع الأكثر تحديداً في الأعلى).
     *
     * @var array<int, array{tier: string, keywords: array<int, string>}>
     */
    'keyword_rules' => [
        // ——— صغير (محدد) ———
        [
            'tier' => 'small',
            'keywords' => [
                'سجاد', 'سجاده', 'rug', 'beach rug', 'beach mat', 'mat',
                'مخدة', 'مخده', 'مخدات', 'pillow', 'cushion',
            ],
        ],
        // ——— متوسط (محدد قبل «حاجات البحر» العامة) ———
        [
            'tier' => 'medium',
            'keywords' => [
                'bean bag', 'beanbag', 'بين باج', 'بينbage',
                'footrest', 'foot rest', 'مسند القدم', 'مسند قدم', 'مسند',
                'ottoman', 'pouf', 'بوف',
            ],
        ],
        // ——— صغير (فئات عامة) ———
        [
            'tier' => 'small',
            'keywords' => [
                'شنط', 'شنطه', 'شنطة', 'bag', 'tote', 'handbag',
                'خشب', 'wood', 'wooden',
                'مطبخ', 'kitchen',
            ],
        ],
        // ——— متوسط (حاجات البحر غير السجاد) ———
        [
            'tier' => 'medium',
            'keywords' => [
                'حاجات البحر', 'beach', 'شاطئ', 'sea',
            ],
        ],
        // ——— كبير ———
        [
            'tier' => 'large',
            'keywords' => [
                'كرسي', 'كراسي', 'chair', 'chairs',
                'طاولة', 'table', 'desk',
                'أثاث', 'اثاث', 'furniture', 'sofa', 'كنبة', 'كنبه', 'bed', 'سرير',
                'cabinet', 'دولاب', 'wardrobe', 'خزانة',
            ],
        ],
    ],

    /**
     * مطابقة حقل warehouse في categories (اختياري).
     *
     * @var array<string, array<int, string>>
     */
    'warehouse_tiers' => [
        'small' => ['شنط', 'bags', 'bag', 'أخشاب', 'اخشاب', 'wood', 'مطبخ', 'kitchen'],
        'medium' => ['بحر', 'beach', 'شاطئ'],
    ],
];
