<?php

namespace App\Http\Requests\V2\TreeAccount;
use App\Http\Requests\BaseRequest\BaseRequest;
class TreeAccountStoreRequest extends BaseRequest
{
    
    public function rules(): array
    {
        return [
        'name' => 'required|string|max:255',
         'parent_id' => 'nullable|integer|exists:tree_accounts,id',
        'type' => 'required|in:asset,liability,equity,revenue,expense',
        'balance'=>'nullable|integer|min:1'
         ];
    }
}
