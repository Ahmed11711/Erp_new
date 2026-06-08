<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $roleTable = config('permission.table_names.roles');

        Schema::create('department_role_templates', function (Blueprint $table) use ($roleTable) {
            $table->id();
            $table->string('department', 191);
            $table->unsignedBigInteger('role_id');
            $table->unsignedInteger('sample_users_count')->default(0);
            $table->timestamps();

            $table->foreign('role_id')
                ->references('id')
                ->on($roleTable)
                ->cascadeOnDelete();

            $table->unique('department');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_role_templates');
    }
};
