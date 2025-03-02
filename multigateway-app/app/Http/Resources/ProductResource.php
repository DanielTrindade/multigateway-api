<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'name' => $this->name,
            'amount' => $this->amount,
            'amount_formatted' => 'R$ ' . number_format($this->amount / 100, 2, ',', '.'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'pivot' => $this->whenPivotLoaded('transaction_products', function () {
                return [
                    'quantity' => $this->pivot->quantity,
                    'subtotal' => $this->amount * $this->pivot->quantity,
                    'subtotal_formatted' => 'R$ ' . number_format(($this->amount * $this->pivot->quantity) / 100, 2, ',', '.'),
                ];
            }),
            'links' => [
                'self' => route('products.show', $this->id),
            ],
        ];
    }
}
