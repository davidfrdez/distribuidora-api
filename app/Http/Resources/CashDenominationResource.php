<?php

namespace App\Http\Resources;

use App\Models\CashDenomination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashDenomination
 */
class CashDenominationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'denomination' => $this->denomination,
            'quantity' => $this->quantity,
            'value' => $this->value(),
        ];
    }
}
