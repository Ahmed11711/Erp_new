<?php

namespace Tests\Unit;

use App\Services\Accounting\TrialBalanceOpeningImportService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class TrialBalanceOpeningImportServiceTest extends TestCase
{
    public function test_normalize_code_strips_excel_decimal_suffix(): void
    {
        $service = $this->serviceWithoutConstructor();
        $method = (new ReflectionClass($service))->getMethod('normalizeCode');
        $method->setAccessible(true);

        $this->assertSame('1011001', $method->invoke($service, '1011001.0'));
        $this->assertSame('1011001', $method->invoke($service, '1011001'));
    }

    public function test_parse_amount_handles_thousands_and_parentheses(): void
    {
        $service = $this->serviceWithoutConstructor();
        $method = (new ReflectionClass($service))->getMethod('parseAmount');
        $method->setAccessible(true);

        $this->assertSame(1136860.24, $method->invoke($service, '1,136,860.24'));
        $this->assertSame(11187.0, $method->invoke($service, '(11,187.00)'));
        $this->assertSame(0.0, $method->invoke($service, ''));
    }

    public function test_detects_erp_arabic_trial_balance_headers_using_opening_not_level(): void
    {
        $map = $this->detectHeaderMap([
            1 => 'كود الحساب',
            2 => 'اسم الحساب',
            3 => 'النوع',
            4 => 'المستوى',
            5 => 'رصيد أول المدة - مدين',
            6 => 'رصيد أول المدة - دائن',
            7 => 'الحركة - مدين',
            8 => 'الحركة - دائن',
            9 => 'رصيد آخر المدة - مدين',
            10 => 'رصيد آخر المدة - دائن',
        ]);

        $this->assertNotNull($map);
        $this->assertSame(1, $map['code']);
        $this->assertSame(2, $map['name']);
        $this->assertSame(5, $map['debit']);
        $this->assertSame(6, $map['credit']);
        $this->assertSame(9, $map['debit_alt']);
        $this->assertSame(10, $map['credit_alt']);
    }

    public function test_detects_classic_prev_balance_headers(): void
    {
        $map = $this->detectHeaderMap([
            1 => 'AccountID',
            2 => 'AccountName',
            3 => 'PrevCRBalance',
            4 => 'PrevDBBalance',
        ]);

        $this->assertNotNull($map);
        $this->assertSame(1, $map['code']);
        $this->assertSame(3, $map['credit']);
        $this->assertSame(4, $map['debit']);
    }

    public function test_erp_data_row_reads_opening_debit_not_account_level(): void
    {
        $service = $this->serviceWithoutConstructor();
        $method = (new ReflectionClass($service))->getMethod('parseDataRow');
        $method->setAccessible(true);

        $headerMap = $this->detectHeaderMap([
            1 => 'كود الحساب',
            2 => 'اسم الحساب',
            3 => 'النوع',
            4 => 'المستوى',
            5 => 'رصيد أول المدة - مدين',
            6 => 'رصيد أول المدة - دائن',
            7 => 'الحركة - مدين',
            8 => 'الحركة - دائن',
            9 => 'رصيد آخر المدة - مدين',
            10 => 'رصيد آخر المدة - دائن',
        ]);

        $row = $method->invoke($service, [
            1 => '100011',
            2 => 'أصول ثابتة - أجهزة كهربائية وتكيفات',
            3 => 'أصول',
            4 => '3',
            5 => '4630',
            6 => '0',
            7 => '0',
            8 => '0',
            9 => '4630',
            10 => '0',
        ], $headerMap, 6);

        $this->assertSame('100011', $row['account_code']);
        $this->assertSame(4630.0, $row['debit']);
        $this->assertSame(0.0, $row['credit']);
    }

    public function test_erp_data_row_does_not_mix_in_closing_when_opening_is_zero(): void
    {
        $service = $this->serviceWithoutConstructor();
        $method = (new ReflectionClass($service))->getMethod('parseDataRow');
        $method->setAccessible(true);
        $headerMap = $this->detectHeaderMap([
            1 => 'كود الحساب',
            2 => 'اسم الحساب',
            3 => 'النوع',
            4 => 'المستوى',
            5 => 'رصيد أول المدة - مدين',
            6 => 'رصيد أول المدة - دائن',
            7 => 'الحركة - مدين',
            8 => 'الحركة - دائن',
            9 => 'رصيد آخر المدة - مدين',
            10 => 'رصيد آخر المدة - دائن',
        ]);

        $row = $method->invoke($service, [
            1 => '100011',
            2 => 'أصول ثابتة',
            3 => 'أصول',
            4 => '3',
            5 => '0',
            6 => '0',
            7 => '0',
            8 => '0',
            9 => '4630',
            10 => '0',
        ], $headerMap, 6);

        $this->assertSame(0.0, $row['debit']);
        $this->assertSame(0.0, $row['credit']);
    }

    /**
     * @param  array<int, string>  $cells
     * @return array{code:int, name:int, credit:int, debit:int, debit_alt:?int, credit_alt:?int}|null
     */
    private function detectHeaderMap(array $cells): ?array
    {
        $service = $this->serviceWithoutConstructor();
        $method = (new ReflectionClass($service))->getMethod('detectHeaderMap');
        $method->setAccessible(true);

        return $method->invoke($service, $cells);
    }

    private function serviceWithoutConstructor(): TrialBalanceOpeningImportService
    {
        return (new ReflectionClass(TrialBalanceOpeningImportService::class))
            ->newInstanceWithoutConstructor();
    }
}
