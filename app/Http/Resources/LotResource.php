<?php

namespace App\Http\Resources;

use App\Models\Lot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lot
 */
class LotResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'code' => $this->code,
            'supplierLotCode' => $this->supplierLotCode,
            'purchaseInvoice' => $this->purchaseInvoice,

            'productId' => $this->productId,
            'product' => new ProductResource($this->whenLoaded('product')),
            'supplierId' => $this->supplierId,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),

            'initialUnits' => $this->initialUnits,
            'currentUnits' => $this->currentUnits,
            'initialKg' => $this->initialKg,
            'currentKg' => $this->currentKg,

            // `receivedAt` es NOT NULL; las otras dos fechas sí pueden faltar.
            'receivedAt' => $this->receivedAt->toDateString(),
            'expirationDate' => $this->expirationDate?->toDateString(),
            'manufacturingDate' => $this->manufacturingDate?->toDateString(),
            'daysToExpiration' => $this->daysToExpiration(),
            'isExpired' => $this->isExpired(),

            'status' => $this->status,
            'statusLabel' => $this->status->label(),
            'labelPrinted' => $this->labelPrinted,
            'notes' => $this->notes,
            'receivedById' => $this->receivedById,
        ];

        // El costo del lote es información sensible: define el margen del negocio.
        if ($request->user()?->role->canSeeFinances()) {
            $data['costPerUnit'] = $this->costPerUnit;
            $data['costPerKg'] = $this->costPerKg;
            $data['totalCost'] = $this->totalCost;
        }

        return $data;
    }
}
