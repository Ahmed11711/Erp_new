<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'prepaid_payment_type')) {
                $table->string('prepaid_payment_type', 32)
                    ->nullable()
                    ->after('bank_id')
                    ->comment('bank|safe|service_account|pending — مصدر دفع مبلغ تحت الحساب');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'prepaid_payment_type')) {
                $table->dropColumn('prepaid_payment_type');
            }
        });
    }
};
