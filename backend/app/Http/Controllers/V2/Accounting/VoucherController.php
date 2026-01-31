<?php

namespace App\Http\Controllers\V2\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Voucher::with(['account', 'client', 'supplier', 'user']);

        if ($request->has('voucher_type')) {
            $query->where('voucher_type', $request->voucher_type);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->whereBetween('date', [$request->date_from, $request->date_to]);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('client_or_supplier_name', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 25);
        $vouchers = $query->orderBy('date', 'desc')->paginate($perPage);

        return response()->json($vouchers, 200);
    }


    // ... helper method to handle legacy operational balances ...
    private function updateOperationalBalance($voucher, $reverse = false) {
        $user_id = auth()->id();
        $isClient = $voucher->voucher_type === 'client';
        $amount = $voucher->amount;
        
        // Determine Direction based on Voucher Type & Operation
        // Receipt ( قبض ) : Client pays us -> Client Balance decreases (-) | Supplier pays us -> Supplier Balance increases (+? Refund) usually Supplier Balance is Liability (Credit). 
        // Let's follow standard: 
        // Client (Asset): Debit increases, Credit decreases. Receipt = Credit to Client. So Balance decreases.
        // Supplier (Liability): Credit increases, Debit decreases. Payment = Debit to Supplier. So Balance decreases.
        
        $sign = 1;
        if ($voucher->type === 'receipt') {
             // Receipt: Money come IN. 
             // Logic: Client Balance reduces (Payment received). 
             $sign = -1; 
        } else {
             // Payment: Money go OUT. 
             // Logic: Supplier Balance reduces (Payment made).
             // Logic: Client Balance increases (Refund given).
             $sign = ($isClient) ? 1 : -1;
        }

        // If reversing (for delete/update), flip the sign
        if ($reverse) {
            $sign *= -1;
        }

        $finalAmount = $amount * $sign;

        if ($isClient) {
             if ($voucher->client_id) {
                // Use Stored Procedure for Customer
                // CALL update_customer_company_balance(company_id, amount, bank_id, ref, details, type, user_id, date)
                
                // Determine Bank ID if the account used is a bank
                // For now, we pass null as 'bank_id' usually tracks specific bank balance updates in the SP, 
                // but here we are just updating the CUSTOMER balance. 
                // However, OrdersController passes bank_id. 
                // Let's attempt to map account_id to bank_id if possible, or pass null.
                $bankId = null; 
                // $bank = \App\Models\Bank::where('asset_id', $voucher->account_id)->first(); // Logic to find bank if needed
                
                $details = "سند {$voucher->type} رقم {$voucher->id} - {$voucher->notes}";
                $type = 'سندات'; 

                // DIRECT UPDATE (Replacing Missing Stored Procedure)
                $client = \App\Models\customerCompany::find($voucher->client_id);
                if ($client) {
                    $currentBalance = $client->balance;
                    $client->balance += $finalAmount;
                    $client->save();
    
                    DB::table('customer_company_details')->insert([
                        'bank_id' => $bankId,
                        'customer_company_id' => $client->id,
                        'ref' => $voucher->id,
                        'details' => $details,
                        'type' => $type,
                        'amount' => $finalAmount,
                        'balance_before' => $currentBalance,
                        'balance_after' => $client->balance,
                        'date' => date('Y-m-d'),
                        'created_at' => now(),
                        'user_id' => $user_id
                    ]);
                }
             }
        } else {
            // Supplier Logic
            if ($voucher->supplier_id) {
                $supplier = \App\Models\Supplier::find($voucher->supplier_id);
                if ($supplier) {
                    $oldBalance = $supplier->balance;
                    $supplier->last_balance = $oldBalance;
                    $supplier->balance = $oldBalance + $finalAmount; // Add the signed amount
                    $supplier->save();

                    // Insert into supplier_balance history
                    // Only insert history if NOT reversing? Or insert reverse entry?
                    // Typically history tracks actions. 
                    // If we are DELETING, we might want to just update balance or insert a "Correction" entry.
                    // PurchaseController inserts entry on Delete/Edit.
                    
                    DB::table('supplier_balance')->insert([
                        // 'supplierpay_id' => null, // This table seems to link to supplier_pays OR purchases. It has invoice_id and supplierpay_id.
                        // We need to check if we can link it to voucher. 
                        // The migration we saw made 'invoice_id' nullable. 
                        // Does it have 'voucher_id'? No. 
                        // We might need to use the 'details' or just record the balance change.
                        // Let's check columns: balance_before, balance_after, user_id, invoice_id, supplierpay_id. 
                        // We don't have a voucher_id column. 
                        // We will leave FKs null and rely on the fact that the balance updated.
                        'balance_before' => $oldBalance,
                        'balance_after' => $supplier->balance,
                        'user_id' => $user_id,
                        'created_at' => now(),
                        // 'invoice_id' => null
                    ]);
                }
            }
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Manually Validate Client/Supplier Existence
        if ($request->voucher_type === 'client') {
             if (!$request->client_id || !\App\Models\customerCompany::where('id', $request->client_id)->exists()) {
                 return response()->json(['message' => 'العميل المختار غير صحيح'], 422);
             }
        } elseif ($request->voucher_type === 'supplier') {
             if (!$request->supplier_id || !\App\Models\Supplier::where('id', $request->supplier_id)->exists()) {
                 return response()->json(['message' => 'المورد المختار غير صحيح'], 422);
             }
        }

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'type' => 'required|in:receipt,payment',
            'voucher_type' => 'required|in:client,supplier',
            'account_id' => 'required|exists:tree_accounts,id',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'reference_number' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $voucher = Voucher::create([
                'date' => $request->date,
                'type' => $request->type,
                'voucher_type' => $request->voucher_type,
                'account_id' => $request->account_id,
                'client_id' => $request->voucher_type === 'client' ? $request->client_id : null,
                'supplier_id' => $request->voucher_type === 'supplier' ? $request->supplier_id : null,
                'client_or_supplier_name' => $request->client_or_supplier_name,
                'amount' => $request->amount,
                'notes' => $request->notes,
                'reference_number' => $request->reference_number,
                'user_id' => auth()->id(),
            ]);

            // 1. Get or Create the Partner's Tree Account (Client or Supplier)
            $partnerTreeAccountId = null;
            
            if ($request->voucher_type === 'client') {
                $client = \App\Models\customerCompany::find($request->client_id);
                if (!$client->tree_account_id) {
                    // Auto-create Account
                    $parentAccount = TreeAccount::where('name', 'like', '%العملاء%')->first(); 
                    if (!$parentAccount) {
                        $parentAccount = TreeAccount::firstOrCreate(
                            ['name' => 'العملاء'],
                            ['type' => 'asset', 'balance' => 0, 'code' => '1200'] 
                        );
                    }

                    $newAccount = TreeAccount::create([
                        'name' => $client->name ?? $client->company_name ?? 'Client ' . $client->id,
                        'parent_id' => $parentAccount->id,
                        'code' => $parentAccount->code . $client->id, 
                        'type' => 'asset', 
                        'balance' => 0,
                        'debit_balance' => 0,
                        'credit_balance' => 0,
                    ]);
                    
                    $client->tree_account_id = $newAccount->id;
                    $client->save();
                }
                $partnerTreeAccountId = $client->tree_account_id;

            } else {
                $supplier = \App\Models\Supplier::find($request->supplier_id);
                 if (!$supplier->tree_account_id) {
                     // Auto-create Account
                    $parentAccount = TreeAccount::where('name', 'like', '%الموردين%')->first(); 
                     if (!$parentAccount) {
                        $parentAccount = TreeAccount::firstOrCreate(
                            ['name' => 'الموردين'],
                            ['type' => 'liability', 'balance' => 0, 'code' => '2100'] 
                        );
                    }

                     $newAccount = TreeAccount::create([
                        'name' => $supplier->supplier_name ?? 'Supplier ' . $supplier->id,
                        'parent_id' => $parentAccount->id,
                        'code' => $parentAccount->code . $supplier->id,
                        'type' => 'liability',
                        'balance' => 0,
                        'debit_balance' => 0,
                        'credit_balance' => 0,
                    ]);
                    
                    $supplier->tree_account_id = $newAccount->id;
                    $supplier->save();
                 }
                $partnerTreeAccountId = $supplier->tree_account_id;
            }

            // 2. Identify Debit and Credit Accounts
            $debitAccountId = null;
            $creditAccountId = null;

            if ($request->type === 'receipt') {
                // Receipt: Debit Safe/Bank, Credit Partner
                $debitAccountId = $request->account_id;
                $creditAccountId = $partnerTreeAccountId;
            } else {
                // Payment: Debit Partner, Credit Safe/Bank
                $debitAccountId = $partnerTreeAccountId;
                $creditAccountId = $request->account_id;
            }

            // 3. Create Debit Entry
            AccountEntry::create([
                'tree_account_id' => $debitAccountId,
                'debit' => $request->amount,
                'credit' => 0,
                'description' => "سند {$request->type} - {$voucher->client_or_supplier_name} - {$voucher->notes}",
                'voucher_id' => $voucher->id,
                'created_at' => $request->date,
                'updated_at' => $request->date
            ]);
           
            // Update Balance Logic
            $debitAccount = TreeAccount::find($debitAccountId);
            $debitAccount->increment('debit_balance', $request->amount);
            if (in_array($debitAccount->type, ['asset', 'expense'])) {
                $debitAccount->increment('balance', $request->amount);
            } else {
                $debitAccount->decrement('balance', $request->amount);
            }

            // 4. Create Credit Entry
            AccountEntry::create([
                'tree_account_id' => $creditAccountId,
                'debit' => 0,
                'credit' => $request->amount,
                'description' => "سند {$request->type} - {$voucher->client_or_supplier_name} - {$voucher->notes}",
                'voucher_id' => $voucher->id,
                'created_at' => $request->date,
                'updated_at' => $request->date
            ]);
            
            $creditAccount = TreeAccount::find($creditAccountId);
            $creditAccount->increment('credit_balance', $request->amount);
             if (in_array($creditAccount->type, ['asset', 'expense'])) {
                $creditAccount->decrement('balance', $request->amount);
            } else {
                $creditAccount->increment('balance', $request->amount);
            }

            // Save Model Updates
            $debitAccount->save();
            $creditAccount->save();

            // ------------------------------------------------------------------
            // UPDATE OPERATIONAL BALANCES (Legacy System)
            // ------------------------------------------------------------------
            $this->updateOperationalBalance($voucher, false); // Add Effect

            DB::commit();
            return response()->json([
                'message' => 'تم إنشاء السند بنجاح',
                'data' => $voucher->load(['account', 'client', 'supplier', 'user'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $voucher = Voucher::with(['account', 'client', 'supplier', 'user'])->find($id);
        
        if (!$voucher) {
            return response()->json(['message' => 'السند غير موجود'], 404);
        }

        return response()->json($voucher, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $voucher = Voucher::find($id);
        
        if (!$voucher) {
            return response()->json(['message' => 'السند غير موجود'], 404);
        }

        $validator = Validator::make($request->all(), [
            'date' => 'sometimes|date',
            'type' => 'sometimes|in:receipt,payment',
            'account_id' => 'sometimes|exists:tree_accounts,id',
            'amount' => 'sometimes|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // ------------------------------------------------------------------
            // REVERSE OLD OPERATIONAL BALANCE
            // ------------------------------------------------------------------
            $this->updateOperationalBalance($voucher, true); // Reverse Old Effect

            // Reverse old entries
            $oldEntries = AccountEntry::where('voucher_id', $voucher->id)->get();
            foreach ($oldEntries as $entry) {
                $acct = TreeAccount::find($entry->tree_account_id);
                if ($acct) {
                    if ($entry->debit > 0) {
                        $acct->decrement('debit_balance', $entry->debit);
                        if (in_array($acct->type, ['asset', 'expense'])) {
                            $acct->decrement('balance', $entry->debit);
                        } else {
                            $acct->increment('balance', $entry->debit);
                        }
                    }
                    if ($entry->credit > 0) {
                        $acct->decrement('credit_balance', $entry->credit);
                        if (in_array($acct->type, ['asset', 'expense'])) {
                            $acct->increment('balance', $entry->credit);
                        } else {
                            $acct->decrement('balance', $entry->credit);
                        }
                    }
                    $acct->save();
                }
                $entry->delete();
            }

            // Update voucher
            $voucher->update($request->only([
                'date', 'type', 'account_id', 'amount', 'notes', 'reference_number'
            ]));

            // New Accounting Logic (Copy from Store)
             // 1. Get the Partner's Tree Account (Client or Supplier)
            $partnerTreeAccountId = null;
            if ($voucher->voucher_type === 'client') {
                $client = \App\Models\customerCompany::find($voucher->client_id);
                $partnerTreeAccountId = $client ? $client->tree_account_id : null;
                if (!$partnerTreeAccountId) throw new \Exception("العميل ليس لديه حساب شجري مرتبط.");
            } else {
                $supplier = \App\Models\Supplier::find($voucher->supplier_id);
                $partnerTreeAccountId = $supplier ? $supplier->tree_account_id : null;
                 if (!$partnerTreeAccountId) throw new \Exception("المورد ليس لديه حساب شجري مرتبط.");
            }

            $debitAccountId = null;
            $creditAccountId = null;

            if ($voucher->type === 'receipt') {
                $debitAccountId = $voucher->account_id;
                $creditAccountId = $partnerTreeAccountId;
            } else {
                $debitAccountId = $partnerTreeAccountId;
                $creditAccountId = $voucher->account_id;
            }

            // Create Debit Entry
            AccountEntry::create([
                'tree_account_id' => $debitAccountId,
                'debit' => $voucher->amount,
                'credit' => 0,
                'description' => "سند {$voucher->type} - {$voucher->client_or_supplier_name} - {$voucher->notes}",
                'voucher_id' => $voucher->id,
                'created_at' => $voucher->date,
                'updated_at' => $voucher->date
            ]);
            $debitAccount = TreeAccount::find($debitAccountId);
            $debitAccount->increment('debit_balance', $voucher->amount);
            if (in_array($debitAccount->type, ['asset', 'expense'])) {
                $debitAccount->increment('balance', $voucher->amount);
            } else {
                $debitAccount->decrement('balance', $voucher->amount);
            }

            // Create Credit Entry
            AccountEntry::create([
                'tree_account_id' => $creditAccountId,
                'debit' => 0,
                'credit' => $voucher->amount,
                'description' => "سند {$voucher->type} - {$voucher->client_or_supplier_name} - {$voucher->notes}",
                'voucher_id' => $voucher->id,
                'created_at' => $voucher->date,
                'updated_at' => $voucher->date
            ]);
            $creditAccount = TreeAccount::find($creditAccountId);
            $creditAccount->increment('credit_balance', $voucher->amount);
             if (in_array($creditAccount->type, ['asset', 'expense'])) {
                $creditAccount->decrement('balance', $voucher->amount);
            } else {
                $creditAccount->increment('balance', $voucher->amount);
            }

            $debitAccount->save();
            $creditAccount->save();

            // ------------------------------------------------------------------
            // APPLY NEW OPERATIONAL BALANCE
            // ------------------------------------------------------------------
            $this->updateOperationalBalance($voucher, false); // Add New Effect

            DB::commit();
            return response()->json([
                'message' => 'تم تحديث السند بنجاح',
                'data' => $voucher->load(['account', 'client', 'supplier', 'user'])
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $voucher = Voucher::find($id);
        
        if (!$voucher) {
            return response()->json(['message' => 'السند غير موجود'], 404);
        }

        DB::beginTransaction();
        try {
            // ------------------------------------------------------------------
            // REVERSE OPERATIONAL BALANCE
            // ------------------------------------------------------------------
            $this->updateOperationalBalance($voucher, true); // Reverse Effect

            // Reverse accounting entries
            // Reverse accounting entries
             $oldEntries = AccountEntry::where('voucher_id', $voucher->id)->get();
            foreach ($oldEntries as $entry) {
                $acct = TreeAccount::find($entry->tree_account_id);
                if ($acct) {
                    if ($entry->debit > 0) {
                        $acct->decrement('debit_balance', $entry->debit);
                        if (in_array($acct->type, ['asset', 'expense'])) {
                            $acct->decrement('balance', $entry->debit);
                        } else {
                            $acct->increment('balance', $entry->debit);
                        }
                    }
                    if ($entry->credit > 0) {
                        $acct->decrement('credit_balance', $entry->credit);
                        if (in_array($acct->type, ['asset', 'expense'])) {
                            $acct->increment('balance', $entry->credit);
                        } else {
                            $acct->decrement('balance', $entry->credit);
                        }
                    }
                    $acct->save();
                }
                $entry->delete();
            }

            // Delete voucher
            $voucher->delete();

            DB::commit();
            return response()->json(['message' => 'تم حذف السند بنجاح'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }
}

