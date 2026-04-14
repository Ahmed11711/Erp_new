<?php

namespace App\Http\Controllers;

use App\Models\ExpenseKind;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExpenseKindController extends Controller
{
    public function index(){
        $data = ExpenseKind::all();
        return response()->json($data, 200);
    }

    public function store(Request $request){

        Log::alert("Creating Expense Kind: ", [$request->all()]);
        $request->validate([
            "expense_type"=>"in:مصروف ادارى,مصروف تسويق,مصروف تشغيل",
            "expense_kind"=>"required|string",
        ]);
        $data = ExpenseKind::create($request->only(['expense_type', 'expense_kind']));
        return response()->json($data, 201);
    }

    /**
     * تحديث فئة مصروف وربطها بنوع رئيسي (أو تعديل الاسم).
     */
    public function update(Request $request, ExpenseKind $expense_kind)
    {
        $request->validate([
            'expense_type' => 'required|in:مصروف ادارى,مصروف تسويق,مصروف تشغيل',
            'expense_kind' => 'required|string|max:255',
        ]);

        $expense_kind->update($request->only(['expense_type', 'expense_kind']));

        return response()->json($expense_kind->fresh(), 200);
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

        $search = $search->paginate($itemsPerPage);

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
