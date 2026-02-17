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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('sender_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('receiver_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('content');
            $table->enum('direction', ['inbound', 'outbound'])->default('outbound');
            $table->enum('status', ['sent', 'delivered', 'read', 'failed', 'received'])->default('sent');
            $table->string('twilio_message_sid')->nullable()->unique(); // Meta message ID
            $table->timestamps();
            
            $table->index('customer_id');
            $table->index('direction');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('messages');
    }
};
