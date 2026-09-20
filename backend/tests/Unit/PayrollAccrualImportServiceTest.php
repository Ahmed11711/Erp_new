<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Services\Hr\PayrollAccrualImportService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PayrollAccrualImportServiceTest extends TestCase
{
    public function test_normalize_code_strips_excel_decimal_suffix(): void
    {
        $service = $this->serviceWithoutConstructor();
        $method = (new ReflectionClass($service))->getMethod('normalizeCode');
        $method->setAccessible(true);

        $this->assertSame('12', $method->invoke($service, '12.0'));
        $this->assertSame('12', $method->invoke($service, '12'));
    }

    public function test_parse_amount_handles_thousands_and_parentheses(): void
    {
        $service = $this->serviceWithoutConstructor();
        $method = (new ReflectionClass($service))->getMethod('parseAmount');
        $method->setAccessible(true);

        $this->assertSame(18903.67, $method->invoke($service, '18,903.67'));
        $this->assertSame(-11187.0, $method->invoke($service, '(11,187.00)'));
        $this->assertSame(0.0, $method->invoke($service, ''));
    }

    public function test_detect_header_map_arabic_payroll_sheet(): void
    {
        $service = $this->serviceWithoutConstructor();
        $method = (new ReflectionClass($service))->getMethod('detectHeaderMap');
        $method->setAccessible(true);

        $cells = [
            1 => 'الراتب المستحق',
            2 => 'رقم البصمة',
            3 => 'رقم الموظف',
            4 => 'اسم الموظف',
            5 => 'الوظيفة',
            9 => 'قيمة يوم إضافي',
            12 => 'قيمة الساعة الإضافية',
            13 => 'مكافآت',
            18 => 'سلفة',
            19 => 'خصومات تأخير / غياب',
        ];

        $map = $method->invoke($service, $cells);

        $this->assertNotNull($map);
        $this->assertSame(1, $map['amount']);
        $this->assertSame(2, $map['fingerprint']);
        $this->assertSame(3, $map['code']);
        $this->assertSame(4, $map['name']);
        $this->assertSame(9, $map['extra_day_value']);
        $this->assertSame(12, $map['overtime_value']);
        $this->assertSame(13, $map['rewards']);
        $this->assertSame(18, $map['advance']);
        $this->assertSame(19, $map['delay_deduction']);
    }

    public function test_detect_header_map_net_salary_column(): void
    {
        $service = $this->serviceWithoutConstructor();
        $method = (new ReflectionClass($service))->getMethod('detectHeaderMap');
        $method->setAccessible(true);

        $cells = [
            1 => 'اسم الموظف',
            2 => 'كود الموظف',
            3 => 'صافي الراتب',
        ];

        $map = $method->invoke($service, $cells);

        $this->assertNotNull($map);
        $this->assertSame(3, $map['amount']);
        $this->assertSame(2, $map['code']);
        $this->assertSame(1, $map['name']);
    }

    public function test_apply_known_layout_defaults_fills_detail_columns(): void
    {
        $service = $this->serviceWithoutConstructor();
        $method = (new ReflectionClass($service))->getMethod('applyKnownLayoutDefaults');
        $method->setAccessible(true);

        $map = $method->invoke($service, [
            'amount' => 1,
            'fingerprint' => 2,
            'code' => 3,
            'name' => 4,
            'extra_day_value' => null,
            'overtime_value' => null,
            'rewards' => null,
            'commissions' => null,
            'transport' => null,
            'meals' => null,
            'advance' => null,
        ]);

        $this->assertSame(10, $map['extra_day_value']);
        $this->assertSame(12, $map['overtime_value']);
        $this->assertSame(18, $map['advance']);
    }

    public function test_normalize_name_unifies_arabic_letters_and_titles(): void
    {
        $service = $this->serviceWithoutConstructor();
        $method = (new ReflectionClass($service))->getMethod('normalizeName');
        $method->setAccessible(true);

        $this->assertSame(
            'احمد محمد علي',
            $method->invoke($service, 'أحمد محمد على')
        );
        $this->assertSame(
            'احمد محمد علي',
            $method->invoke($service, 'الأستاذ أحمد محمد علي')
        );
    }

    public function test_resolve_employee_prefers_unique_fingerprint_name_over_serial_code(): void
    {
        $service = $this->serviceWithoutConstructor();
        $method = (new ReflectionClass($service))->getMethod('resolveEmployee');
        $method->setAccessible(true);

        $named = new Employee();
        $named->id = 56;
        $named->name = 'أحمد محمد علي';
        $named->code = '88';
        $named->acc_no = '1204';

        $serial = new Employee();
        $serial->id = 1;
        $serial->name = 'موظف آخر';
        $serial->code = '3';
        $serial->acc_no = null;

        [$matched, $by] = $method->invoke(
            $service,
            '3',
            '',
            'أحمد محمد على',
            ['3' => $serial, '88' => $named],
            ['1204' => $named],
            [
                'احمد محمد علي' => [$named],
                'موظف اخر' => [$serial],
            ]
        );

        $this->assertSame(56, $matched->id);
        $this->assertSame('name', $by);
    }

    public function test_resolve_employee_fuzzy_matches_fingerprint_employee_by_partial_name(): void
    {
        $service = $this->serviceWithoutConstructor();
        $method = (new ReflectionClass($service))->getMethod('resolveEmployee');
        $method->setAccessible(true);

        $named = new Employee();
        $named->id = 70;
        $named->name = 'محمد عبد الله حسن';
        $named->code = '22';
        $named->acc_no = '900';

        [$matched, $by] = $method->invoke(
            $service,
            '',
            '',
            'محمد عبد الله',
            ['22' => $named],
            ['900' => $named],
            ['محمد عبد الله حسن' => [$named]]
        );

        $this->assertSame(70, $matched->id);
        $this->assertSame('name', $by);
    }

    public function test_detect_header_map_accepts_name_column_labelled_alism(): void
    {
        $service = $this->serviceWithoutConstructor();
        $method = (new ReflectionClass($service))->getMethod('detectHeaderMap');
        $method->setAccessible(true);

        $map = $method->invoke($service, [
            1 => 'الاسم',
            2 => 'الراتب المستحق',
        ]);

        $this->assertNotNull($map);
        $this->assertSame(1, $map['name']);
        $this->assertSame(2, $map['amount']);
    }

    private function serviceWithoutConstructor(): PayrollAccrualImportService
    {
        return (new ReflectionClass(PayrollAccrualImportService::class))
            ->newInstanceWithoutConstructor();
    }
}
