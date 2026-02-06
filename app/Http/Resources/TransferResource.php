<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransferResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'from_warehouse_id' => $this->from_warehouse_id,
            'to_warehouse_id' => $this->to_warehouse_id,
            'created_by' => $this->created_by,
            'lines' => $this->lines->map(function ($line) {
                return [
                    'inventory_item_id' => $line->inventory_item_id,
                    'quantity' => $line->quantity,
                ];
            })->values(),
            'created_at' => optional($this->created_at)?->toISOString(),
        ];
    }
}
