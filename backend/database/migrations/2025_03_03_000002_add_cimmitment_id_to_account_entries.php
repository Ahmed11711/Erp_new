<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة ربط القيود المحاسبية بالالتزامات للتتبع
     */
    public function up()
    {
        if (Schema::hasTable('account_entries') && !Schema::hasColumn('account_entries', 'cimmitment_id')) {
            Schema::table('account_entries', function (Blueprint $table) {
                $table->foreignId('cimmitment_id')->nullable()->after('order_id')
                    ->constrained('cimmitments')->nullOnDelete();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('account_entries', 'cimmitment_id')) {
            Schema::table('account_entries', function (Blueprint $table) {
                $table->dropForeign(['cimmitment_id']);
            });
        }
    }
};
