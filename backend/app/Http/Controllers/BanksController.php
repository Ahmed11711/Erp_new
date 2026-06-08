<?php

namespace App\Http\Controllers;

use Validator;
use Carbon\Carbon;
use App\Models\Bank;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Http\Resources\Bank\BankResource;
use App\Services\TreeAccount\AddRecordedService;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\BankOperationalLedgerService;

class BanksController extends Controller
{
    public function __construct(public AddRecordedService $addRecordedService)
    {}

    public function index(){
        $banks = Bank::where('type', 'main')->get();
        return response(BankResource::collection($banks),200);
    }

    public function bankSelect(){
        $data = Bank::where('type', 'main')->select('id', 'name')->get();
        return response()->json($data, 200);
    }

    public function store(Request $request){
        $balance = (float) $request->input('balance', 0);
        $parentId = $request->input('parent_account_id') ?? $request->input('asset_id');

        $rules = [
            'name' => 'required',
            'balance' => 'required|numeric|min:0',
            'usage' => 'required',
            'parent_account_id' => 'nullable|exists:tree_accounts,id',
            'asset_id' => 'nullable|exists:tree_accounts,id',
            'counter_account_id' => 'nullable|exists:tree_accounts,id',
        ];
        if ($balance > 0.000001) {
            $rules['counter_account_id'] = 'required|exists:tree_accounts,id';
        }
        $request->validate($rules);

        if (!$parentId) {
            return response()->json([
                'errors' => ['parent_account_id' => ['يجب اختيار الحساب الأب في شجرة الحسابات']],
            ], 422);
        }

        DB::beginTransaction();
        try {
            $parentAccount = TreeAccount::find($parentId);

            $lastChild = TreeAccount::where('parent_id', $parentAccount->id)
                ->orderByDesc('code')
                ->lockForUpdate()
                ->first();

            switch ($parentAccount->level) {
                case 1:
                    $newCode = $lastChild ? $lastChild->code + 1 : ($parentAccount->code * 10 + 1);
                    $newLevel = 2;
                    break;
                case 2:
                    if (!$lastChild) {
                        if ($parentAccount->code < 100) {
                            $parentCode = (string) $parentAccount->code;
                            $newCode = (int) ($parentCode[0] . '0' . $parentCode[1]);
                        } else {
                            $newCode = $parentAccount->code * 10 + 1;
                        }
                    } else {
                        $newCode = $lastChild->code + 1;
                    }
                    $newLevel = 3;
                    break;
                case 3:
                    $newCode = $lastChild ? $lastChild->code + 1 : ($parentAccount->code * 10 + 1);
                    $newLevel = 4;
                    break;
                default:
                    $newCode = $lastChild ? $lastChild->code + 1 : ($parentAccount->code * 10 + 1);
                    $newLevel = ($parentAccount->level ?? 1) + 1;
                    break;
            }

            $childAccount = TreeAccount::create([
                'name' => 'بنك - ' . $request->name,
                'name_en' => 'Bank - ' . $request->name,
                'code' => $newCode,
                'type' => $parentAccount->type,
                'level' => $newLevel,
                'parent_id' => $parentAccount->id,
                'balance' => 0,
                'debit_balance' => 0,
                'credit_balance' => 0,
                'is_trading_account' => false,
                'detail_type' => 'bank',
            ]);

            Bank::create([
                'name' => $request->name,
                'type' => 'main',
                'balance' => $balance,
                'usage' => $request->usage,
                'asset_id' => $childAccount->id,
            ]);

            if ($balance > 0.000001) {
                $bankAccountId = (int) $childAccount->id;
                $counterId = (int) $request->counter_account_id;
                if ($bankAccountId === $counterId) {
                    throw new \Exception('الحساب المقابل يجب أن يكون مختلفاً عن حساب البنك');
                }
                $now = now();
                $desc = 'رصيد افتتاحي بنك - ' . $request->name;
                AccountEntry::create([
                    'tree_account_id' => $bankAccountId,
                    'debit' => $balance,
                    'credit' => 0,
                    'description' => $desc,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                AccountEntry::create([
                    'tree_account_id' => $counterId,
                    'debit' => 0,
                    'credit' => $balance,
                    'description' => $desc,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                app(AccountingService::class)->updateAccountHierarchyBalances($bankAccountId);
                app(AccountingService::class)->updateAccountHierarchyBalances($counterId);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response(["message"=>"success"],201);
    }

    public function update(Request $request, $id)
    {
        $bank = Bank::findOrFail($id);
        $request->validate([
            'name' => 'required',
            'usage' => 'required',
        ]);

        $oldName = $bank->name;
        $bank->update([
            'name' => $request->name,
            'usage' => $request->usage,
        ]);

        if ($request->name !== $oldName && $bank->asset_id) {
            $treeAccount = TreeAccount::find($bank->asset_id);
            if ($treeAccount && $treeAccount->detail_type === 'bank') {
                $treeAccount->update([
                    'name' => 'بنك - ' . $request->name,
                    'name_en' => 'Bank - ' . $request->name,
                ]);
            }
        }

        return response(["message" => "success"], 200);
    }


        /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $bank = Bank::find($id);

        if (!$bank) {
            return response()->json(['message' => 'البنك غير موجود'], 404);
        }

        if ($bank->balance != 0) {
            return response()->json(['message' => 'لا يمكن حذف البنك لأن رصيده لا يساوي صفر'], 422);
        }

        DB::beginTransaction();
        try {
            $accountId = $bank->asset_id;
            $bank->delete();

            if ($accountId) {
                $treeAccount = TreeAccount::find($accountId);
                if ($treeAccount && $treeAccount->detail_type === 'bank') {
                    $hasEntries = AccountEntry::where('tree_account_id', $accountId)->exists();
                    if (!$hasEntries) {
                        $treeAccount->delete();
                    }
                }
            }

            DB::commit();
            return response()->json(['message' => 'تم حذف البنك بنجاح'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $itemsPerPage = $request->input('itemsPerPage', 15);

        $bankName = Bank::where('id', $id)->value('name');

        $data = DB::table('bank_details')
        ->join('users', 'bank_details.user_id', '=', 'users.id')
        ->select('bank_details.*', 'users.name')
        ->where('bank_id', $id)
        ->orderBy('bank_details.created_at', 'desc')
        ->orderBy('bank_details.id', 'desc')
        ->paginate($itemsPerPage);


        $result = [
            'data' => $data,
            $bankName,
        ];

        return response()->json($result, 200);
    }

    public function depositBank(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string',
            'counter_account_id' => 'required|exists:tree_accounts,id',
        ]);

        $bank = Bank::findOrFail($id);

        DB::beginTransaction();
        try {
            app(BankOperationalLedgerService::class)->deposit(
                $bank,
                (float) $request->amount,
                (int) $request->counter_account_id,
                $request->reason
            );
            DB::commit();

            return response()->json('success', 200);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function editBankBalance(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'reason' => 'required|string',
            'counter_account_id' => 'required|exists:tree_accounts,id',
        ]);

        $bank = Bank::findOrFail($id);

        DB::beginTransaction();
        try {
            app(BankOperationalLedgerService::class)->adjustToTargetBalance(
                $bank,
                (float) $request->amount,
                (int) $request->counter_account_id,
                $request->reason
            );
            DB::commit();

            return response()->json('success', 200);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function withDrawBank(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string',
            'counter_account_id' => 'required|exists:tree_accounts,id',
        ]);

        $bank = Bank::findOrFail($id);

        DB::beginTransaction();
        try {
            app(BankOperationalLedgerService::class)->withdraw(
                $bank,
                (float) $request->amount,
                (int) $request->counter_account_id,
                $request->reason
            );
            DB::commit();

            return response()->json('success', 200);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function transferMoney(Request $request)
    {
        $request->validate([
            'bankFrom' => 'required|exists:banks,id',
            'bankTo' => 'required|exists:banks,id|different:bankFrom',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string',
        ]);

        $bankFromData = Bank::findOrFail($request->bankFrom);
        $bankToData = Bank::findOrFail($request->bankTo);

        DB::beginTransaction();
        try {
            app(BankOperationalLedgerService::class)->transfer(
                $bankFromData,
                $bankToData,
                (float) $request->amount,
                $request->reason
            );
            DB::commit();

            return response()->json('success', 200);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

}
