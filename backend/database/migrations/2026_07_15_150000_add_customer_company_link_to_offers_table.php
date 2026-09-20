<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_company_id')->nullable()->after('client_phone');
            $table->timestamp('debt_posted_at')->nullable()->after('customer_company_id');
            $table->decimal('debt_amount', 15, 3)->nullable()->after('debt_posted_at');

            $table->foreign('customer_company_id')
                ->references('id')
                ->on('customer_companies')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropForeign(['customer_company_id']);
            $table->dropColumn(['customer_company_id', 'debt_posted_at', 'debt_amount']);
        });
    }
};
