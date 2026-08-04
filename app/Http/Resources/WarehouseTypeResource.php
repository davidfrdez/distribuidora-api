<?php

namespace App\Http\Resources;

use App\Models\WarehouseType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WarehouseType
 */
class WarehouseTypeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'defaultTempMin' => $this->defaultTempMin,
            'defaultTempMax' => $this->defaultTempMax,
            'requiresColdChain' => $this->requiresColdChain,
            'active' => $this->active,
        ];
    }
}
