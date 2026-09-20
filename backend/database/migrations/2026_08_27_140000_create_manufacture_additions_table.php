<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacture_additions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('cost', 20, 4)->default(0);
            $table->string('unit')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacture_additions');
    }
};
