<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_finger_print_sheet_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finger_print_sheet_id')
                ->constrained('employee_finger_print_sheets')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name');
            $table->string('action');
            $table->json('changes')->nullable();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['finger_print_sheet_id', 'created_at'], 'fp_sheet_logs_sheet_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_finger_print_sheet_logs');
    }
};
