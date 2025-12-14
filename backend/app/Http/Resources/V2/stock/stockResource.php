<?php

namespace App\Http\Resources\V2\stock;

use Illuminate\Http\Resources\Json\JsonResource;

class stockResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'balance' => $this->balance,
            'asset_id' => $this->asset_id ?? null,
            'asset_name' => $this->asset->name ?? null,
            'created_at' => $this->created_at->format('Y-m-d H:i') ?? null,
            'updated_at' => $this->updated_at->format('Y-m-d H:i') ?? null,
        ];
    }
}
