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
        Schema::table('corporate_sales_leads', function (Blueprint $table) {
            $table->foreignId('lead_status_id')->nullable()->after('user_id')->constrained('lead_statuses');
            
            // Add next action fields for Follow-Up status
            $table->string('next_action_type')->nullable()->after('lead_status_id')->comment('Call, Email, Meeting, etc.');
            $table->date('next_action_date')->nullable()->after('next_action_type');
            $table->text('next_action_notes')->nullable()->after('next_action_date');
            
            // Add additional fields for better CRM management
            $table->string('contact_title')->nullable()->after('company_name')->comment('Job title like CEO, Manager, etc.');
            $table->string('contact_department')->nullable()->after('contact_title')->comment('Department like IT, Sales, etc.');
        });

        // Set default status for existing leads to "New Lead"
        $newLeadStatus = DB::table('lead_statuses')->where('name', 'New Lead')->first();
        if ($newLeadStatus) {
            DB::table('corporate_sales_leads')
                ->whereNull('lead_status_id')
                ->update(['lead_status_id' => $newLeadStatus->id]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('corporate_sales_leads', function (Blueprint $table) {
            $table->dropForeign(['lead_status_id']);
            $table->dropColumn(['lead_status_id', 'next_action_type', 'next_action_date', 'next_action_notes', 'contact_title', 'contact_department']);
        });
    }
};
