<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Recepción de mercancía. Las reglas de qué cantidad es obligatoria según el modo
 * de venta las aplica `InventoryService::normalizeReceivedQuantities()`; aquí sólo
 * se valida la forma de los datos y lo que se puede comprobar sin el producto.
 */
class ReceiveStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->role->canManageInventory();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'productId' => ['required', 'integer', 'exists:product,id'],
            'supplierId' => ['nullable', 'integer', 'exists:supplier,id'],

            'units' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'kg' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'totalCost' => ['required', 'numeric', 'min:0', 'max:99999999999'],

            'supplierLotCode' => ['nullable', 'string', 'max:60'],
            'purchaseInvoice' => ['nullable', 'string', 'max:60'],
            'receivedAt' => ['nullable', 'date'],
            'expirationDate' => ['nullable', 'date'],
            'manufacturingDate' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('units') && ! $this->filled('kg')) {
                $validator->errors()->add('units', 'Indica las unidades o los kilos recibidos.');
            }

            $manufacturing = $this->date('manufacturingDate');
            $expiration = $this->date('expirationDate');

            if ($manufacturing && $expiration && $expiration->lessThanOrEqualTo($manufacturing)) {
                $validator->errors()->add(
                    'expirationDate',
                    'El vencimiento debe ser posterior a la fecha de fabricación.',
                );
            }
        });
    }
}
