<?php

namespace App\Http\Resources;

use App\Models\ProductBarcode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductBarcode
 */
class ProductBarcodeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'productId' => $this->productId,
            'barcode' => $this->barcode,
            'label' => $this->label,
            'isWeightEmbedded' => $this->isWeightEmbedded,
            'isPrimary' => $this->isPrimary,
        ];
    }
}
