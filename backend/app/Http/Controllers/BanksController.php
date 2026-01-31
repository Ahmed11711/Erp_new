<?php

namespace App\Http\Controllers;

use Validator;
use Carbon\Carbon;
use App\Models\Bank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Http\Resources\Bank\BankResource;
use App\Services\TreeAccount\AddRecordedService;

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
        $request->validate([
            'name'=>'required',
            'balance'=>'required', //block update
            'usage'=>'required',
            'asset_id'=>'required|exists:tree_accounts,id',
        ]);

        Bank::create([
            'name'=>$request->name,
            'type'=>'main',
            'balance'=>$request->balance,
            'usage'=>$request->usage,
            'asset_id'=>$request->asset_id,
        ]);
        // $this->addRecordedService->checkFoundBank($name);
        return response(["message"=>"success"],201);
    }

    public function update(Request $request, $id)
    {
        $bank = Bank::findOrFail($id);
        $request->validate([
            'name'=>'required',
            'usage'=>'required',
            'asset_id'=>'required|exists:tree_accounts,id',
        ]);
        
        $bank->update([
            'name' => $request->name,
            'type' => 'بنك', // Force type to be 'بنك'
            'usage' => $request->usage,
            'asset_id' => $request->asset_id
        ]);
        
        return response(["message"=>"success"], 200);
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

        $bank->delete();

        return response()->json(['message' => 'تم حذف البنك بنجاح'], 200);
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
        $bank = Bank::find($id);
        $ref = 'D1';
        $lastRef = DB::table('bank_details')->where('type', 'ايداع')->latest()->first();
        if ($lastRef) {
            $lastRefNumber = (int)substr($lastRef->ref, 1);
            $ref = 'D' . ($lastRefNumber + 1);
        }
        DB::table('bank_details')->insert([
            'bank_id' => $id,
            'details' => $request->reason,
            'ref' => $ref ,
            'type' => 'ايداع',
            'amount' => (double)$request->amount,
            'balance_before' => $bank->balance,
            'balance_after' => $bank->balance + $request->amount,
            'date' => Carbon::now()->format('Y-m-d'),
            'created_at' => now(),
            'user_id'=> auth()->user()->id
        ]);

        $bank->balance = $bank->balance + $request->amount;
        $bank->save();
        return response()->json('success' , 200);
    }

    public function editBankBalance(Request $request, $id)
    {
        $bank = Bank::find($id);
        $ref = 'E1';
        $lastRef = DB::table('bank_details')->where('type', 'تعديل')->latest()->first();
        if ($lastRef) {
            $lastRefNumber = (int)substr($lastRef->ref, 1);
            $ref = 'E' . ($lastRefNumber + 1);
        }
        DB::table('bank_details')->insert([
            'bank_id' => $id,
            'details' => $request->reason,
            'ref' => $ref ,
            'type' => 'تعديل',
            'amount' => (double)$request->amount-$bank->balance,
            'balance_before' => $bank->balance,
            'balance_after' => $request->amount,
            'date' => Carbon::now()->format('Y-m-d'),
            'created_at' => now(),
            'user_id'=> auth()->user()->id
        ]);

        $bank->balance = $request->amount;
        $bank->save();
        return response()->json('success' , 200);
    }

    public function withDrawBank(Request $request, $id)
    {
        $bank = Bank::find($id);
        $ref = 'W1';
        $lastRef = DB::table('bank_details')->where('type', 'سحب')->latest()->first();
        if ($lastRef) {
            $lastRefNumber = (int)substr($lastRef->ref, 1);
            $ref = 'W' . ($lastRefNumber + 1);
        }
        DB::table('bank_details')->insert([
            'bank_id' => $id,
            'details' => $request->reason,
            'ref' => $ref ,
            'type' => 'سحب',
            'amount' => (double)$request->amount,
            'balance_before' => $bank->balance,
            'balance_after' => $bank->balance - $request->amount,
            'date' => Carbon::now()->format('Y-m-d'),
            'created_at' => now(),
            'user_id'=> auth()->user()->id
        ]);

        $bank->balance = $bank->balance - $request->amount;
        $bank->save();
        return response()->json('success' , 200);
    }

    public function transferMoney(Request $request)
    {

        $bankFrom = $request->bankFrom;
        $bankTo = $request->bankTo;
        $amount = $request->amount;
        $reason = $request->reason;
        $bankFromData = Bank::find($bankFrom);
        $bankToData = Bank::find($bankTo);
        $ref = 'T1';
        $lastRef = DB::table('bank_details')->where('type', 'تحويل')->latest()->first();
        if ($lastRef) {
            $lastRefNumber = (int)substr($lastRef->ref, 1);
            $ref = 'T' . ($lastRefNumber + 1);
        }
        DB::table('bank_details')->insert([
            'bank_id' => $bankFrom,
            'details' => 'تحويل الي '.$bankToData->name.' - '.$reason,
            'ref' => $ref ,
            'type' => 'تحويل',
            'amount' => (double)$amount,
            'balance_before' => $bankFromData->balance,
            'balance_after' => $bankFromData->balance - $amount,
            'date' => Carbon::now()->format('Y-m-d'),
            'created_at' => now(),
            'user_id'=> auth()->user()->id
        ]);
        DB::table('bank_details')->insert([
            'bank_id' => $bankTo,
            'details' => ' استلام من '.$bankFromData->name.' - '.$reason,
            'ref' => $ref ,
            'type' => 'تحويل',
            'amount' => (double)$amount,
            'balance_before' => $bankToData->balance,
            'balance_after' => $bankToData->balance + $amount,
            'date' => Carbon::now()->format('Y-m-d'),
            'created_at' => now(),
            'user_id'=> auth()->user()->id
        ]);

        $bankFromData->balance = $bankFromData->balance - $amount;
        $bankToData->balance = $bankToData->balance + $amount;
        $bankFromData->save();
        $bankToData->save();
        return response()->json('success' , 200);
    }

}
