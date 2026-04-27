<?php

namespace App\Http\Requests\V2\TreeAccount;

use App\Http\Requests\BaseRequest\BaseRequest;
use App\Models\TreeAccount;

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
        'name' => [
            'sometimes',
            'string',
            'max:255',
            function ($attribute, $value, $fail) use ($treeAccountId) {
                if ($value === null || $value === '') {
                    return;
                }
                if (TreeAccount::nameAlreadyUsed((string) $value, $treeAccountId ? (int) $treeAccountId : null)) {
                    $fail('اسم الحساب مستخدم مسبقاً');
                }
            },
        ],
        'name_en' => 'nullable|string|max:255',
        'parent_id' => 'nullable|integer|exists:tree_accounts,id',
        'type' => 'nullable|in:asset,liability,equity,revenue,expense,settlement',
        'is_trading_account' => 'nullable|boolean',
        // لا تُضمَّن حقول الرصيد هنا — التعديل يتم عبر القيود / balance-adjustment
        'budget_type' => 'nullable|string',
        'budget_amount' => 'nullable|numeric|min:0',
        'budget_period' => 'nullable|in:yearly,monthly',
    ];
}


}
