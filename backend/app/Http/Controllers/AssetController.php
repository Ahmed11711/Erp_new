<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Bank;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\AccountEntry;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssetController extends Controller
{
    public function index()
    {
        $data = Asset::all();
        return response()->json($data, 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            "name" => "required",
            'asset_date' => 'required|date',
            "payment_amount" => "required|numeric",
            "asset_amount" => "required|numeric",
            "purchase_price" => "required|numeric",
            "life_span" => "required|integer",
            "asset_account_id" => "required|exists:tree_accounts,id",
            'payment_source_type' => 'required|in:bank,safe,service_account',
            'bank_id' => 'required_if:payment_source_type,bank|nullable|exists:banks,id',
            'safe_id' => 'required_if:payment_source_type,safe|nullable|exists:safes,id',
            'service_account_id' => 'required_if:payment_source_type,service_account|nullable|exists:service_accounts,id',
        ]);

        try {
            DB::beginTransaction();

            $data = $request->all();
            if (!isset($data['current_value'])) {
                $data['current_value'] = $data['purchase_price'];
            }

            $paymentType = $request->input('payment_source_type', 'bank');
            $data['payment_source_type'] = $paymentType;
            $data['bank_id'] = $paymentType === 'bank' ? $request->input('bank_id') : null;
            $data['safe_id'] = $paymentType === 'safe' ? $request->input('safe_id') : null;
            $data['service_account_id'] = $paymentType === 'service_account' ? $request->input('service_account_id') : null;

            $asset = Asset::create($data);

            $creditAccountId = null;
            if ($paymentType === 'bank') {
                $bank = Bank::findOrFail($request->bank_id);
                $creditAccountId = $bank->asset_id;
                if (!$creditAccountId) {
                    throw new \Exception('البنك غير مرتبط بحساب في شجرة الحسابات (asset_id)');
                }
            } elseif ($paymentType === 'safe') {
                $safe = Safe::findOrFail($request->safe_id);
                $creditAccountId = $safe->account_id;
                if (!$creditAccountId) {
                    throw new \Exception('الخزينة غير مرتبطة بحساب في شجرة الحسابات');
                }
            } else {
                $svc = ServiceAccount::findOrFail($request->service_account_id);
                $creditAccountId = $svc->account_id;
                if (!$creditAccountId) {
                    throw new \Exception('الحساب الخدمي غير مرتبط بحساب في شجرة الحسابات');
                }
            }

            $entry = DailyEntry::create([
                'date' => $asset->asset_date,
                'entry_number' => DailyEntry::getNextEntryNumber(),
                'description' => "Purchase Asset: " . $asset->name,
                'user_id' => auth()->id() ?? 1,
            ]);

            DailyEntryItem::create([
                'daily_entry_id' => $entry->id,
                'account_id' => $asset->asset_account_id,
                'debit' => $asset->purchase_price,
                'credit' => 0,
                'notes' => "Asset Value",
            ]);

            DailyEntryItem::create([
                'daily_entry_id' => $entry->id,
                'account_id' => $creditAccountId,
                'debit' => 0,
                'credit' => $asset->purchase_price,
                'notes' => "Asset Purchase Payment",
            ]);

            AccountEntry::create([
                'tree_account_id' => $asset->asset_account_id,
                'daily_entry_id' => $entry->id,
                'debit' => $asset->purchase_price,
                'credit' => 0,
                'description' => "Purchase Asset: " . $asset->name,
            ]);

            AccountEntry::create([
                'tree_account_id' => $creditAccountId,
                'daily_entry_id' => $entry->id,
                'debit' => 0,
                'credit' => $asset->purchase_price,
                'description' => "Purchase Asset: " . $asset->name,
            ]);

            $accountingService = new AccountingService();
            $accountingService->updateAccountHierarchyBalances($asset->asset_account_id);
            $accountingService->updateAccountHierarchyBalances($creditAccountId);

            DB::commit();

            return response()->json($asset, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to create asset: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create asset',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
