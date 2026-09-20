<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processing_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 64)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('source_stock_id')->constrained('stocks')->restrictOnDelete();
            $table->foreignId('destination_stock_id')->nullable()->constrained('stocks')->nullOnDelete();
            $table->string('status', 32)->default('draft');
            $table->date('expected_return_date')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('expected_service_total', 20, 4)->default(0);
            $table->decimal('total_dispatched_qty', 18, 6)->default(0);
            $table->decimal('total_received_qty', 18, 6)->default(0);
            $table->decimal('total_service_cost', 20, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'status']);
            $table->index('status');
        });

        Schema::create('processing_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processing_order_id')->constrained('processing_orders')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('destination_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('at_vendor_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->decimal('ordered_qty', 18, 6);
            $table->decimal('dispatched_qty', 18, 6)->default(0);
            $table->decimal('received_good_qty', 18, 6)->default(0);
            $table->decimal('received_damaged_qty', 18, 6)->default(0);
            $table->decimal('received_rejected_qty', 18, 6)->default(0);
            $table->decimal('expected_service_amount', 20, 4)->default(0);
            $table->decimal('unit_material_cost', 16, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('processing_dispatch_notes', function (Blueprint $table) {
            $table->id();
            $table->string('dispatch_number', 64)->unique();
            $table->foreignId('processing_order_id')->constrained('processing_orders')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('source_stock_id')->constrained('stocks')->restrictOnDelete();
            $table->date('dispatch_date');
            $table->string('status', 32)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('daily_entry_id')->nullable()->constrained('daily_entries')->nullOnDelete();
            $table->foreignId('stock_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['processing_order_id', 'status']);
        });

        Schema::create('processing_dispatch_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processing_dispatch_note_id')->constrained('processing_dispatch_notes')->cascadeOnDelete();
            $table->foreignId('processing_order_line_id')->constrained('processing_order_lines')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('at_vendor_category_id')->constrained('categories')->restrictOnDelete();
            $table->decimal('quantity', 18, 6);
            $table->decimal('unit_cost', 16, 4)->default(0);
            $table->decimal('total_cost', 20, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('processing_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 64)->unique();
            $table->foreignId('processing_order_id')->constrained('processing_orders')->restrictOnDelete();
            $table->foreignId('processing_dispatch_note_id')->nullable()->constrained('processing_dispatch_notes')->nullOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('destination_stock_id')->constrained('stocks')->restrictOnDelete();
            $table->date('receipt_date');
            $table->string('status', 32)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('daily_entry_id')->nullable()->constrained('daily_entries')->nullOnDelete();
            $table->foreignId('stock_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['processing_order_id', 'status']);
        });

        Schema::create('processing_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processing_receipt_id')->constrained('processing_receipts')->cascadeOnDelete();
            $table->foreignId('processing_order_line_id')->constrained('processing_order_lines')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('at_vendor_category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('destination_category_id')->constrained('categories')->restrictOnDelete();
            $table->decimal('good_qty', 18, 6)->default(0);
            $table->decimal('damaged_qty', 18, 6)->default(0);
            $table->decimal('rejected_qty', 18, 6)->default(0);
            $table->decimal('material_unit_cost', 16, 4)->default(0);
            $table->decimal('allocated_service_cost', 20, 4)->default(0);
            $table->foreignId('rejection_return_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('processing_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 64)->unique();
            $table->string('external_invoice_no', 128)->nullable();
            $table->foreignId('processing_order_id')->nullable()->constrained('processing_orders')->nullOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 20, 4)->default(0);
            $table->decimal('tax_amount', 20, 4)->default(0);
            $table->decimal('grand_total', 20, 4)->default(0);
            $table->decimal('paid_amount', 20, 4)->default(0);
            $table->decimal('due_amount', 20, 4)->default(0);
            $table->string('status', 32)->default('draft');
            $table->boolean('capitalize_to_inventory')->default(true);
            $table->text('notes')->nullable();
            $table->string('invoice_image', 512)->nullable();
            $table->foreignId('daily_entry_id')->nullable()->constrained('daily_entries')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'status']);
        });

        Schema::create('processing_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processing_invoice_id')->constrained('processing_invoices')->cascadeOnDelete();
            $table->string('line_type', 32)->default('other');
            $table->string('description', 512);
            $table->decimal('quantity', 18, 6)->default(1);
            $table->decimal('unit_price', 16, 4)->default(0);
            $table->decimal('total', 20, 4)->default(0);
            $table->foreignId('processing_receipt_line_id')->nullable()->constrained('processing_receipt_lines')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('processing_material_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processing_order_id')->constrained('processing_orders')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('at_vendor_category_id')->constrained('categories')->restrictOnDelete();
            $table->decimal('qty_at_vendor', 18, 6)->default(0);
            $table->timestamps();

            $table->unique(['processing_order_id', 'at_vendor_category_id'], 'proc_mat_bal_order_cat_uq');
        });

        Schema::create('processing_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 128);
            $table->unsignedBigInteger('subject_id');
            $table->string('action', 64);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
        });

        if (Schema::hasTable('supplier_pays') && ! Schema::hasColumn('supplier_pays', 'processing_invoice_id')) {
            Schema::table('supplier_pays', function (Blueprint $table) {
                $table->foreignId('processing_invoice_id')->nullable()->after('supplier_id')
                    ->constrained('processing_invoices')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('supplier_pays') && Schema::hasColumn('supplier_pays', 'processing_invoice_id')) {
            Schema::table('supplier_pays', function (Blueprint $table) {
                $table->dropConstrainedForeignId('processing_invoice_id');
            });
        }

        Schema::dropIfExists('processing_activity_logs');
        Schema::dropIfExists('processing_material_balances');
        Schema::dropIfExists('processing_invoice_lines');
        Schema::dropIfExists('processing_invoices');
        Schema::dropIfExists('processing_receipt_lines');
        Schema::dropIfExists('processing_receipts');
        Schema::dropIfExists('processing_dispatch_lines');
        Schema::dropIfExists('processing_dispatch_notes');
        Schema::dropIfExists('processing_order_lines');
        Schema::dropIfExists('processing_orders');
    }
};
