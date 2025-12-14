<?php

namespace App\Http\Controllers;
use App\Models\Bank;
use App\Models\Expense;
use App\Models\ExpenseKind;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
use Illuminate\Http\Request;
use App\Observers\OrderObserver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\TreeAccount\ExpenseService;


class ExpenseController extends Controller
{
    public function __construct(public ExpenseService $addRecordedTree)
    {
     }
    public function index()
    {
        $data = Expense::with('kind' , 'bank')->get();
        return response()->json($data);
    }

    public function store(Request $request)
    {
        $request->validate(
            [
                "expense_type"=>"in:مصروف ادارى,مصروف تسويق,مصروف تشغيل",
                'bank_id' => 'required|numeric|exists:banks,id',
                'kind_id' => 'required|numeric|exists:expense_kinds,id',
                'expens_statement' => 'required|string',
                'amount' => 'required|numeric|min:0',
                'note' => 'required|string',
                'address' => 'required|string'
            ]);
        $img_name ='';
        if($request->hasFile('expense_image')){
            $img = $request->file('expense_image');
            $img_name = time() . '.' . $img->extension();
            $img->move(public_path('images'), $img_name);
        }


        $expense = Expense::create([
            'expense_type' => request('expense_type'),
            'bank_id' => request('bank_id'),
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


        $bank = Bank::find($request->bank_id);
        $paid = (double)$request->amount;
        $balance =(double) $bank->balance;
        $bank->balance= $balance- $paid;
        $bank->save();


        DB::table('bank_details')->insert([
            'bank_id' => $request->bank_id,
            'details' => $expense->expense_type.' - '.$expenseKind->expense_kind,
            'ref' => $expense->expense_number,
            'type' => 'المصروفات',
            'amount' => (double)$request->amount,
            'balance_before' => $balance,
            'balance_after' => $bank->balance,
            'date' => $request->created_at,
            'created_at' => now(),
            'user_id'=> auth()->user()->id
        ]);

        // 
        
        $this->addRecordedTree->addexpense($request->bank_id,request('kind_id'));
        $this->createAccountTreeBank($bank->name,request('expense_type'),request('amount'));


        return response()->json($expense, 201);
    }

    public function editExpense($id , Request $request)
    {
        $request->validate(
            [
                "expense_type"=>"in:مصروف ادارى,مصروف تسويق,مصروف تشغيل",
                'bank_id' => 'required|numeric|exists:banks,id',
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


        $bank = Bank::find($request->bank_id);
        $paid = (double)$request->amount-$oldExpense->amount;
        $balance =(double) $bank->balance;
        $bank->balance= $balance- $paid;
        $bank->save();


        DB::table('bank_details')->insert([
            'bank_id' => $request->bank_id,
            'details' => ' تعديل '.$expense->expense_type.' - '.$expenseKind->expense_kind.' الخاص برقم '.$oldExpense->expense_number,
            'ref' => $expense->expense_number,
            'type' => 'المصروفات',
            'amount' => (double)$request->amount-$oldExpense->amount,
            'balance_before' => $balance,
            'balance_after' => $bank->balance,
            'date' => date('Y-m-d'),
            'created_at' => now(),
            'user_id'=> auth()->user()->id
        ]);

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
            'bank_id' => $oldExpense->bank_id,
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


        $bank = Bank::find($oldExpense->bank_id);
        $paid = (double)-$oldExpense->amount;
        $balance =(double) $bank->balance;
        $bank->balance= $balance- $paid;
        $bank->save();


        DB::table('bank_details')->insert([
            'bank_id' => $oldExpense->bank_id,
            'details' => ' حذف '.$oldExpense->expense_type.' - '.$expenseKind->expense_kind.' الخاص برقم '.$oldExpense->expense_number,
            'ref' => $oldExpense->expense_number,
            'type' => 'المصروفات',
            'amount' => (double)-$oldExpense->amount,
            'balance_before' => $balance,
            'balance_after' => $bank->balance,
            'date' => date('Y-m-d'),
            'created_at' => now(),
            'user_id'=> auth()->user()->id
        ]);

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

        $search = $search->with('kind', 'bank')
            ->orderBy('id', 'desc')
            ->paginate($itemsPerPage);

        return response()->json($search, 200);
    }

    public function show($id)
    {
        $expense = Expense::with('kind' , 'bank')->find($id);

        if (!$expense) {
            return response()->json(['message' => 'not found'], 404);
        }

        return response()->json($expense, 200);
    }


     public function createAccountTreeBank($bankName, $expenseType, $amount)
    {
        Log::alert(' Expense Type - ' . $expenseType );

        $treeBank = TreeAccount::where('name', $bankName)->where('level', 4)->first();
        $treeExpense = TreeAccount::where('name', $expenseType)->where('level', 4)->first();

        $finalEntries[] = [
            'account_id' => $treeExpense->id ?? null,
            'debit'      => $amount,
            'credit'     => 0,
            'description' => "Expense Recorded",
        ];

        Log::alert('Expense Entry: ' . json_encode($finalEntries));

        $finalEntries[] = [
            'account_id' => $treeBank->id ?? null,
            'debit'      => 0,
            'credit'     => $amount,
            'description' => "Bank Withdrawal - Expense Payment",
        ];
        $batchCode = 'ORD-' . '-' . now()->format('YmdHis');

        foreach ($finalEntries as $entry) {
            if (!$entry['account_id']) continue;

            AccountEntry::create([
                'tree_account_id'  => $entry['account_id'],
                'debit'            => $entry['debit'],
                'credit'           => $entry['credit'],
                'description'      => $entry['description'],
                'order_id'         => null,
                'entry_batch_code' => $batchCode,
            ]);

            $type = $entry['debit'] > 0 ? 'debit' : 'credit';
            $this->updateBalance($entry['account_id'], max($entry['debit'], $entry['credit']), $type);
        }
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
