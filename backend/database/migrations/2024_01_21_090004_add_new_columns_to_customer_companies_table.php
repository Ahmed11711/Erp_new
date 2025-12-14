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
        Schema::table('customer_companies', function (Blueprint $table) {
            $table->string('phone3')->nullable()->after('phone2');
            $table->string('phone4')->nullable()->after('phone3');
            $table->string('tel')->nullable()->after('phone4');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_companies', function (Blueprint $table) {
            Schema::table('customer_companies', function (Blueprint $table) {
                $table->dropColumn('phone3');
                $table->dropColumn('phone4');
                $table->dropColumn('tel');
            });
        });
    }
};
