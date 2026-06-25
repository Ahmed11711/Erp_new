<?php

namespace Tests\Unit;

use App\Models\Safe;
use App\Models\TreeAccount;
use App\Services\Accounting\ReceivableTreeAccountGuard;
use App\Services\Shipping\UnlinkedReceivableAccountException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReceivableTreeAccountGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_detects_safe_linked_tree_account_as_payment_source(): void
    {
        $parent = TreeAccount::create([
            'name' => 'نقدية',
            'code' => '900001',
            'type' => 'asset',
            'level' => 2,
        ]);
        $safeAccount = TreeAccount::create([
            'parent_id' => $parent->id,
            'name' => 'خزينة اختبار',
            'code' => '9000011',
            'type' => 'asset',
            'detail_type' => 'safe',
            'level' => 3,
        ]);
        Safe::create([
            'name' => 'خزينة اختبار',
            'balance' => 0,
            'type' => 'main',
            'account_id' => $safeAccount->id,
        ]);

        $guard = app(ReceivableTreeAccountGuard::class);

        $this->assertTrue($guard->isPaymentSourceTreeAccount($safeAccount->id));
        $this->assertNull($guard->sanitizeReceivableAccountId($safeAccount->id));
    }

    public function test_allows_regular_receivable_asset_account(): void
    {
        $parent = TreeAccount::create([
            'name' => 'مدينون',
            'code' => '900002',
            'type' => 'asset',
            'level' => 2,
        ]);
        $receivable = TreeAccount::create([
            'parent_id' => $parent->id,
            'name' => 'ذمم شركة شحن',
            'code' => '9000021',
            'type' => 'asset',
            'level' => 3,
        ]);

        $guard = app(ReceivableTreeAccountGuard::class);

        $this->assertFalse($guard->isPaymentSourceTreeAccount($receivable->id));
        $this->assertSame($receivable->id, $guard->sanitizeReceivableAccountId($receivable->id));
    }

    public function test_rejects_payment_source_assignment(): void
    {
        $parent = TreeAccount::create([
            'name' => 'نقدية',
            'code' => '900004',
            'type' => 'asset',
            'level' => 2,
        ]);
        $safeAccount = TreeAccount::create([
            'parent_id' => $parent->id,
            'name' => 'خزينة مرفوضة',
            'code' => '9000041',
            'type' => 'asset',
            'detail_type' => 'safe',
            'level' => 3,
        ]);
        Safe::create([
            'name' => 'خزينة مرفوضة',
            'balance' => 0,
            'type' => 'main',
            'account_id' => $safeAccount->id,
        ]);

        $guard = app(ReceivableTreeAccountGuard::class);

        $this->expectException(UnlinkedReceivableAccountException::class);
        $guard->assertValidReceivableAssignment($safeAccount->id, 'شركة الشحن');
    }
}
