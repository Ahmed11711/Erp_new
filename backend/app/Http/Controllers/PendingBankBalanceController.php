<?php

namespace App\Http\Controllers;

use App\Models\PendingBankBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PendingBankBalanceController extends Controller
{
    public function pendingBanks(Request $request){
        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
        $search = PendingBankBalance::query()->with(['user' , 'bank']);
        if($request->has('status')){
            $search = $search->where('status' , $request->status);
        }
        $search = $search->orderBy('id' , 'desc')->paginate($itemsPerPage);
        return response()->json($search, 200);
    }

    public function pendingBanksStatus(Request $request){
        DB::beginTransaction();
        try{
            $id = $request->id;
            $pendingBank = PendingBankBalance::find($id);
            $pendingBank->status = $request->status;
            $pendingBank->bank_id = $request->bank_id;
            $pendingBank->save();
            if ($request->status == 'approved') {
                $amount = $pendingBank->amount;
                $details = $pendingBank->details;
                $ref = $pendingBank->ref;
                $type = $pendingBank->type;
                $bank = $request->bank_id;
                $user_id = $pendingBank->user_id;
                DB::statement('CALL update_bank_balance(?, ?, ?, ?, ?, ?, ?, ?)', [
                    $bank, $amount, $order=$ref, $user_id, $details, $ref, $type, now()
                ]);
            }
            DB::commit();
            return response()->json(['message'=>'success'], 201);
        }catch(\Exception $e){
            DB::rollback();
            return response()->json(['message'=>$e->getMessage()], 500);
        }
    }


}
