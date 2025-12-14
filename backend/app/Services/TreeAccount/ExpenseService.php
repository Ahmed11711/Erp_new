<?php

namespace App\Services\TreeAccount;

use App\Models\Bank;
use App\Models\ExpenseKind;
use App\Models\TreeAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\TreeAccount\AddRecordedService;

class ExpenseService
{
    public function __construct(public AddRecordedService $addRecordedTree){

    }
       public function addexpense($bank_id,$kind_id)
       {
        // Your logic to add expense to TreeAccount
        // For example, you might want to create a new TreeAccount entry for the expense
        // or update an existing one based on the bank_id and kind_id

        $bank = Bank::find($bank_id);
        if (!$bank) {
            throw new \Exception("Bank not found (ID: {$bank_id})");    
       }
       $addBank=$this->addRecordedTree->checkFoundBank($bank->name);

       $kindId=ExpenseKind::find($kind_id);
       if (!$kindId) {
        throw new \Exception("Expense Kind not found (ID: {$kind_id})");        
    }
            $expenseKind = ExpenseKind::find($kind_id);
            

        }
}