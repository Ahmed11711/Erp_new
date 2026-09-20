<?php

namespace App\Http\Controllers;

use App\Services\Hr\PayrollAccrualImportService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PayrollAccrualImportController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private PayrollAccrualImportService $importService
    ) {}

    /**
     * معاينة ملف المرتبات ومطابقة الموظفين لصافي/مستحق الراتب.
     */
    public function preview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:20480',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $result = $this->importService->preview(
                $request->file('file'),
                (int) $request->month,
                (int) $request->year
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('payroll accrual import preview failed', ['e' => $e->getMessage()]);

            return $this->errorResponse('فشل قراءة ملف الإكسيل: '.$e->getMessage(), 500);
        }

        return $this->successResponse($result, 'تمت معاينة الملف بنجاح');
    }

    /**
     * تطبيق تحديث المرتبات المستحقة بعد المعاينة.
     * body: month, year, lines[{employee_id, amount}]
     */
    public function apply(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'lines' => 'required|array|min:1',
            'lines.*.employee_id' => 'required|integer|exists:employees,id',
            'lines.*.amount' => 'nullable|numeric|min:0',
            'lines.*.extra_day_value' => 'nullable|numeric|min:0',
            'lines.*.overtime_value' => 'nullable|numeric|min:0',
            'lines.*.rewards' => 'nullable|numeric|min:0',
            'lines.*.allowances' => 'nullable|numeric|min:0',
            'lines.*.deductions' => 'nullable|numeric|min:0',
            'lines.*.advance' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $result = $this->importService->apply(
                (int) $request->month,
                (int) $request->year,
                $request->input('lines', [])
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('payroll accrual import apply failed', ['e' => $e->getMessage()]);

            return $this->errorResponse('فشل تحديث المرتبات المستحقة: '.$e->getMessage(), 500);
        }

        $msg = sprintf(
            'تم تحديث %d مستحق، تخطي %d، فشل %d',
            $result['totals']['updated_count'],
            $result['totals']['skipped_count'],
            $result['totals']['failed_count']
        );

        return $this->successResponse($result, $msg);
    }
}
