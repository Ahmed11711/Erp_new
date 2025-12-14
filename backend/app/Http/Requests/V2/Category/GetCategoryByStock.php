<?php

namespace App\Http\Requests\V2\Category;

use Illuminate\Foundation\Http\FormRequest;

class GetCategoryByStock extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'stock_id' => 'required|integer|exists:stocks,id',
        ];
    }

       public function validationData()
    {
         return $this->query();
    }
}
