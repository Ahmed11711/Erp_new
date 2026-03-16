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
        Schema::create('lead_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('order')->unique(); // For proper ordering
            $table->string('color')->nullable(); // For UI display
            $table->boolean('requires_follow_up')->default(false); // For Follow-Up status
            $table->timestamps();
        });

        // Insert the professional lead statuses in correct order
        DB::table('lead_statuses')->insert([
            [
                'name' => 'New Lead',
                'description' => 'عميل لسه داخل ومحدش كلمه',
                'order' => 1,
                'color' => '#6c757d', // Gray
                'requires_follow_up' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Contacted',
                'description' => 'تم التواصل معاه أول مرة',
                'order' => 2,
                'color' => '#17a2b8', // Info blue
                'requires_follow_up' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Qualified',
                'description' => 'مناسب + مهتم + عنده budget / احتياج',
                'order' => 3,
                'color' => '#28a745', // Green
                'requires_follow_up' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Follow-Up',
                'description' => 'قال يرجع الأسبوع الجاي / بعد شهر / بعد ما يستلم فلوس / إلخ',
                'order' => 4,
                'color' => '#ffc107', // Yellow/Orange
                'requires_follow_up' => true, // Most important status with date
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Converted',
                'description' => 'اشترى أو وافق رسميًا',
                'order' => 5,
                'color' => '#007bff', // Blue
                'requires_follow_up' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Dealt / Closed Won',
                'description' => 'تم تنفيذ الصفقة وتسليم الطلب',
                'order' => 6,
                'color' => '#20c997', // Teal
                'requires_follow_up' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Not Qualified',
                'description' => 'مش مناسب (budget قليل – خارج المنطقة – مش محتاج المنتج)',
                'order' => 7,
                'color' => '#fd7e14', // Orange
                'requires_follow_up' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Wrong Data',
                'description' => 'رقم غلط / مش نفس الشخص / بيانات وهمية',
                'order' => 8,
                'color' => '#dc3545', // Red
                'requires_follow_up' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Archived',
                'description' => 'Lead قديم خلصت قصته (سواء اشترى أو لا) — للتنضيف',
                'order' => 9,
                'color' => '#6f42c1', // Purple
                'requires_follow_up' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lead_statuses');
    }
};
