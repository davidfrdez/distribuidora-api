<?php

namespace App\Http\Resources;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Warehouse
 */
class WarehouseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        [$tempMin, $tempMax] = $this->effectiveTempRange();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'warehouseTypeId' => $this->warehouseTypeId,
            'warehouseType' => new WarehouseTypeResource($this->whenLoaded('warehouseType')),
            'tempMin' => $this->tempMin,
            'tempMax' => $this->tempMax,
            // Rango realmente vigente: el propio, o el heredado del tipo de bodega.
            'effectiveTempMin' => $tempMin,
            'effectiveTempMax' => $tempMax,
            'isDefault' => $this->isDefault,
            'isQuarantine' => $this->isQuarantine,
            'sellable' => $this->sellable,
            'canDispatch' => $this->canDispatch(),
            'active' => $this->active,
        ];
    }
}
