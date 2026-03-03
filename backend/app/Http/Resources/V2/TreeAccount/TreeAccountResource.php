<?php

namespace App\Http\Resources\V2\TreeAccount;

use Illuminate\Http\Resources\Json\JsonResource;

class TreeAccountResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_en' => $this->name_en,
            'code' => $this->code,
            'type' => $this->type,
            'account_type' => $this->account_type,
            'budget_type' => $this->budget_type,
            'budget_amount' => $this->budget_amount ?? null,
            'budget_period' => $this->budget_period ?? null,
            'is_trading_account' => $this->is_trading_account,
            'level' => $this->level,
            'parent_id' => $this->parent_id,
            'balance' => $this->balance,
            'debit_balance' => $this->debit_balance,
            'credit_balance' => $this->credit_balance,
            'previous_year_amount' => $this->previous_year_amount,
            'total_balance' => $this->total_balance,
            'parent' => $this->whenLoaded('parent', function () {
                return [
                    'id' => $this->parent->id,
                    'name' => $this->parent->name,
                    'code' => $this->parent->code,
                    'level' => $this->parent->level,
                ];
            }),
            'main_account' => $this->whenLoaded('mainAccount', function () {
                return [
                    'id' => $this->mainAccount->id,
                    'name' => $this->mainAccount->name,
                    'code' => $this->mainAccount->code,
                ];
            }),
            // Always return children as full nested resources when available,
            // whether they were loaded via relation or built manually (repository tree).
            'children' => $this->children && $this->children->count() > 0
                ? self::collection($this->children)
                : [],
            'created_at' => $this->created_at->format('Y-m-d H:i') ?? null,
            'updated_at' => $this->updated_at->format('Y-m-d H:i') ?? null,
        ];
    }
}





//  cach

 