<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
 use HasFactory;
 protected  $fillable = [
  'category_name',
  'item_code',
  'color',
  'recipe_id',
  'item_revision',
  'lineage_root_id',
  'replaces_item_id',
  'replaced_by_item_id',
  'category_price',
  'unit_price',
  'total_price',
  'sell_total_price',
  'initial_balance',
  'quantity',
  'minimum_quantity',
  'warehouse',
  'production_id',
  'measurement_id',
  'category_image',
  'ref',
  'status',
  'stock_id',
 ];
 public function production()
 {
  return $this->belongsTo(Production::class);
 }
 public function measurement()
 {
  return $this->belongsTo(Measurement::class);
 }
 public function stock()
 {
  return $this->belongsTo(Stock::class);
 }

 public function recipe()
 {
  return $this->belongsTo(Recipe::class);
 }
}
