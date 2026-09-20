<?php

namespace App\Http\Resources\Bank;

use Illuminate\Http\Resources\Json\JsonResource;

class BankResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'balance' => $this->balance,
            'usage' => $this->usage,
            'asset_id' => $this->asset_id,
            'asset_name' => $this->asset ? $this->asset->name : null,
            'asset' => $this->asset,
            'assigned_users' => $this->whenLoaded('assignedUsers', function () {
                return $this->assignedUsers->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'department' => $u->department,
                ])->values();
            }),
            'is_restricted' => $this->whenLoaded('assignedUsers', fn () => $this->assignedUsers->isNotEmpty(), false),
        ];
    }
}
