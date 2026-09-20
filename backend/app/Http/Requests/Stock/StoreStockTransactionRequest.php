<?php

namespace App\Http\Requests\Stock;

use App\Models\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreStockTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_type_id' => 'required|integer|exists:transaction_types,id',
            'warehouse_id' => 'nullable|integer|exists:stocks,id',
            'document_date' => 'required|date',
            'notes' => 'nullable|string|max:5000',
            'reference_type' => 'nullable|string|max:64',
            'reference_id' => 'nullable|integer|min:1',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:categories,id',
            'items.*.qty' => 'required|numeric|min:0.000001',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.total' => 'nullable|numeric',
            'items.*.to_product_id' => 'nullable|integer|exists:categories,id',
            'items.*.qty_direction' => 'nullable|in:in,out',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $typeId = (int) $this->input('transaction_type_id');
            $type = TransactionType::query()->find($typeId);
            if (! $type) {
                return;
            }
            if ($type->code === 'PURCHASE_ADD') {
                $validator->errors()->add('transaction_type_id', 'فواتير الشراء تُنشأ تلقائياً من وحدة المشتريات.');
            }
            if ($type->code === 'MANUAL_ADJUSTMENT') {
                foreach ($this->input('items', []) as $i => $row) {
                    if (empty($row['qty_direction'])) {
                        $validator->errors()->add(
                            "items.$i.qty_direction",
                            'يجب تحديد اتجاه التعديل in أو out لكل سطر في تسوية الجرد.'
                        );
                    }
                }
            }
            if ($type->code === 'WAREHOUSE_TRANSFER') {
                foreach ($this->input('items', []) as $i => $row) {
                    if (empty($row['to_product_id'])) {
                        $validator->errors()->add(
                            "items.$i.to_product_id",
                            'نقل المخزون يتطلب الصنف الوجهة to_product_id.'
                        );
                    }
                }
            }
            if (in_array($type->code, ['STOCK_OUT', 'SALES_RETURN', 'PURCHASE_RETURN', 'AMANAT_OUT', 'AMANAT_RETURN', 'MANUAL_ADJUSTMENT', 'WAREHOUSE_TRANSFER'], true)
                && ! $this->filled('warehouse_id')) {
                $validator->errors()->add('warehouse_id', 'المخزن مطلوب لهذا النوع من المستندات.');
            }
        });
    }
}
