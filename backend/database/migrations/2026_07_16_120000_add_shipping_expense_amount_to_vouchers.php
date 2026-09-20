<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            if (! Schema::hasColumn('vouchers', 'shipping_expense_amount')) {
                $table->decimal('shipping_expense_amount', 15, 2)->default(0)->after('amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            if (Schema::hasColumn('vouchers', 'shipping_expense_amount')) {
                $table->dropColumn('shipping_expense_amount');
            }
        });
    }
};
