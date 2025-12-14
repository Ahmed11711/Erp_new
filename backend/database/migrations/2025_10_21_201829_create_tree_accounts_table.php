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
        Schema::create('tree_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('tree_accounts')->onDelete('cascade');
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->tinyInteger('level')->default(1);
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();
            $table->index('parent_id');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tree_accounts');
    }
};
