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
            'code' => $this->code,
            'type' => $this->type,
            'balance' => $this->balance,
            'parent'=>$this->parent->name ?? null,
            'children' => $this->whenLoaded('children', function () {
                return $this->children->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'name' => $child->name,
                        'code' => $child->code,
                        'type' => $child->type,
                        'balance' => $child->balance,

                    ];
                });
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i') ?? null,
            'updated_at' => $this->updated_at->format('Y-m-d H:i') ?? null,
            
        ];
    }
}





//  cach

 