<?php

namespace App\Http\Controllers;

use App\Models\Covenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CovenantController extends Controller
{
    public function index()
    {
        $rows = Covenant::query()
            ->with([
                'safe:id,name',
                'bank:id,name',
                'serviceAccount:id,name',
                'user:id,name',
            ])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        return response()->json($rows);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_date' => 'required|date',
            'covenant_type' => 'required|string|max:32',
            'holder_kind' => 'required|string|max:64',
            'payment_type' => 'required|in:bank,safe,service_account',
            'bank_id' => 'required_if:payment_type,bank|nullable|exists:banks,id',
            'safe_id' => 'required_if:payment_type,safe|nullable|exists:safes,id',
            'service_account_id' => 'required_if:payment_type,service_account|nullable|exists:service_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $row = Covenant::create([
            'transaction_date' => $request->transaction_date,
            'covenant_type' => $request->covenant_type,
            'holder_kind' => $request->holder_kind,
            'payment_type' => $request->payment_type,
            'safe_id' => $request->payment_type === 'safe' ? $request->safe_id : null,
            'bank_id' => $request->payment_type === 'bank' ? $request->bank_id : null,
            'service_account_id' => $request->payment_type === 'service_account' ? $request->service_account_id : null,
            'amount' => $request->amount,
            'description' => $request->description,
            'note' => $request->note,
            'user_id' => auth()->id(),
        ]);

        return response()->json($row->load(['safe', 'bank', 'serviceAccount', 'user']), 201);
    }
}
