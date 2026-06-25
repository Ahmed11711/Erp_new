<?php

use App\Models\DocumentSequence;
use App\Models\TransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures processing module document types exist (required for order/dispatch/receipt/invoice numbering).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transaction_types') || ! Schema::hasTable('document_sequences')) {
            return;
        }

        $types = [
            ['name' => 'Processing Order', 'code' => 'PROCESSING_ORDER', 'prefix' => 'SUB', 'affects_stock' => false, 'stock_direction' => 'neutral'],
            ['name' => 'Processing Dispatch', 'code' => 'PROCESSING_DISPATCH', 'prefix' => 'MDN', 'affects_stock' => true, 'stock_direction' => 'neutral'],
            ['name' => 'Processing Receipt', 'code' => 'PROCESSING_RECEIPT', 'prefix' => 'PR', 'affects_stock' => true, 'stock_direction' => 'neutral'],
            ['name' => 'Processing Invoice', 'code' => 'PROCESSING_INVOICE', 'prefix' => 'PVI', 'affects_stock' => false, 'stock_direction' => 'neutral'],
        ];

        foreach ($types as $row) {
            $type = TransactionType::query()->firstOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'prefix' => $row['prefix'],
                    'affects_stock' => $row['affects_stock'],
                    'stock_direction' => $row['stock_direction'],
                    'is_active' => true,
                ]
            );

            DocumentSequence::query()->firstOrCreate(
                ['transaction_type_id' => $type->id],
                ['prefix' => $type->prefix, 'last_number' => 1000]
            );
        }
    }

    public function down(): void
    {
        //
    }
};
