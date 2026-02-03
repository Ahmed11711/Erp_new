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
        Schema::table('service_accounts', function (Blueprint $table) {
            $table->string('account_number')->nullable()->after('name');
            $table->string('img')->nullable()->after('balance');
            $table->text('other_info')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('service_accounts', function (Blueprint $table) {
            $table->dropColumn(['account_number', 'img', 'other_info']);
        });
    }
};
