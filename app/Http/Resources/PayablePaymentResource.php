<?php

namespace App\Http\Resources;

use App\Models\PayablePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PayablePayment
 */
class PayablePaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payableId' => $this->payableId,
            'amount' => $this->amount,
            'paymentDate' => $this->paymentDate->toDateString(),
            'paymentMethod' => $this->paymentMethod,
            'paymentMethodLabel' => $this->paymentMethod->label(),
            'reference' => $this->reference,
            'notes' => $this->notes,
            'createdAt' => $this->createdAt,
        ];
    }
}
