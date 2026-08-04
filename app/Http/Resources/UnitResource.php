<?php

namespace App\Http\Resources;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Unit
 */
class UnitResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'kind' => $this->kind,
            'kindLabel' => $this->kind->label(),
            'factorToBase' => $this->factorToBase,
            'isBase' => $this->isBase,
            'decimals' => $this->decimals,
            'active' => $this->active,
        ];
    }
}
