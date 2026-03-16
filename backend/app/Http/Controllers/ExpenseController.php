<?php

namespace App\Http\Controllers;
use App\Models\Bank;
use App\Models\Safe;
use App\Models\Expense;
use App\Models\ExpenseKind;
use App\Models\ServiceAccount;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use Illuminate\Http\Request;
use App\Observers\OrderObserver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class ExpenseController extends Controller
{
    public function index()
    {
        $data = Expense::with('kind', 'bank', 'safe', 'serviceAccount')->get();
        return response()->json($data);
    }

    public function store(Request $request)
    {
        $request->validate([
            "expense_type" => "in:مصروف ادارى,مصروف تسويق,مصروف تشغيل",
            'payment_type' => 'nullable|in:safe,bank,service_account',
            'bank_id' => 'nullable|exists:banks,id',
            'safe_id' => 'nullable|exists:safes,id',
            'service_account_id' => 'nullable|exists:service_accounts,id',
            'kind_id' => 'required|numeric|exists:expense_kinds,id',
            'expens_statement' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'note' => 'required|string',
            'address' => 'required|string'
        ]);

        $paymentType = $request->payment_type ?? 'bank';
        $bankId = $request->bank_id;
        $safeId = $request->safe_id;
        $serviceAccountId = $request->service_account_id;

        if ($paymentType === 'bank' && !$bankId) {
            return response()->json(['message' => 'يجب اختيار البنك'], 422);
        }
        if ($paymentType === 'safe' && !$safeId) {
            return response()->json(['message' => 'يجب اختيار الخزينة'], 422);
        }
        if ($paymentType === 'service_account' && !$serviceAccountId) {
            return response()->json(['message' => 'يجب اختيار الحساب الخدمي'], 422);
        }

        $img_name = '';
        if ($request->hasFile('expense_image')) {
            $img = $request->file('expense_image');
            $img_name = time() . '.' . $img->extension();
            $img->move(public_path('images'), $img_name);
        }

        DB::beginTransaction();
        try {
            $expense = Expense::create([
                'expense_type' => request('expense_type'),
                'payment_type' => $paymentType,
                'bank_id' => $paymentType === 'bank' ? $bankId : null,
                'safe_id' => $paymentType === 'safe' ? $safeId : null,
                'service_account_id' => $paymentType === 'service_account' ? $serviceAccountId : null,
                'user_id' => auth()->user()->id,
                'kind_id' => request('kind_id'),
                'expens_statement' => request('expens_statement'),
                'amount' => request('amount'),
                'note' => request('note'),
                'address' => request('address'),
                'created_at' => $request->created_at,
                'expense_image' => $img_name,
            ]);

            $expenseKind = ExpenseKind::find($expense->kind_id);
            $amount = (double) $request->amount;

            // تحديث رصيد مصدر الدفع
            $creditTreeId = null;
            $sourceName = '';

            if ($paymentType === 'safe') {
                $safe = Safe::find($safeId);
                if (!$safe || !$safe->account_id) {
                    throw new \Exception('الخزينة غير مرتبطة بحساب في شجرة الحسابات');
                }
                $creditTreeId = $safe->account_id;
                $sourceName = $safe->name;
                $safe->decrement('balance', $amount);
            } elseif ($paymentType === 'service_account') {
                $svc = ServiceAccount::find($serviceAccountId);
                if (!$svc || !$svc->account_id) {
                    throw new \Exception('الحساب الخدمي غير مرتبط بحساب في شجرة الحسابات');
                }
                $creditTreeId = $svc->account_id;
                $sourceName = $svc->name;
                $svc->decrement('balance', $amount);
            } else {
                $bank = Bank::find($bankId);
                if (!$bank) {
                    throw new \Exception('البنك غير موجود');
                }
                $balanceBefore = (float) $bank->balance;
                $bank->decrement('balance', $amount);
                DB::table('bank_details')->insert([
                    'bank_id' => $bankId,
                    'details' => $expense->expense_type . ' - ' . $expenseKind->expense_kind,
                    'ref' => $expense->expense_number,
                    'type' => 'المصروفات',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $bank->fresh()->balance,
                    'date' => $request->created_at ?? date('Y-m-d'),
                    'created_at' => now(),
                    'user_id' => auth()->user()->id
                ]);
                if ($bank->asset_id) {
                    $creditTreeId = $bank->asset_id;
                    $sourceName = $bank->name;
                }
            }

            // القيد المحاسبي: مدين حساب المصروف، دائن مصدر الدفع (خزينة/بنك/حساب خدمي)
            $this->createExpenseAccountingEntry($expense->expense_type, $amount, $creditTreeId, $sourceName, $expense->expense_number);

            DB::commit();
            return response()->json($expense, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Expense store failed: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * إنشاء القيد المحاسبي للمصروف وتحديث شجرة الحسابات
     */
    protected function createExpenseAccountingEntry($expenseType, $amount, $creditTreeId, $sourceName, $ref)
    {
        $treeExpense = TreeAccount::where('name', $expenseType)->where('level', 4)->first();
        if (!$treeExpense || !$creditTreeId) {
            Log::warning("Expense accounting: missing tree account for expense_type={$expenseType} or creditTreeId={$creditTreeId}");
            return;
        }

        $batchCode = 'EXP-' . now()->format('YmdHis');

        // مدين: حساب المصروف
        AccountEntry::create([
            'tree_account_id' => $treeExpense->id,
            'debit' => $amount,
            'credit' => 0,
            'description' => 'مصروف - ' . $sourceName . ' - ' . $ref,
            'order_id' => null,
            'entry_batch_code' => $batchCode,
        ]);
        $this->updateBalance($treeExpense->id, $amount, 'debit');

        // دائن: مصدر الدفع (خزينة/بنك/حساب خدمي)
        AccountEntry::create([
            'tree_account_id' => $creditTreeId,
            'debit' => 0,
            'credit' => $amount,
            'description' => 'صرف مصروف - ' . $ref,
            'order_id' => null,
            'entry_batch_code' => $batchCode,
        ]);
        $this->updateBalance($creditTreeId, $amount, 'credit');
    }

    public function editExpense($id , Request $request)
    {
        $request->validate(
            [
                "expense_type"=>"in:مصروف ادارى,مصروف تسويق,مصروف تشغيل",
                'bank_id' => 'nullable|exists:banks,id',
                'kind_id' => 'required|numeric|exists:expense_kinds,id',
                'expens_statement' => 'required|string',
                'amount' => 'required|numeric',
                'note' => 'required|string',
                'address' => 'required|string'
            ]);
        $img_name ='';
        if($request->hasFile('expense_image')){
            $img = $request->file('expense_image');
            $img_name = time() . '.' . $img->extension();
            $img->move(public_path('images'), $img_name);
        }

        $oldExpense = Expense::find($id);
        $oldExpense->ref = $oldExpense->expense_number;
        $oldExpense->status = 0;
        $oldExpense->save();

        $expense = Expense::create([
            'expense_type' => request('expense_type'),
            'bank_id' => request('bank_id'),
            'user_id' => auth()->user()->id,
            'kind_id' => request('kind_id'),
            'expens_statement' => request('expens_statement'),
            'amount' => request('amount'),
            'note' => request('note'),
            'address' => request('address'),
            'ref' => $oldExpense->expense_number,
            'status' => 0,
            'expense_image' => $img_name,
        ]);

        // $expense->ref = $oldExpense->expense_number;
        // $expense->status = 0;
        // $expense->save();

        $expenseKind = ExpenseKind::find($expense->kind_id);
        $paid = (double)$request->amount - $oldExpense->amount;

        if ($request->bank_id) {
            $bank = Bank::find($request->bank_id);
            if ($bank) {
                $balance = (double) $bank->balance;
                $bank->balance = $balance - $paid;
                $bank->save();
                DB::table('bank_details')->insert([
                    'bank_id' => $request->bank_id,
                    'details' => ' تعديل '.$expense->expense_type.' - '.$expenseKind->expense_kind.' الخاص برقم '.$oldExpense->expense_number,
                    'ref' => $expense->expense_number,
                    'type' => 'المصروفات',
                    'amount' => $paid,
                    'balance_before' => $balance,
                    'balance_after' => $bank->balance,
                    'date' => date('Y-m-d'),
                    'created_at' => now(),
                    'user_id'=> auth()->user()->id
                ]);
            }
        }

        return response()->json($expense, 201);
    }

    public function deleteExpense($id , Request $request)
    {

        $oldExpense = Expense::find($id);
        $oldExpense->ref = $oldExpense->expense_number;
        $oldExpense->status = 1;
        $oldExpense->save();

        $expense = Expense::create([
            'expense_type' => $oldExpense->expense_type,
            'payment_type' => $oldExpense->payment_type ?? 'bank',
            'bank_id' => $oldExpense->bank_id,
            'safe_id' => $oldExpense->safe_id,
            'service_account_id' => $oldExpense->service_account_id,
            'user_id' => auth()->user()->id,
            'kind_id' => $oldExpense->kind_id,
            'expens_statement' => $oldExpense->expens_statement,
            'amount' => -$oldExpense->amount,
            'note' => $oldExpense->note,
            'address' => $oldExpense->address,
            'ref' => $oldExpense->expense_number,
            'status' => 1,
            'expense_image'=>''
        ]);

        // $expense->ref = $oldExpense->expense_number;
        // $expense->status = 0;
        // $expense->save();

        $expenseKind = ExpenseKind::find($oldExpense->kind_id);
        $paid = (double) -$oldExpense->amount;

        if ($oldExpense->bank_id) {
            $bank = Bank::find($oldExpense->bank_id);
            if ($bank) {
                $balance = (double) $bank->balance;
                $bank->balance = $balance - $paid;
                $bank->save();
                DB::table('bank_details')->insert([
                    'bank_id' => $oldExpense->bank_id,
                    'details' => ' حذف '.$oldExpense->expense_type.' - '.$expenseKind->expense_kind.' الخاص برقم '.$oldExpense->expense_number,
                    'ref' => $oldExpense->expense_number,
                    'type' => 'المصروفات',
                    'amount' => $paid,
                    'balance_before' => $balance,
                    'balance_after' => $bank->balance,
                    'date' => date('Y-m-d'),
                    'created_at' => now(),
                    'user_id'=> auth()->user()->id
                ]);
            }
        } elseif ($oldExpense->safe_id) {
            Safe::where('id', $oldExpense->safe_id)->increment('balance', abs($paid));
        } elseif ($oldExpense->service_account_id) {
            ServiceAccount::where('id', $oldExpense->service_account_id)->increment('balance', abs($paid));
        }

        return response()->json($expense, 201);
    }

    public function search(Request $request){

        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
        $search = Expense::query();
        if($request->has('date_to') && $request->has('date_from')){
            $search->whereBetween('created_at', [
                $request->input('date_from'),
                $request->input('date_to')
            ]);
        }

        $search = $search->with('kind', 'bank', 'safe', 'serviceAccount')
            ->orderBy('id', 'desc')
            ->paginate($itemsPerPage);

        return response()->json($search, 200);
    }

    public function show($id)
    {
        $expense = Expense::with('kind', 'bank', 'safe', 'serviceAccount')->find($id);

        if (!$expense) {
            return response()->json(['message' => 'not found'], 404);
        }

        return response()->json($expense, 200);
    }


    protected function updateBalance($accountId, $amount, $type)
    {
        $account = TreeAccount::find($accountId);
        if (!$account) return;

        $isDebitNormal = in_array($account->type, ['asset', 'expense']);

        if ($type === 'debit') {
            $account->balance += $isDebitNormal ? $amount : -$amount;
        } else { // credit
            $account->balance += $isDebitNormal ? -$amount : $amount;
        }

        $account->save();

        $parent = $account->parent;
        while ($parent) {
            if ($type === 'debit') {
                $parent->balance += $isDebitNormal ? $amount : -$amount;
            } else {
                $parent->balance += $isDebitNormal ? -$amount : $amount;
            }
            $parent->save();

            $parent = $parent->parent;
        }
    }

}
