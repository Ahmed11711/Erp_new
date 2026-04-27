<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة مورد بحقول اختيارية — السماح بقيم NULL في الجدول.
     * (لا يُفترض وجود foreign key على supplier_type في كل البيئات؛ التعديل على الأعمدة فقط.)
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // $table->string('supplier_name')->nullable()->change();
            $table->string('supplier_address')->nullable()->change();
            $table->unsignedBigInteger('supplier_type')->nullable()->change();
            $table->integer('supplier_rate')->nullable()->change();
            $table->integer('price_rate')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('supplier_name')->nullable(false)->change();
            $table->string('supplier_address')->nullable(false)->change();
            $table->unsignedBigInteger('supplier_type')->nullable(false)->change();
            $table->integer('supplier_rate')->nullable(false)->change();
            $table->integer('price_rate')->nullable(false)->change();
        });
    }
};
