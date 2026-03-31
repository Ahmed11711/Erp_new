<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_confirmation_sessions')) {
            return;
        }
        // توسيع الحقل لدعم حالات فلو إضافية (رفض الاستلام، إلخ)
        DB::statement('ALTER TABLE order_confirmation_sessions MODIFY flow_state VARCHAR(64) NOT NULL');
    }

    public function down(): void
    {
        // إرجاع ENUM قد يفشل إذا وُجدت قيم جديدة في البيانات
    }
};
