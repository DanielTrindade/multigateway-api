<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
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
            'external_id' => $this->external_id,
            'status' => $this->status,
            'amount' => $this->amount,
            'amount_formatted' => 'R$ ' . number_format($this->amount / 100, 2, ',', '.'),
            'card_last_numbers' => $this->card_last_numbers,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'client' => new ClientResource($this->whenLoaded('client')),
            'gateway' => new GatewayResource($this->whenLoaded('gateway')),
            'products' => ProductResource::collection($this->whenLoaded('products')),
            'links' => [
                'self' => route('transactions.show', $this->id),
                'refund' => route('transactions.refund', $this->id),
            ],
        ];
    }
}
