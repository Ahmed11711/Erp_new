<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\TreeAccount\AddRecordedService;
use App\Http\Controllers\V2\TreeAccount\AddAssetController;
use App\Models\Transaction;
use Illuminate\Support\Facades\Request;

class OrderObserver
{
    public function __construct(public AddAssetController $addAssetRepo, public AddRecordedService $AddRecordedService) {}

    public function created(Order $order)
    {
        try {
            $this->recordAccountingEntries($order);
        } catch (\Throwable $e) {
            Log::error("OrderObserver: Failed to create account entries", [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function updated(Order $order)
    {
        Log::alert("Observer Triggered");

        try {

            if ($order->wasChanged('order_status') && $order->order_status === 'تم التحصيل') {
                Log::alert("Full Collection");
                // $this->recordAccountingEntries($order, true);
            }

            if ($order->wasChanged('prepaid_amount')) {
                $bankIdFromPayload = Request::input('bank_id');

                $diff = $order->prepaid_amount - ($order->getOriginal('prepaid_amount') ?: 0);

                $this->partcollectOrder($order, $diff, $bankIdFromPayload);
                Log::alert("Partial Collection", [
                    "old" => $order->getOriginal('prepaid_amount'),
                    "new" => $order->prepaid_amount,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("OrderObserver accounting error", [
                'order_id' => $order->id,
                'message' => $e->getMessage()
            ]);
        }
    }


    protected function recordAccountingEntries(Order $order, $isUpdate = false)
    {
        DB::transaction(function () use ($order, $isUpdate) {

            if ($isUpdate) {
                AccountEntry::where('order_id', $order->id)->delete();
            }

            $codeCustomer = TreeAccount::where('name', $order->customer_name)
                ->where('level', 4)
                ->first()?->code;

            if (!$codeCustomer) {
                $add = $this->addAssetRepo->Addcustomer($order->customer_name, $order->customer_type);
                $codeCustomer = $add->code;
            }

            $accounts = [
                'customer'        => $codeCustomer,
                'sales'           => '4011001',
                'vat'             => '3071001',
                'shipping'        => '4031001',
                'prepaid_amount'  => '1011001',
                'discount'        => '1051001',
                'tax_authority'   => '2021001'

            ];

            $accountModels = TreeAccount::whereIn('code', array_values($accounts))
                ->get()
                ->keyBy('code');

            $batchCode = 'ORD-' . $order->id . '-' . now()->format('YmdHis');

            $finalEntries = [];






            if ($order->total_invoice > 0) {
                $finalEntries[] = [
                    'account_id' => $accountModels[$accounts['sales']]->id ?? null,
                    'debit'      => 0,
                    'credit'     => $order->sales,
                    'description' => "ايردات المبيعات",
                ];
            }
            if ($order->discount > 0) {
                $finalEntries[] = [
                    'account_id' => $accountModels[$accounts['discount']]->id ?? null,
                    'debit'      => $order->discount,
                    'credit'     => 0,
                    'description' => "خصم للعميل - ",
                ];
            }
            if ($order->shipping_cost > 0) {
                $finalEntries[] = [
                    'account_id' => $accountModels[$accounts['shipping']]->id ?? null,
                    'debit'      => 0,
                    'credit'     => $order->shipping_cost,
                    'description' => "مصاريف شحن -  ",
                ];
            }

            $finalEntries[] = [
                'account_id' => $accountModels[$accounts['vat']]->id ?? null,
                'debit'      => 0,
                'credit'     => ($order->sales - $order->discount) * 0.14,
                'description' => "القيمة المضافة",
            ];
            // ا ت ص
            $finalEntries[] = [
                'account_id' => $accountModels[$accounts['tax_authority']]->id ?? null,
                'debit' => ($order->sales - $order->discount) * 0.01,
                'credit'     => 0,
                'description' => " ا ت ص",
            ];
            $totalDebit  = array_sum(array_column($finalEntries, 'debit'));
            Log::alert("totalDebit", [$totalDebit]);
            $totalCredit = array_sum(array_column($finalEntries, 'credit'));
            Log::alert("totalCredit", [$totalCredit]);


            $netAmount = $totalCredit - $totalDebit;


            $finalEntries[] = [
                'account_id' => $accountModels[$accounts['customer']]->id ?? null,
                'credit'      => $netAmount > 0 ? 0 : abs($netAmount),
                'debit'     => $netAmount > 0 ? $netAmount : 0,
                'description' => "العميل مدين ",
            ];
            $this->storeInTransaction($order, 'create', $netAmount); // for model transaction

            Log::alert("Net Amount", ['netAmount' => $order]);

            if ($order->prepaid_amount > 0) {



                // for customer credit
                $finalEntries[] = [
                    'account_id' => $accountModels[$accounts['customer']]->id ?? null,
                    'debit'      => 0,
                    'credit'     => $order->prepaid_amount,
                    'description' => "العميل دائن",
                ];

                // for bank credit
                $bankName = $this->AddRecordedService->getBankById($order->bank_id);
                Log::alert("Bank Name", [$this->AddRecordedService->checkFoundBank($bankName)]);

                $finalEntries[] = [
                    'account_id' => $this->AddRecordedService->checkFoundBank($bankName) ?? null,
                    'debit'      => $order->prepaid_amount,
                    'credit'     => 0,
                    'description' => $bankName,
                ];
                $this->storeInTransaction($order, 'update', $netAmount); // for model transaction
            }
            foreach ($finalEntries as $entry) {
                if (!$entry['account_id']) continue;

                AccountEntry::create([
                    'tree_account_id'  => $entry['account_id'],
                    'debit'            => $entry['debit'],
                    'credit'           => $entry['credit'],
                    'description'      => $entry['description'],
                    'order_id'         => $order->id,
                    'entry_batch_code' => $batchCode,
                ]);

                $type = $entry['debit'] > 0 ? 'debit' : 'credit';
                $this->updateBalance($entry['account_id'], max($entry['debit'], $entry['credit']), $type);
            }
        });
    }

    /**
     */
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

    public function partcollectOrder(Order $order, $diff, $bankIdFromPayload)
    {


        DB::transaction(function () use ($order, $diff, $bankIdFromPayload) {

            $finalEntries = [];

            $customerAccount = TreeAccount::where('name', $order->customer_name)
                ->where('level', 4)
                ->first();
            Log::alert("Customer Account Found", ['customerAccount' => $customerAccount?->toArray()]);

            if (!$customerAccount) {
                $add = $this->addAssetRepo->Addcustomer($order->customer_name, $order->customer_type);
                $customerAccount = $add;
                Log::alert("Customer Account Created", ['customerAccount' => $customerAccount?->toArray()]);
            }

            $finalEntries[] = [
                'account_id' => $customerAccount->id,
                'debit'      => $diff,
                'credit'     => 0,
                'description' => "العميل مدين",
            ];

            $bankName = $this->AddRecordedService->getBankById($bankIdFromPayload);
            Log::alert("Bank Name Retrieved", ['bankName' => $bankName]);

            $bankAccountId = $this->AddRecordedService->checkFoundBank($bankName);
            Log::alert("Bank Account ID", ['bankAccountId' => $bankAccountId]);

            $finalEntries[] = [
                'account_id' => $bankAccountId,
                'debit'      => $diff,
                'credit'     => 0,
                'description' => $bankName,
            ];
            $this->storeInTransaction($order, 'update'); // for model transaction

            foreach ($finalEntries as $entry) {
                Log::alert("Creating Account Entry", ['entry' => $entry]);

                if (!$entry['account_id']) {
                    Log::error("Skipping Entry, account_id null", ['entry' => $entry]);
                    continue;
                }

                $accountEntry = AccountEntry::create([
                    'tree_account_id'  => $entry['account_id'],
                    'debit'            => $entry['debit'],
                    'credit'           => $entry['credit'],
                    'description'      => $entry['description'],
                    'order_id'         => $order->id,
                    'entry_batch_code' => 'PARTCOLLECT-' . now()->format('YmdHis'),
                ]);

                Log::alert("Account Entry Created", ['accountEntry' => $accountEntry->toArray()]);

                $type = $entry['debit'] > 0 ? 'debit' : 'credit';
                $this->updateBalance($entry['account_id'], max($entry['debit'], $entry['credit']), $type);
            }
        });
    }

    public function storeInTransaction($order, $type, $netAmount = 0)
    {
        if ($type == 'create') {
            $prepaid_amount = 0;
            $net_total = $netAmount;
        } else {
            $prepaid_amount = $order->prepaid_amount - ($order->getOriginal('prepaid_amount') ?: 0);

            $net_total = 0;
        }
        $data = [
            'order_id' => $order->id,
            'phone' => $order->customer_phone_1 ?? '010161582010',
            'net_total' => $net_total,
            'prepaid_amount' => $prepaid_amount,
        ];
        Transaction::create($data);
    }

    public function createAccountTreeBank($bankName, $expenseType, $amount)
    {

        $treeBank = TreeAccount::where('name', $bankName)->where('level', 4)->first();
        $treeExpense = TreeAccount::where('name', $expenseType)->where('level', 4)->first();

        $finalEntries[] = [
            'account_id' => $treeExpense->id ?? null,
            'debit'      => $amount,
            'credit'     => 0,
            'description' => "Expense Recorded",
        ];

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
}
