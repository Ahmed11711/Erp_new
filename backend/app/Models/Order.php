<?php

namespace App\Models;

use App\Enums\OrderCollectionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Services\Orders\OrderStatusVisibilityService;

class Order extends Model
{
 use HasFactory;

 protected $guarded = [];

 protected $casts = [
  'shopify_reviewed_at' => 'datetime',
  'shopify_needs_product_review' => 'boolean',
  'offer_debt_posted' => 'boolean',
 ];

 public function shopifyReviewer()
 {
  return $this->belongsTo(User::class, 'shopify_reviewed_by_user_id');
 }

 public function order_products()
 {
  return $this->hasMany(OrderProduct::class);
 }

 /** دفعات الشحن الجزئي (order_shipments) — غير علاقة Shipment القديمة */
 public function orderShipments()
 {
  return $this->hasMany(OrderShipment::class)->orderBy('shipment_seq');
 }
 public function order_products_archive()
 {
  return $this->hasMany(OrderProductArchive::class);
 }
 public function order_shipment_number()
 {
  return $this->hasMany(OrderShippingNumber::class)->orderBy('created_at', 'desc');
 }
 public function notifications()
 {
  return $this->hasMany(Notification::class);
 }
 public function tempReview()
 {
  return $this->hasMany(OrderTempReview::class);
 }
 public function traking()
 {
  return $this->hasMany(tracking::class);
 }
 public function note()
 {
  return $this->hasMany(Note::class);
 }
 public function maintenReason()
 {
  return $this->hasMany(OrderMaintenReason::class);
 }
 public function shipping_method()
 {
  return $this->belongsTo(ShippingMethod::class);
 }
 public function order_source()
 {
  return $this->belongsTo(OrderSource::class);
 }
 public function order_details()
 {
  return $this->hasOne(OrderDetails::class);
 }
 public function bank()
 {
  return $this->belongsTo(Bank::class);
 }

 public function status_histories()
 {
  return $this->hasMany(OrderStatusHistory::class)->orderByDesc('created_at');
 }

 public function rollback_audits()
 {
  return $this->hasMany(OrderRollbackAudit::class)->orderByDesc('created_at');
 }

 public function shipments()
 {
  return $this->hasMany(Shipment::class);
 }

 /**
  * حالات سطور الشحن الملغاة/المُعاد فتحها — لا تُحتسب عند التسوية التلقائية.
  *
  * @var list<string>
  */
 private const INACTIVE_SHIPPING_DETAIL_STATUSES = ['إعادة فتح', 'ملغي'];

 /**
  * عند إغلاق كل سطور shipping_company_details (is_done) دون تحديث الطلب (مثلاً بعد قبض سند)،
  * تُحدَّث حالة الطلب إلى «تم التحصيل» وتُعبأ collection_date عند الحاجة.
  *
  * لا يُغلق الطلب تلقائياً عند تحصيل جزئي، ولا اعتماداً على سطور «إعادة فتح» القديمة فقط.
  */
 public static function reconcileCollectionStatusIfAllShippingLinesClosed(int $orderId): bool
 {
  $order = self::with('order_details')->find($orderId);
  if (! $order) {
   return false;
  }
  if ($order->order_status === 'تم التحصيل') {
   return false;
  }
  if (! in_array($order->order_status, ['تم شحن', 'تم التسليم', 'شحن جزئي', 'تسليم جزئي'], true)) {
   return false;
  }
  if (! $order->order_details) {
   return false;
  }

  if (! self::hasClosedActiveShippingLines($orderId)) {
   return false;
  }

  $guard = app(\App\Services\Orders\OrderManualCollectionGuard::class);
  $updated = false;

  DB::transaction(function () use ($orderId, $guard, &$updated) {
   $o = self::with('order_details')->lockForUpdate()->find($orderId);
   if (! $o || $o->order_status === 'تم التحصيل') {
    return;
   }
   if (! in_array($o->order_status, ['تم شحن', 'تم التسليم', 'شحن جزئي', 'تسليم جزئي'], true)) {
    return;
   }
   if (! self::hasClosedActiveShippingLines($orderId)) {
    return;
   }

   $od = $o->order_details;

   // كل سطور المندوب/شركة الشحن أُقفلت (سند قبض / تسوية) → مستحق الشحن المخزَّن صُفِّي فعلياً.
   // نفس ما يفعله مسار «تحصيل الطلب» قبل فحص الإغلاق، وإلا بقي الطلب «تم التسليم» رغم توريد المندوب.
   if ($od && $od->shipping_receivable_amount !== null && (float) $od->shipping_receivable_amount > 0.009) {
    $od->shipping_receivable_amount = 0;
    $od->save();
    $o->setRelation('order_details', $od->fresh());
   }

   if (! $guard->shouldMarkOrderFullyCollected($o)) {
    return;
   }

   if ($od) {
    if (! $od->collection_date) {
     $od->collection_date = now()->format('Y-m-d');
    }
    $od->status_date = now()->format('Y-m-d');
    $od->collection_status = OrderCollectionStatus::Collected->value;
    $od->save();
   }
   $o->order_status = 'تم التحصيل';
   $o->save();
   $updated = true;
  });

  return $updated;
 }

 /**
  * سطور شحن فعّالة (غير إعادة فتح/ملغي) وكلها مغلقة — شرط التسوية التلقائية.
  */
 private static function hasClosedActiveShippingLines(int $orderId): bool
 {
  $active = static fn () => shippingCompanyDetails::query()
   ->where('order_id', $orderId)
   ->whereNotIn('status', self::INACTIVE_SHIPPING_DETAIL_STATUSES);

  if (! $active()->exists()) {
   return false;
  }

  return ! $active()->where('is_done', 0)->exists();
 }

 // ['id=>Order Number
 // order Status=>order_status
 //order date=>order_date
 //customer Note=> collect_note
 //First Name =>customer_name
 //Last Name=>customer_name
 //company=>$company->name 
 //Address=>address
 //city=>city
 // state code=>""
 // Country code

 // ]

 /**
  * يقيّد الاستعلام بالطلبات التي يسمح للمستخدم برؤية حالتها.
  */
 public function scopeVisibleToUser(Builder $query, User $user): Builder
 {
  app(OrderStatusVisibilityService::class)->applySearchScope($query, $user);

  return $query;
 }

}
