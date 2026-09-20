<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tree_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('tree_accounts', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('tree_accounts', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('tree_account_audits')) {
            Schema::create('tree_account_audits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tree_account_id')->nullable();
                $table->string('action', 20);
                $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('account_code', 64)->nullable();
                $table->string('account_name')->nullable();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->json('changes')->nullable();
                $table->timestamps();

                $table->index(['tree_account_id', 'created_at']);
                $table->index(['performed_by', 'created_at']);
                $table->foreign('tree_account_id')->references('id')->on('tree_accounts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tree_account_audits');

        Schema::table('tree_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('tree_accounts', 'updated_by')) {
                $table->dropConstrainedForeignId('updated_by');
            }
            if (Schema::hasColumn('tree_accounts', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
        });
    }
};
