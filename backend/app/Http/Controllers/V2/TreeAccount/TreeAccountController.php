<?php

namespace App\Http\Controllers\V2\TreeAccount;

use App\Models\TreeAccount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseController\BaseController;
use App\Http\Resources\V2\TreeAccount\TreeAccountResource;
use App\Http\Resources\V2\TreeAccount\TreeAccountAuditResource;
use App\Models\TreeAccountAudit;
use App\Http\Requests\V2\TreeAccount\TreeAccountStoreRequest;
use App\Http\Requests\V2\TreeAccount\TreeAccountUpdateRequest;
use App\Repositories\TreeAccount\TreeAccountRepositoryInterface;
use App\Services\Accounting\ManualBalanceAdjustmentService;
use App\Services\TreeAccount\TreeAccountSoftDeleteService;
use Illuminate\Support\Facades\Validator;

class TreeAccountController extends BaseController
{
    public function __construct(TreeAccountRepositoryInterface $repository)
    {
        parent::__construct();
        $this->initService(repository: $repository, collectionName: 'TreeAccount');
        $this->storeRequestClass = TreeAccountStoreRequest::class;
        $this->updateRequestClass = TreeAccountUpdateRequest::class;
        $this->resourceClass = TreeAccountResource::class;
    }

    /**
     * تسوية رصيد حساب شجري عبر قيد يومي مع حساب مقابل (قيد مزدوج + وصف تدقيق).
     * body: target_balance, counter_account_id, reason?, date?
     */
    public function balanceAdjustment(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'target_balance' => 'required|numeric',
            'counter_account_id' => 'required|integer|exists:tree_accounts,id',
            'reason' => 'nullable|string|max:2000',
            'date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $account = $this->repository->find($id);
        if (!$account) {
            return $this->errorResponse('Record not found', 404);
        }

        $counter = TreeAccount::find((int) $request->counter_account_id);
        if (!$counter) {
            return $this->errorResponse('الحساب المقابل غير موجود', 404);
        }

        $service = app(ManualBalanceAdjustmentService::class);
        $date = $request->input('date') ? \Carbon\Carbon::parse($request->input('date'))->format('Y-m-d') : now()->format('Y-m-d');

        try {
            $result = $service->adjustToTarget(
                $account,
                (float) $request->target_balance,
                $counter,
                $request->input('reason'),
                $date,
                (int) auth()->id()
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('balanceAdjustment failed', ['e' => $e->getMessage()]);

            return $this->errorResponse('فشل إنشاء قيد التسوية: ' . $e->getMessage(), 500);
        }

        return $this->successResponse(
            [
                'daily_entry' => $result['daily_entry'],
                'previous_net_from_entries' => $result['previous_net'],
                'delta_posted' => $result['delta'],
                'target_balance' => $result['target_balance'],
            ],
            'تم تسجيل تسوية الرصيد كقيد يومي بنجاح'
        );
    }

    /**
     * مطابقة عدة أرصدة مع الواقع في قيد يومي واحد.
     * body: counter_account_id, lines: [{ tree_account_id, target_balance }, ...], reason?, date?
     */
    public function bulkBalanceAdjustment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'counter_account_id' => 'required|integer|exists:tree_accounts,id',
            'lines' => 'required|array|min:1',
            'lines.*.tree_account_id' => 'required|integer|exists:tree_accounts,id',
            'lines.*.target_balance' => 'required|numeric',
            'reason' => 'nullable|string|max:2000',
            'date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $merged = [];
        foreach ($request->lines as $line) {
            $aid = (int) $line['tree_account_id'];
            $merged[$aid] = ($merged[$aid] ?? 0.0) + (float) $line['target_balance'];
        }

        $counter = TreeAccount::find((int) $request->counter_account_id);
        if (!$counter) {
            return $this->errorResponse('الحساب المقابل غير موجود', 404);
        }

        $service = app(ManualBalanceAdjustmentService::class);
        $date = $request->input('date') ? \Carbon\Carbon::parse($request->input('date'))->format('Y-m-d') : now()->format('Y-m-d');

        try {
            $result = $service->adjustBatchToTargets(
                $merged,
                $counter,
                $request->input('reason'),
                $date,
                (int) auth()->id()
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('bulkBalanceAdjustment failed', ['e' => $e->getMessage()]);

            return $this->errorResponse('فشل إنشاء قيد المطابقة: ' . $e->getMessage(), 500);
        }

        return $this->successResponse(
            [
                'daily_entry' => $result['daily_entry'],
                'posted_accounts' => $result['posted'],
            ],
            'تم تسجيل مطابقة الأرصدة في قيد يومي واحد'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $data = $this->repository->getAccounts($request);

        return $this->successResponse(
            TreeAccountResource::collection($data),
            'Accounts retrieved successfully'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'name_en' => 'nullable|string',
            'parent_id' => 'nullable|exists:tree_accounts,id',
            'type' => 'required|in:asset,liability,equity,revenue,expense,settlement',
            'budget_type' => 'nullable|string',
            'budget_amount' => 'nullable|numeric|min:0',
            'budget_period' => 'nullable|in:yearly,monthly',
            'is_trading_account' => 'nullable|boolean',
            'balance' => 'sometimes|numeric',
            'debit_balance' => 'sometimes|numeric',
            'credit_balance' => 'sometimes|numeric',
            'previous_year_amount' => 'nullable|string',
            'main_account_id' => 'nullable|exists:tree_accounts,id',
        ]);

        $validated['balance'] = $validated['balance'] ?? 0.00;
        $validated['debit_balance'] = $validated['debit_balance'] ?? 0.00;
        $validated['credit_balance'] = $validated['credit_balance'] ?? 0.00;
        $validated['is_trading_account'] = $validated['is_trading_account'] ?? false;

        if (TreeAccount::nameAlreadyUsed($validated['name'])) {
            return $this->errorResponse('اسم الحساب مستخدم مسبقاً', 422);
        }

        try {
            DB::transaction(function () use (&$validated) {
                if (empty($validated['parent_id'])) {
                    $lastRoot = TreeAccount::withTrashed()
                        ->whereNull('parent_id')
                        ->orderByDesc('code')
                        ->lockForUpdate()
                        ->first();

                    $code = (string) ($lastRoot ? ((int) $lastRoot->code + 1) : 1);

                    while (TreeAccount::withTrashed()->where('code', $code)->exists()) {
                        $code = (string) ((int) $code + 1);
                    }

                    $validated['code'] = $code;
                    $validated['level'] = 1;
                } else {
                    $parent = TreeAccount::find($validated['parent_id']);

                    if (!$parent) {
                        throw new \Exception('الحساب الأب غير موجود');
                    }

                    if ($parent->type !== $validated['type']) {
                        throw new \Exception('Child account type must match parent type');
                    }

                    $lastChild = TreeAccount::queryLastChildUnderParentLocked($parent);
                    $resolved = TreeAccount::resolveNextChildCodeAndLevel($parent, $lastChild);
                    $code = $resolved['code'];

                    while (TreeAccount::withTrashed()->where('code', $code)->exists()) {
                        $code = (string) ((int) $code + 1);
                    }

                    $validated['code'] = $code;
                    $validated['level'] = $resolved['level'];
                }

                // إنشاء الحساب
                $account = $this->repository->create($validated);

                $validated['id'] = $account->id;
            });

            return $this->successResponse(
                new $this->resourceClass(TreeAccount::with(['createdByUser:id,name', 'updatedByUser:id,name'])->find($validated['id'])),
                'Account created successfully',
                201
            );
        } catch (\Throwable $e) {
            Log::error('Error creating tree account', [
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                $e->getMessage() ?: 'Failed to create account',
                422
            );
        }
    }



    public function show(int $id): JsonResponse
    {
        $record = $this->repository->find($id);
         if (!$record) {
            return $this->errorResponse("Record not found", 404);
        }
        $record->load(['children', 'createdByUser:id,name', 'updatedByUser:id,name']);
        // Log::alert("Tree Account Show with Children", ['account'=>$record]);
        return $this->successResponse(
            new TreeAccountResource($record),
            'Tree account with parent and children retrieved successfully'
        );
    }

    public function audits(int $id): JsonResponse
    {
        $record = TreeAccount::withTrashed()->find($id);
        if (! $record) {
            return $this->errorResponse('Record not found', 404);
        }

        $audits = TreeAccountAudit::query()
            ->with('performer:id,name')
            ->where('tree_account_id', $id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return $this->successResponse(
            TreeAccountAuditResource::collection($audits),
            'Tree account audit log retrieved successfully'
        );
    }

    public function trash(): JsonResponse
    {
        $items = app(TreeAccountSoftDeleteService::class)->listTrash();

        return $this->successResponse(
            TreeAccountResource::collection($items),
            'تم جلب الحسابات المحذوفة'
        );
    }

    public function restore(int $id): JsonResponse
    {
        $record = TreeAccount::onlyTrashed()->find($id);
        if (! $record) {
            return $this->errorResponse('الحساب غير موجود في سلة المحذوفات', 404);
        }

        try {
            $restored = app(TreeAccountSoftDeleteService::class)->restore($record);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('tree account restore failed', ['e' => $e->getMessage()]);

            return $this->errorResponse('فشل استرجاع الحساب: '.$e->getMessage(), 500);
        }

        return $this->successResponse(
            new TreeAccountResource($restored),
            'تم استرجاع الحساب وحساباته الفرعية بنجاح',
            200
        );
    }

    public function forceDestroy(int $id): JsonResponse
    {
        $record = TreeAccount::onlyTrashed()->find($id);
        if (! $record) {
            return $this->errorResponse('الحساب غير موجود في سلة المحذوفات', 404);
        }

        try {
            app(TreeAccountSoftDeleteService::class)->forceDelete($record);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('tree account force delete failed', ['e' => $e->getMessage()]);

            return $this->errorResponse('فشل الحذف النهائي: '.$e->getMessage(), 500);
        }

        return $this->successResponse(null, 'تم الحذف النهائي للحساب وجميع فروعه وقيوده المرتبطة');
    }

    public function destroy($id): JsonResponse
    {
        $record = $this->repository->find($id);
        if (!$record) {
            return $this->errorResponse("Record not found", 404);
        }

        try {
            app(TreeAccountSoftDeleteService::class)->softDelete($record);
        } catch (\Throwable $e) {
            Log::error('tree account soft delete failed', ['e' => $e->getMessage()]);

            return $this->errorResponse('فشل حذف الحساب: '.$e->getMessage(), 500);
        }

        return $this->successResponse(null, 'تم نقل الحساب وفروعه إلى سلة المحذوفات — يمكن استرجاعها لاحقاً');
    }
}
