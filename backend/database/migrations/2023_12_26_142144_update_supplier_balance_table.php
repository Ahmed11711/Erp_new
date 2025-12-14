<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateSupplierBalanceTable extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('supplier_balance', 'supplierpay_id')) {
            Schema::table('supplier_balance', function (Blueprint $table) {
                $table->foreignId('supplierpay_id')->nullable()->constrained('supplier_pays');
            });
        }
    }

    public function down()
    {
        Schema::table('supplier_balance', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplierpay_id');
        });
    }
}


