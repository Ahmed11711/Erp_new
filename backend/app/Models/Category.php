<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
 use HasFactory;
 protected  $fillable = [
  'category_name',
  'item_code',
  'color',
  'item_classification_id',
  'parent_item_id',
  'supports_color',
  'color_id',
  'recipe_id',
  'product_type',
  'shipping_size_tier',
  'allow_wip_sale',
  'item_revision',
  'lineage_root_id',
  'replaces_item_id',
  'replaced_by_item_id',
  'category_price',
  'unit_price',
  'total_price',
  'sell_total_price',
  'initial_balance',
  'minimum_quantity',
  'warehouse',
  'production_id',
  'measurement_id',
  'category_image',
  'ref',
  'status',
  'stock_id',
 ];

 protected $casts = [
     'allow_wip_sale' => 'boolean',
     'supports_color' => 'boolean',
 ];

 public function production()
 {
  return $this->belongsTo(Production::class);
 }
 public function measurement()
 {
  return $this->belongsTo(Measurement::class);
 }

 public function itemClassification()
 {
  return $this->belongsTo(ItemClassification::class);
 }
 public function stock()
 {
  return $this->belongsTo(Stock::class);
 }

 public function recipe()
 {
  return $this->belongsTo(Recipe::class);
 }

 public function parentItem(): BelongsTo
 {
  return $this->belongsTo(Category::class, 'parent_item_id');
 }

 public function variants(): HasMany
 {
  return $this->hasMany(Category::class, 'parent_item_id');
 }

 public function tintColor(): BelongsTo
 {
  return $this->belongsTo(Color::class, 'color_id');
 }

 /**
  * Available production / sales colors for a base finished-good (pivot).
  */
 public function manufacturingColors(): BelongsToMany
 {
  return $this->belongsToMany(Color::class, 'category_color', 'category_id', 'color_id')->withTimestamps();
 }

 public function costHistories(): HasMany
 {
  return $this->hasMany(CategoryCostHistory::class, 'category_id')->orderByDesc('id');
 }
}
