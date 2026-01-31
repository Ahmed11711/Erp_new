<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('cost_centers')) {
            Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // اسم المركز
            $table->string('name_en')->nullable(); // الاسم بالإنجليزية
            $table->string('code')->unique(); // رقم المركز
            $table->enum('type', ['main', 'sub']); // نوع المركز (رئيسي، فرعي)
            $table->foreignId('parent_id')->nullable()->constrained('cost_centers')->onDelete('cascade'); // المركز الأب
            $table->foreignId('responsible_person_id')->nullable()->constrained('employees')->onDelete('set null'); // الشخص المسؤول
            $table->string('location')->nullable(); // الموقع
            $table->string('phone')->nullable(); // الهاتف
            $table->string('email')->nullable(); // الإميل
            $table->date('start_date')->nullable(); // تاريخ البداية
            $table->date('end_date')->nullable(); // تاريخ الإنتهاء
            $table->string('duration')->nullable(); // المدة
            $table->decimal('value', 15, 2)->default(0); // القيمة
            $table->timestamps();
            
            $table->index('parent_id');
            $table->index('type');
        });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cost_centers');
    }
};

