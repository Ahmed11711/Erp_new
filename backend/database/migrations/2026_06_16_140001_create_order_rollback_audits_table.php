<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_rollback_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('previous_status');
            $table->string('target_status');
            $table->text('reason');
            $table->unsignedBigInteger('performed_by');
            $table->json('reversed_accounting')->nullable();
            $table->json('reversed_inventory')->nullable();
            $table->json('reversed_operations')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('performed_by')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['order_id', 'created_at']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('shipments', 'reopened_at')) {
                $table->timestamp('reopened_at')->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('shipments', 'reopen_reason')) {
                $table->string('reopen_reason')->nullable()->after('reopened_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'reopen_reason')) {
                $table->dropColumn('reopen_reason');
            }
            if (Schema::hasColumn('shipments', 'reopened_at')) {
                $table->dropColumn('reopened_at');
            }
        });

        Schema::dropIfExists('order_rollback_audits');
    }
};
