<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Defaults when creating a new category (item) from Excel
    |--------------------------------------------------------------------------
    | Set IDs explicitly if your database has multiple productions/measurements.
    */
    'new_item' => [
        'production_id' => env('ITEM_IMPORT_DEFAULT_PRODUCTION_ID'),
        'measurement_id' => env('ITEM_IMPORT_DEFAULT_MEASUREMENT_ID'),
        'warehouse' => env('ITEM_IMPORT_DEFAULT_WAREHOUSE', 'مخزن مواد خام'),
        'category_price' => (float) env('ITEM_IMPORT_DEFAULT_CATEGORY_PRICE', 0),
        'initial_balance' => (float) env('ITEM_IMPORT_DEFAULT_INITIAL_BALANCE', 0),
        'minimum_quantity' => (float) env('ITEM_IMPORT_DEFAULT_MIN_QTY', 0),
        'category_image' => env('ITEM_IMPORT_DEFAULT_CATEGORY_IMAGE', ''),
    ],

    'item_code_prefix' => env('ITEM_IMPORT_CODE_PREFIX', 'ITM-'),

];
