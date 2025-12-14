<?php

namespace App\Http\Requests\V2\stock;
use App\Http\Requests\BaseRequest\BaseRequest;
class stockStoreRequest extends BaseRequest
{   
    public function rules(): array
    {
        return [
            'name'      => 'required|string|max:191',
            'balance'   => 'sometimes|numeric',
            'asset_id'  => 'required|integer|exists:tree_accounts,id',
            'active'    => 'sometimes|integer',
        ];
    }
}
