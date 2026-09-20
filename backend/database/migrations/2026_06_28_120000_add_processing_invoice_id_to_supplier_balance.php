<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_balance')) {
            return;
        }

        if (! Schema::hasColumn('supplier_balance', 'processing_invoice_id')) {
            Schema::table('supplier_balance', function (Blueprint $table) {
                $table->foreignId('processing_invoice_id')
                    ->nullable()
                    ->after('supplierpay_id')
                    ->constrained('processing_invoices')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('processing_invoices')) {
            return;
        }

        $invoices = DB::table('processing_invoices')
            ->whereIn('status', ['posted', 'partially_paid', 'paid'])
            ->get(['id', 'supplier_id', 'grand_total', 'created_at', 'updated_at']);

        foreach ($invoices as $invoice) {
            $already = DB::table('supplier_balance')
                ->where('processing_invoice_id', $invoice->id)
                ->exists();
            if ($already) {
                continue;
            }

            $amount = (float) $invoice->grand_total;
            $ts = $invoice->updated_at ?? $invoice->created_at;

            $orphan = DB::table('supplier_balance')
                ->whereNull('invoice_id')
                ->whereNull('supplierpay_id')
                ->whereNull('processing_invoice_id')
                ->whereBetween(DB::raw('balance_after - balance_before'), [$amount - 0.01, $amount + 0.01])
                ->when($ts, fn ($q) => $q->whereBetween('created_at', [
                    date('Y-m-d H:i:s', strtotime($ts) - 120),
                    date('Y-m-d H:i:s', strtotime($ts) + 120),
                ]))
                ->orderByDesc('id')
                ->first();

            if ($orphan) {
                DB::table('supplier_balance')
                    ->where('id', $orphan->id)
                    ->update(['processing_invoice_id' => $invoice->id]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('supplier_balance') || ! Schema::hasColumn('supplier_balance', 'processing_invoice_id')) {
            return;
        }

        Schema::table('supplier_balance', function (Blueprint $table) {
            $table->dropConstrainedForeignId('processing_invoice_id');
        });
    }
};
