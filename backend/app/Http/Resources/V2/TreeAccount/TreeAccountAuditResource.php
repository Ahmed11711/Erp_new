<?php

namespace App\Http\Resources\V2\TreeAccount;

use Illuminate\Http\Resources\Json\JsonResource;

class TreeAccountAuditResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tree_account_id' => $this->tree_account_id,
            'action' => $this->action,
            'action_label' => match ($this->action) {
                'created' => 'إضافة',
                'updated' => 'تعديل',
                'deleted' => 'حذف',
                default => $this->action,
            },
            'performed_by' => $this->performed_by,
            'performer' => $this->whenLoaded('performer', function () {
                return [
                    'id' => $this->performer->id,
                    'name' => $this->performer->name,
                ];
            }),
            'account_code' => $this->account_code,
            'account_name' => $this->account_name,
            'parent_id' => $this->parent_id,
            'changes' => $this->changes ?? [],
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
