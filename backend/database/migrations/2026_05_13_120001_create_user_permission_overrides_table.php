<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $permTable = config('permission.table_names.permissions');

        Schema::create('user_permission_overrides', function (Blueprint $table) use ($permTable) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('permission_id');
            $table->enum('type', ['allow', 'deny']);
            $table->timestamps();

            $table->foreign('permission_id')
                ->references('id')
                ->on($permTable)
                ->cascadeOnDelete();

            $table->unique(['user_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission_overrides');
    }
};
