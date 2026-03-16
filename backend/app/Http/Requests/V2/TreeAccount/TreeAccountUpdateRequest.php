<?php

namespace App\Http\Requests\V2\TreeAccount;
use App\Http\Requests\BaseRequest\BaseRequest;
use Illuminate\Support\Facades\Log;

class TreeAccountUpdateRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

public function rules(): array
{
    $treeAccount = $this->route('tree_account');

     $treeAccountId = is_object($treeAccount) ? $treeAccount->id : $treeAccount;

    return [
        'name' => 'sometimes|string|max:255',
        'parent_id' => 'nullable|integer|exists:tree_accounts,id',
        'type' => 'nullable|in:asset,liability,equity,revenue,expense',
        'balance' => 'nullable',
        'budget_type' => 'nullable|string',
        'budget_amount' => 'nullable|numeric|min:0',
        'budget_period' => 'nullable|in:yearly,monthly',
    ];
}


}
