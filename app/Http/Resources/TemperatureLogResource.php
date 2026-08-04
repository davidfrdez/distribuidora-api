<?php

namespace App\Http\Resources;

use App\Models\TemperatureLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TemperatureLog
 */
class TemperatureLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouseId' => $this->warehouseId,
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'temperature' => $this->temperature,
            'expectedMin' => $this->expectedMin,
            'expectedMax' => $this->expectedMax,
            'outOfRange' => $this->outOfRange,
            'source' => $this->source,
            'notes' => $this->notes,
            'recordedById' => $this->recordedById,
            'recordedByName' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy?->name),
            'recordedAt' => $this->recordedAt,
        ];
    }
}
