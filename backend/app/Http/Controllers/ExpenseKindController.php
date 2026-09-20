<?php

namespace App\Http\Controllers;

use App\Models\ExpenseKind;
use App\Models\TreeAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExpenseKindController extends Controller
{
    public function ledgerAccounts()
    {
        $parent = TreeAccount::ensureCashOutExpenseParent();
        $accounts = TreeAccount::cashOutExpenseLeafAccounts()
            ->map(fn (TreeAccount $acc) => [
                'id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
            ])
            ->values();

        return response()->json([
            'parent' => [
                'id' => $parent->id,
                'code' => $parent->code,
                'name' => $parent->name,
            ],
            'accounts' => $accounts,
        ], 200);
    }

    public function index()
    {
        $data = ExpenseKind::with('treeAccount')->orderBy('id')->get();

        return response()->json($data, 200);
    }

    public function store(Request $request)
    {
        Log::alert('Creating Expense Kind: ', [$request->all()]);
        $validated = $request->validate([
            'expense_type' => 'required|in:مصروف ادارى,مصروف تسويق,مصروف تشغيل',
            'expense_kind' => 'required|string|max:255',
            'tree_account_id' => 'nullable|integer|exists:tree_accounts,id',
        ]);
        $data = ExpenseKind::create($validated);
        if (empty($validated['tree_account_id'])) {
            TreeAccount::ensureExpenseKindLedgerAccount($data, $validated['expense_type']);
            $data->refresh();
        }

        return response()->json($data->load('treeAccount'), 201);
    }

    /**
     * تحديث فئة مصروف وربطها بنوع رئيسي (أو تعديل الاسم).
     */
    public function update(Request $request, ExpenseKind $expense_kind)
    {
        $validated = $request->validate([
            'expense_type' => 'required|in:مصروف ادارى,مصروف تسويق,مصروف تشغيل',
            'expense_kind' => 'required|string|max:255',
            'tree_account_id' => 'nullable|integer|exists:tree_accounts,id',
        ]);
        $expense_kind->update($validated);
        if (empty($validated['tree_account_id'])) {
            TreeAccount::ensureExpenseKindLedgerAccount($expense_kind, $validated['expense_type']);
        }

        return response()->json($expense_kind->fresh()->load('treeAccount'), 200);
    }

    public function search(Request $request){

        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
        $search = ExpenseKind::query();
        if($request->has('type')){
            $search->where('expense_type', $request->type);
        }

        if($request->has('state')){
            $search->where('id', $request->state);
        }

        $search = $search->with('treeAccount')->orderBy('id')->paginate($itemsPerPage);

        return response()->json($search, 200);
    }

    public function destroy($id)
    {
        $data = ExpenseKind::find($id);
        if(!$data){
        return response()->json(['error' => 'Not Found'], 404);
        }
        $data->delete();
        return response()->json('deleted sucuessfully');
    }

    public function storeAccountTree($data)
    {
        Log::alert("Expense Kind Created: ", [$data]);

       
    }


}
