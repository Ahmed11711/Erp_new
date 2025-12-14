<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->enum('status', ['0', '1'])->nullable();
            $table->double('edits')->default(0);
            $table->unsignedBigInteger('ref')->nullable();
            $table->foreign('ref')->references('id')->on('purchases');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['ref']);
            $table->dropColumn(['edits','status', 'ref']);
        });
    }
};
