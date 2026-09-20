<?php

namespace App\Http\Controllers;

use App\Services\Hr\EmployeeSalaryDisbursementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use App\Models\EmployeeMonthPaid;

class EmployeeMonthPaidController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('itemsPerPage', 50), 200);

        return response()->json(EmployeeMonthPaid::orderByDesc('id')->paginate($perPage));
    }

    public function show(int $id)
    {
        return response()->json(EmployeeMonthPaid::findOrFail($id));
    }

    public function update(Request $request, int $id)
    {
        return response()->json(['message' => 'غير مدعوم'], 405);
    }

    public function destroy(int $id)
    {
        return response()->json(['message' => 'غير مدعوم'], 405);
    }

    public function store(Request $request, EmployeeSalaryDisbursementService $disbursementService)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer',
            'employee_id' => 'required|exists:employees,id',
            'bank_id' => 'nullable|exists:banks,id',
            'source_type' => 'nullable|in:bank,safe,service_account',
            'source_id' => 'nullable|integer',
        ]);

        $sourceType = $request->input('source_type');
        $sourceId = $request->input('source_id');
        if ($request->filled('bank_id') && ! $request->filled('source_type')) {
            $sourceType = 'bank';
            $sourceId = $request->input('bank_id');
        }
        if (! $sourceType || $sourceId === null || $sourceId === '') {
            return response()->json(['message' => 'يجب تحديد مصدر الصرف (بنك أو خزينة أو حساب خدمي)'], 422);
        }

        try {
            $paid = DB::transaction(function () use ($request, $disbursementService, $sourceType, $sourceId) {
                [$paid] = $disbursementService->disburseSingle(
                    (int) $request->employee_id,
                    (float) $request->amount,
                    (int) $request->month,
                    (int) $request->year,
                    (string) $sourceType,
                    (int) $sourceId
                );

                return $paid;
            });

            return response()->json($paid, 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * صرف رواتب عدة موظفين في معاملة واحدة (نفس المصدر والشهر).
     */
    public function bulkStore(Request $request, EmployeeSalaryDisbursementService $disbursementService)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer',
            'source_type' => 'required|in:bank,safe,service_account',
            'source_id' => 'required|integer',
            'payments' => 'required|array|min:1',
            'payments.*.employee_id' => 'required|exists:employees,id',
            'payments.*.amount' => 'required|numeric|min:0.01',
        ]);

        $ids = collect($request->payments)->pluck('employee_id');
        if ($ids->count() !== $ids->unique()->count()) {
            return response()->json(['message' => 'لا يمكن تكرار نفس الموظف أكثر من مرة في طلب الصرف الجماعي'], 422);
        }

        $sum = (float) collect($request->payments)->sum('amount');
        $bal = $disbursementService->totalAvailableBalance($request->source_type, (int) $request->source_id);
        if ($bal + 0.000001 < $sum) {
            return response()->json(['message' => 'رصيد المصدر غير كافٍ لإجمالي المبالغ ('.$sum.') مقابل الرصيد المتاح ('.$bal.')'], 422);
        }

        try {
            $count = DB::transaction(function () use ($request, $disbursementService) {
                $n = 0;
                foreach ($request->payments as $p) {
                    $disbursementService->disburseSingle(
                        (int) $p['employee_id'],
                        (float) $p['amount'],
                        (int) $request->month,
                        (int) $request->year,
                        (string) $request->source_type,
                        (int) $request->source_id
                    );
                    $n++;
                }

                return $n;
            });

            return response()->json(['ok' => true, 'count' => $count], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
