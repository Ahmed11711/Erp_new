<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('corporate_sales_lead_recommenders', function (Blueprint $table) {
            $table->boolean('is_done')->default(false)->after('notes');
        });
    }

    public function down()
    {
        Schema::table('corporate_sales_lead_recommenders', function (Blueprint $table) {
            $table->dropColumn('is_done');
        });
    }
};
