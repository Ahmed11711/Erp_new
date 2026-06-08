<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase invoices live in `purchases` (legacy table name); numbering aligns with document_sequences (PUR-*).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('purchases', 'invoice_no')) {
                $table->string('invoice_no')->nullable()->unique()->after('invoice_number');
            }
            if (! Schema::hasColumn('purchases', 'external_invoice_no')) {
                $table->string('external_invoice_no')->nullable()->after('invoice_no');
            }
            if (! Schema::hasColumn('purchases', 'printable_status')) {
                $table->string('printable_status', 32)->default('draft')->after('external_invoice_no');
            }
            if (! Schema::hasColumn('purchases', 'notes')) {
                $table->text('notes')->nullable()->after('printable_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'notes')) {
                $table->dropColumn('notes');
            }
            if (Schema::hasColumn('purchases', 'printable_status')) {
                $table->dropColumn('printable_status');
            }
            if (Schema::hasColumn('purchases', 'external_invoice_no')) {
                $table->dropColumn('external_invoice_no');
            }
            if (Schema::hasColumn('purchases', 'invoice_no')) {
                $table->dropColumn('invoice_no');
            }
        });
    }
};
