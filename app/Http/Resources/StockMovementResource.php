<?php

namespace App\Http\Resources;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'type' => $this->type,
            'typeLabel' => $this->type->label(),
            'direction' => $this->direction,

            'productId' => $this->productId,
            'product' => new ProductResource($this->whenLoaded('product')),
            'lotId' => $this->lotId,
            'lot' => new LotResource($this->whenLoaded('lot')),

            'units' => $this->units,
            'kg' => $this->kg,
            'signedUnits' => $this->signedUnits(),
            'signedKg' => $this->signedKg(),
            'unitsBefore' => $this->unitsBefore,
            'unitsAfter' => $this->unitsAfter,
            'kgBefore' => $this->kgBefore,
            'kgAfter' => $this->kgAfter,

            'referenceType' => $this->referenceType,
            'referenceId' => $this->referenceId,
            'notes' => $this->notes,
            'userId' => $this->userId,
            'movementDate' => $this->movementDate,
        ];

        if ($request->user()?->role->canSeeFinances()) {
            $data['costPerUnit'] = $this->costPerUnit;
            $data['costPerKg'] = $this->costPerKg;
            $data['totalCost'] = $this->totalCost;
        }

        return $data;
    }
}
