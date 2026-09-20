<?php

namespace App\Http\Controllers\V2\Accounting;

use App\Http\Controllers\Controller;
use App\Models\TreeAccount;
use App\Services\Accounting\TrialBalanceOpeningImportService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TrialBalanceOpeningImportController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private TrialBalanceOpeningImportService $importService
    ) {}

    /**
     * معاينة ملف ميزان المراجعة: مطابقة الأكواد + الحسابات الناقصة.
     */
    public function preview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:20480',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $result = $this->importService->preview($request->file('file'));
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('trial balance opening import preview failed', ['e' => $e->getMessage()]);

            return $this->errorResponse('فشل قراءة ملف الإكسيل: '.$e->getMessage(), 500);
        }

        return $this->successResponse($result, 'تمت معاينة الملف بنجاح', 200);
    }

    /**
     * إضافة حساب ناقص بنفس كود الإكسيل.
     */
    public function createMissingAccount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'account_code' => 'nullable|string|max:50',
            'account_name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense,settlement',
            'parent_id' => 'nullable|integer|exists:tree_accounts,id',
            'debit' => 'nullable|numeric|min:0',
            'credit' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $code = trim((string) $request->input('account_code', ''));
        if ($code === '' && ! $request->filled('parent_id')) {
            return $this->errorResponse('الحساب بدون كود يتطلب اختيار حساب أب.', 422);
        }

        try {
            $result = $this->importService->createMissingAccount(
                $code !== '' ? $code : null,
                (string) $request->account_name,
                (string) $request->type,
                $request->filled('parent_id') ? (int) $request->parent_id : null,
                (float) ($request->input('debit', 0)),
                (float) ($request->input('credit', 0))
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('trial balance create missing account failed', ['e' => $e->getMessage()]);

            return $this->errorResponse('فشل إنشاء الحساب: '.$e->getMessage(), 500);
        }

        return $this->successResponse($result, 'تم إنشاء الحساب الناقص بنجاح', 201);
    }

    /**
     * ترحيل الأرصدة الافتتاحية بعد المعاينة.
     * body: opening_date, counter_account_id, reason?, lines[{tree_account_id, debit, credit}]
     */
    public function apply(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'opening_date' => 'required|date',
            'counter_account_id' => 'required|integer|exists:tree_accounts,id',
            'reason' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1',
            'lines.*.tree_account_id' => 'required|integer|exists:tree_accounts,id',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
            'lines.*.target_net' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $counter = TreeAccount::find((int) $request->counter_account_id);
        if (! $counter) {
            return $this->errorResponse('الحساب المقابل غير موجود', 404);
        }

        try {
            $result = $this->importService->apply(
                $request->input('lines', []),
                $counter,
                (string) $request->opening_date,
                (int) auth()->id(),
                $request->input('reason')
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('trial balance opening import apply failed', ['e' => $e->getMessage()]);

            return $this->errorResponse('فشل ترحيل الأرصدة: '.$e->getMessage(), 500);
        }

        $syncCount = (int) ($result['operational_sync']['counts']['synced'] ?? 0);
        $message = 'تم استيراد '.$result['posted_count'].' رصيد افتتاحي بتاريخ '.$result['opening_date'];
        if ($syncCount > 0) {
            $message .= ' — وتمت مزامنة '.$syncCount.' رصيد تشغيلي مرتبط';
        }

        return $this->successResponse($result, $message);
    }
}
