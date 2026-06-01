<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
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
            'order_id' => $this->order_id,
            'user_id' => $this->user_id,
            'provider' => $this->provider?->value,
            'provider_reference' => $this->provider_reference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status?->value,
            'metadata' => $this->metadata ?? [],
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'order' => $this->whenLoaded('order'),
        ];
    }
}
