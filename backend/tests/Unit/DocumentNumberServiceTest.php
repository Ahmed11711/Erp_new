<?php

namespace Tests\Unit;

use App\Models\DocumentSequence;
use App\Models\Purchase;
use App\Models\TransactionType;
use App\Services\Documents\DocumentNumberService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentNumberServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_skips_existing_purchase_invoice_numbers(): void
    {
        $type = TransactionType::query()->firstOrCreate(
            ['code' => 'PURCHASE_ADD_TEST'],
            [
                'name' => 'Purchase Add Test',
                'prefix' => 'PURT',
                'affects_stock' => true,
                'stock_direction' => 'in',
                'is_active' => true,
            ]
        );

        $seq = DocumentSequence::query()->firstOrCreate(
            ['transaction_type_id' => $type->id],
            ['prefix' => 'PURT', 'last_number' => 1005]
        );
        $seq->update(['last_number' => 1005]);

        Purchase::query()->create([
            'supplier_id' => DB::table('suppliers')->value('id') ?? 1,
            'invoice_type' => 'اضافة وارد جديد',
            'receipt_date' => now()->toDateString(),
            'invoice_number' => 'PURT-1007',
            'invoice_no' => 'PURT-1007',
            'total_price' => 100,
            'paid_amount' => 0,
            'due_amount' => 100,
            'transport_cost' => 0,
            'product_total' => 100,
            'shipping_total' => 0,
            'grand_total' => 100,
            'price_edited' => 0,
            'invoice_image' => '',
            'payment_type' => 'bank',
        ]);

        $next = app(DocumentNumberService::class)->generate((int) $type->id);

        $this->assertSame('PURT-1008', $next);
        $this->assertSame(1008, (int) DocumentSequence::query()->find($seq->id)->last_number);
    }
}
