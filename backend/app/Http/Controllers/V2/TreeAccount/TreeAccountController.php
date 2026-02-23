<?php

namespace App\Http\Controllers\V2\TreeAccount;

use App\Models\TreeAccount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseController\BaseController;
use App\Http\Resources\V2\TreeAccount\TreeAccountResource;
use App\Http\Requests\V2\TreeAccount\TreeAccountStoreRequest;
use App\Http\Requests\V2\TreeAccount\TreeAccountUpdateRequest;
use App\Repositories\TreeAccount\TreeAccountRepositoryInterface;

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
        'type' => 'required|in:asset,liability,equity,revenue,expense',
        'account_type' => 'nullable|in:رئيسي,فرعي,مستوى أول',
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

    DB::transaction(function () use (&$validated) {

        if (empty($validated['parent_id'])) {
             $lastRoot = TreeAccount::whereNull('parent_id')
                ->orderByDesc('code')
                ->lockForUpdate()
                ->first();

            $validated['code'] = $lastRoot ? $lastRoot->code + 1 : 1;
            $validated['level'] = 1;

        } else {
            $parent = TreeAccount::find($validated['parent_id']);

            if ($parent->type !== $validated['type']) {
                throw new \Exception('Child account type must match parent type');
            }

            // جلب آخر Child موجود تحت هذا الأب مع قفل الصف لمنع التكرار
            $lastChild = TreeAccount::where('parent_id', $parent->id)
                ->orderByDesc('code')
                ->lockForUpdate()
                ->first();

            switch ($parent->level) {
                case 1:
                     $validated['code'] = $lastChild ? $lastChild->code + 1 : ($parent->code * 10 +1);
                    $validated['level'] = 2;
                    break;

                case 2:
                     if (!$lastChild) {
                         if ($parent->code < 100) {
                            $parentCode = (string)$parent->code; // الأب رقمين، مثال: "21"

                             $firstDigit = $parentCode[0];
                            $secondDigit = $parentCode[1];

                             $validated['code'] = (int)($firstDigit . '0' . $secondDigit);                        } else {
                             $validated['code'] = $parent->code * 10 + 1;
                        }
                    } else {
                        $validated['code'] = $lastChild->code + 1;
                    }
                    $validated['level'] = 3;
                    break;

      case 3:
                    // LEVEL 4 → أبناء المستوى الثالث (نفس منطق المستوى 2 و 3)
                    $validated['code'] = $lastChild ? $lastChild->code + 1 : ($parent->code * 10 + 1);
                    $validated['level'] = 4;
                    break;
            }
        }

        // إنشاء الحساب
        $account = $this->repository->create($validated);

        $validated['id'] = $account->id;

    });

    return $this->successResponse(
        new $this->resourceClass(TreeAccount::find($validated['id'])),
        'Account created successfully',
        201
    );
}



    public function show(int $id): JsonResponse
    {
        $record = $this->repository->find($id);
         if (!$record) {
            return $this->errorResponse("Record not found", 404);
        }
        $record->load(['children']);
        // Log::alert("Tree Account Show with Children", ['account'=>$record]);
        return $this->successResponse(
            new TreeAccountResource($record),
            'Tree account with parent and children retrieved successfully'
        );
    }
    public function destroy($id): JsonResponse
    {
        $record = $this->repository->find($id);
        if (!$record) {
            return $this->errorResponse("Record not found", 404);
        }

        $this->deleteChildren($record);

        $record->delete();

        return $this->successResponse(null, 'Account and its children deleted successfully');
    }

    private function deleteChildren($account)
    {
        foreach ($account->children as $child) {
            $this->deleteChildren($child);
            $child->delete();
        }
    }
}
