<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('status', 32)->default('active');
            $table->text('notes')->nullable();
            /** Optional bridge to shipping_companies for shipping_company_procedure / legacy GL */
            $table->unsignedBigInteger('linked_shipping_company_id')->nullable();
            $table->unsignedBigInteger('receivable_tree_account_id')->nullable();
            $table->timestamps();

            $table->foreign('linked_shipping_company_id')
                ->references('id')
                ->on('shipping_companies')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_companies');
    }
};
