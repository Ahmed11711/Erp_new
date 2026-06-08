<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $permTable = config('permission.table_names.permissions');

        Schema::create('permission_dependencies', function (Blueprint $table) use ($permTable) {
            $table->id();
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('requires_permission_id');
            $table->timestamps();

            $table->foreign('permission_id')
                ->references('id')
                ->on($permTable)
                ->cascadeOnDelete();

            $table->foreign('requires_permission_id')
                ->references('id')
                ->on($permTable)
                ->cascadeOnDelete();

            $table->unique(['permission_id', 'requires_permission_id'], 'perm_dep_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_dependencies');
    }
};
