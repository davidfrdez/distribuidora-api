<?php

namespace App\Http\Resources;

use App\Models\CashMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashMovement
 */
class CashMovementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'typeLabel' => $this->type->label(),
            'direction' => $this->direction,
            'amount' => $this->amount,
            'signedAmount' => $this->signedAmount(),
            'concept' => $this->concept,
            'createdAt' => $this->createdAt,
        ];
    }
}
