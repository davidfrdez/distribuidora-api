<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * Los campos de costo sólo se exponen a quien puede verlos: un empacador no
     * tiene por qué conocer el margen del negocio.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'brand' => $this->brand,
            'imageUrl' => $this->imageUrl,

            'saleMode' => $this->saleMode,
            'saleModeLabel' => $this->saleMode->label(),
            'driver' => $this->saleMode->driver(),
            'tracksWeight' => $this->tracksWeight(),
            'netWeightKg' => $this->netWeightKg,
            'weightTolerancePercent' => $this->weightTolerancePercent,
            'effectiveWeightTolerancePercent' => $this->effectiveWeightTolerancePercent(),

            'basePrice' => $this->basePrice,
            'priceIncludesTax' => $this->priceIncludesTax,
            'taxPercent' => $this->taxPercent,

            'trackLots' => $this->trackLots,
            'shelfLifeDays' => $this->shelfLifeDays,
            'expirationAlertDays' => $this->expirationAlertDays,
            'minStockKg' => $this->minStockKg,
            'maxStockKg' => $this->maxStockKg,
            'minStockUnits' => $this->minStockUnits,
            'maxStockUnits' => $this->maxStockUnits,
            'shrinkagePercentPerDay' => $this->shrinkagePercentPerDay,
            'storageTempMin' => $this->storageTempMin,
            'storageTempMax' => $this->storageTempMax,

            'sellable' => $this->sellable,
            'purchasable' => $this->purchasable,
            'temporarilyOut' => $this->temporarilyOut,
            'displayOrder' => $this->displayOrder,
            'active' => $this->active,

            'categoryId' => $this->categoryId,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'purchaseUnit' => new UnitResource($this->whenLoaded('purchaseUnit')),
            'saleUnit' => new UnitResource($this->whenLoaded('saleUnit')),
            'barcodes' => ProductBarcodeResource::collection($this->whenLoaded('barcodes')),
        ];

        if ($request->user()?->role->canSeeFinances()) {
            $data['averageCostPerKg'] = $this->averageCostPerKg;
            $data['averageCostPerUnit'] = $this->averageCostPerUnit;
            $data['lastCostPerKg'] = $this->lastCostPerKg;
            $data['lastCostPerUnit'] = $this->lastCostPerUnit;
            $data['costUpdatedAt'] = $this->costUpdatedAt;
        }

        return $data;
    }
}
