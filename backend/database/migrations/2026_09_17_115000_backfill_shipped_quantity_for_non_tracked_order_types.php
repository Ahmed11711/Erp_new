<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * طلبات الصيانة والمرتجع تُشحن بالكامل بحالة الطلب دون تحديث shipped_quantity،
 * فكانت تظهر «تم شحنها 0 / المتبقي = الكمية» بعد الشحن والتسليم، ويُسجَّل
 * «تسليم جزئي» بالخطأ. هذه تسوية بيانات للطلبات التي خرجت بضاعتها فعلاً.
 *
 * لا تمس المخزون ولا القيود: الأنواع دي مستثناة من COGS ومن حركة المخزن،
 * وعكس الشحن عند إعادة الفتح يعتمد على سجل الحركات لا على هذا العمود.
 */
return new class extends Migration
{
    private const SHIPPED_OUT_STATUSES = [
        'تم شحن',
        'تم الاستلام',
        'تم الصيانة',
        'تسليم جزئي',
        'تم التسليم',
        'تم التحصيل',
    ];

    public function up(): void
    {
        DB::table('order_products as op')
            ->join('orders as o', 'o.id', '=', 'op.order_id')
            ->whereNotIn('o.order_type', ['جديد', 'طلب استبدال'])
            ->whereIn('o.order_status', self::SHIPPED_OUT_STATUSES)
            ->where('op.quantity', '>', 0)
            ->whereRaw('COALESCE(op.shipped_quantity, 0) < GREATEST(op.quantity - COALESCE(op.cancelled_quantity, 0), 0)')
            ->update([
                'op.shipped_quantity' => DB::raw('GREATEST(op.quantity - COALESCE(op.cancelled_quantity, 0), 0)'),
            ]);
    }

    public function down(): void
    {
        // تسوية بيانات تصحيحية — لا رجوع.
    }
};
