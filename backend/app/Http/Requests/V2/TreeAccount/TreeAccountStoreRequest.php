<?php

namespace App\Http\Requests\V2\TreeAccount;
use App\Http\Requests\BaseRequest\BaseRequest;
class TreeAccountStoreRequest extends BaseRequest
{
    
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'parent_id' => 'nullable|integer|exists:tree_accounts,id',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'account_type' => 'nullable|in:رئيسي,فرعي,مستوى أول',
            'budget_type' => 'nullable|string|max:255',
            'is_trading_account' => 'nullable|boolean',
            'balance' => 'nullable|numeric|min:0',
            'debit_balance' => 'nullable|numeric|min:0',
            'credit_balance' => 'nullable|numeric|min:0',
            'previous_year_amount' => 'nullable|string|max:255',
            'main_account_id' => 'nullable|integer|exists:tree_accounts,id',
        ];
    }
}
