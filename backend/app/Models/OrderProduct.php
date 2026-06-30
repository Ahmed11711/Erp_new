<?php

namespace App\Models;

use App\Services\Shopify\ShopifyOrderImportService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProduct extends Model
{
    use HasFactory;
    protected $primaryKey = 'id';
    protected $guarded=[];

    protected $casts = [
        'cancelled_quantity' => 'float',
        'shipped_quantity' => 'float',
        'quantity' => 'float',
    ];

    protected $appends = ['is_shopify_unmatched'];

    /** معرف صنف الـ placeholder لمنتجات Shopify غير المطابقة (يُحلّ مرة واحدة لكل طلب HTTP). */
    private static ?int $shopifyUnmatchedCategoryId = null;

    private static bool $shopifyUnmatchedResolved = false;

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function getIsShopifyUnmatchedAttribute(): bool
    {
        if (! self::$shopifyUnmatchedResolved) {
            self::$shopifyUnmatchedResolved = true;
            $id = Category::query()
                ->where('item_code', ShopifyOrderImportService::UNMATCHED_PLACEHOLDER_ITEM_CODE)
                ->value('id');
            self::$shopifyUnmatchedCategoryId = $id !== null ? (int) $id : null;
        }

        return self::$shopifyUnmatchedCategoryId !== null
            && (int) $this->category_id === self::$shopifyUnmatchedCategoryId;
    }
}
