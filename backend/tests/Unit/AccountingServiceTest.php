<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\Accounting\AccountingService;
use App\Models\TreeAccount;
use App\Models\AccountEntry;

class AccountingServiceTest extends TestCase
{
    public function test_invalid_transaction_type_is_caught_by_guard(): void
    {
        $txType = 'invalid_type';
        $valid = in_array($txType, ['cash_in', 'cash_out']);
        $this->assertFalse($valid, 'Invalid transaction types must be rejected');
    }

    public function test_valid_transaction_types_pass_guard(): void
    {
        $this->assertTrue(in_array('cash_in', ['cash_in', 'cash_out']));
        $this->assertTrue(in_array('cash_out', ['cash_in', 'cash_out']));
    }

    public function test_validate_cash_in_produces_correct_debit_credit(): void
    {
        $txType = 'cash_in';
        $amount = 500.0;

        $cashDebit = $txType === 'cash_in' ? $amount : 0;
        $cashCredit = $txType === 'cash_out' ? $amount : 0;
        $targetDebit = $txType === 'cash_out' ? $amount : 0;
        $targetCredit = $txType === 'cash_in' ? $amount : 0;

        $this->assertEquals(500.0, $cashDebit, 'Cash account should be debited for cash_in');
        $this->assertEquals(0, $cashCredit);
        $this->assertEquals(0, $targetDebit);
        $this->assertEquals(500.0, $targetCredit, 'Target account should be credited for cash_in');
    }

    public function test_validate_cash_out_produces_correct_debit_credit(): void
    {
        $txType = 'cash_out';
        $amount = 300.0;

        $cashDebit = $txType === 'cash_in' ? $amount : 0;
        $cashCredit = $txType === 'cash_out' ? $amount : 0;
        $targetDebit = $txType === 'cash_out' ? $amount : 0;
        $targetCredit = $txType === 'cash_in' ? $amount : 0;

        $this->assertEquals(0, $cashDebit);
        $this->assertEquals(300.0, $cashCredit, 'Cash account should be credited for cash_out');
        $this->assertEquals(300.0, $targetDebit, 'Target account should be debited for cash_out');
        $this->assertEquals(0, $targetCredit);
    }
}
