<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToShippingCompanyDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('shipping_company_details', function (Blueprint $table) {
            $table->string('ref')->nullable();
            $table->string('by')->nullable();
            $table->foreignId('shipping_company_id')->constrained('shipping_companies');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shipping_company_details', function (Blueprint $table) {
            $table->dropColumn(['ref', 'by', 'shipping_company_id']);
        });
    }
}
