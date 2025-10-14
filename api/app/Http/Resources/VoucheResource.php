<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucheResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vouched_by_id' => $this->vouched_by_id,
            'vouched_for_id' => $this->vouched_for_id,
            'vouched_by' => new SupplierResource($this->whenLoaded('vouchedBy')),
            'vouched_for' => new SupplierResource($this->whenLoaded('vouchedFor')),
            'message' => $this->message,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
