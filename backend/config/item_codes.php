<?php

return [

    /*
    |--------------------------------------------------------------------------
    | بادئة كود الصنف حسب نوع المخزن
    |--------------------------------------------------------------------------
    | الخامات (مواد خام) تبدأ بـ 10 ثم مسلسل من serial_start → 101001، 101002، …
    */
    'serial_start' => (int) env('ITEM_CODE_SERIAL_START', 1001),

    'prefixes' => [
        'raw_materials' => '10',
        'wip' => '20',
        'finished_goods' => '30',
        'operating_supplies' => '40',
        'maintenance' => '50',
        'damaged' => '60',
        'materials_at_vendor' => '70',
        'default' => '90',
    ],

    'warehouse_names' => [
        'مخزن مواد خام' => 'raw_materials',
        'مخزن منتج تحت التشغيل' => 'wip',
        'مخزن منتج تام' => 'finished_goods',
        'مستلزمات تشغيل وأدوات تشغيل' => 'operating_supplies',
        'مخزن صيانة' => 'maintenance',
        'مخزن تالف' => 'damaged',
        'مخزن التجهيز' => 'materials_at_vendor',
        'مواد لدى مندوب' => 'materials_at_vendor',
        'مخزن مواد لدى مندوب' => 'materials_at_vendor',
        'مخزون لدى معالج خارجي' => 'materials_at_vendor',
    ],

];
