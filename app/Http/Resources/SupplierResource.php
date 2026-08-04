<?php

namespace App\Http\Resources;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Supplier
 */
class SupplierResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'nit' => $this->nit,
            'contactName' => $this->contactName,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'invimaRegistration' => $this->invimaRegistration,
            'paymentTermDays' => $this->paymentTermDays,
            'notes' => $this->notes,
            'active' => $this->active,
        ];
    }
}
