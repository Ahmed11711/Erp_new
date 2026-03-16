<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up()
    {
        Schema::table('supplier_pays', function (Blueprint $table) {
            if (!Schema::hasColumn('supplier_pays', 'safe_id')) {
                $table->unsignedBigInteger('safe_id')->nullable()->after('bank_id');
            }
            if (!Schema::hasColumn('supplier_pays', 'service_account_id')) {
                $table->unsignedBigInteger('service_account_id')->nullable()->after('safe_id');
            }
        });
    }

    public function down()
    {
        Schema::table('supplier_pays', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_pays', 'safe_id')) {
                $table->dropColumn('safe_id');
            }
            if (Schema::hasColumn('supplier_pays', 'service_account_id')) {
                $table->dropColumn('service_account_id');
            }
        });
    }
};
