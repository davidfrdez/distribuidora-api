<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'businessName' => $this->businessName,
            'nit' => $this->nit,
            'address' => $this->address,
            'city' => $this->city,
            'phone' => $this->phone,
            'whatsappPhone' => $this->whatsappPhone,
            'email' => $this->email,
            'invimaRegistration' => $this->invimaRegistration,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'logoPath' => $this->logoPath,
            'logoUrl' => $this->logoUrl,
            'brandColor' => $this->brandColor,
            'tagline' => $this->tagline,
            'minOrderAmount' => $this->minOrderAmount,
            'defaultWeightTolerancePercent' => $this->defaultWeightTolerancePercent,
            'reservationTtlMinutes' => $this->reservationTtlMinutes,
        ];
    }
}
