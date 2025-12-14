<?php

namespace App\Http\Requests\V2\stock;
use App\Http\Requests\BaseRequest\BaseRequest;
class stockUpdateRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:191',
            'balance' => 'sometimes|required|numeric',
            'asset_id' => 'sometimes|required|integer|exists:tree_accounts,id',
            'active' => 'sometimes|required|integer',
        ];
    }
}
