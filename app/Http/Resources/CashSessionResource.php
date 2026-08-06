<?php

namespace App\Http\Resources;

use App\Models\CashSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashSession
 */
class CashSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'statusLabel' => $this->status->label(),

            'openingAmount' => $this->openingAmount,
            'openedAt' => $this->openedAt,
            'openedById' => $this->openedById,
            'openedByName' => $this->whenLoaded('openedBy', fn () => $this->openedBy?->name),

            'closingExpected' => $this->closingExpected,
            'closingCounted' => $this->closingCounted,
            'difference' => $this->difference,
            'closedAt' => $this->closedAt,
            'closedById' => $this->closedById,
            'closedByName' => $this->whenLoaded('closedBy', fn () => $this->closedBy?->name),

            'notes' => $this->notes,
            'movements' => CashMovementResource::collection($this->whenLoaded('movements')),
        ];
    }
}
