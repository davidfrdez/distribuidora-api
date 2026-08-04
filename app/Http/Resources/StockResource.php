<?php

namespace App\Http\Resources;

use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Stock
 */
class StockResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'productId' => $this->productId,
            'product' => new ProductResource($this->whenLoaded('product')),
            'warehouseId' => $this->warehouseId,
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),

            'currentUnits' => $this->currentUnits,
            'reservedUnits' => $this->reservedUnits,
            'availableUnits' => $this->availableUnits,
            'currentKg' => $this->currentKg,
            'reservedKg' => $this->reservedKg,
            'availableKg' => $this->availableKg,

            'lastMovementAt' => $this->lastMovementAt,
            'lastCountAt' => $this->lastCountAt,
        ];
    }
}
