<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'shopify_payment_gateway')) {
                /** وسيلة الدفع كما وردت من Shopify (Paymob Sympl / valU / Mastercard ...) — لعرضها في تفاصيل الطلب وربط شركة التحصيل. */
                $table->string('shopify_payment_gateway', 255)
                    ->nullable()
                    ->after('shopify_fulfillment_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'shopify_payment_gateway')) {
                $table->dropColumn('shopify_payment_gateway');
            }
        });
    }
};
