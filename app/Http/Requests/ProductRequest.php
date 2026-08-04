<?php

namespace App\Http\Requests;

use App\Enums\SaleMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->role->canManageInventory();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $productId = $this->route('product')?->id;
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        // En creación es 'nullable' (ProductController autogenera si falta); en
        // edición es 'sometimes' para no pisar con null el sku existente cuando
        // el cliente no lo envía.
        $skuRule = $this->isMethod('POST') ? 'nullable' : 'sometimes';

        return [
            'sku' => [
                $skuRule, 'string', 'max:40',
                // Ignora los soft-deleted: el índice UNIQUE físico tampoco los ve
                // porque el registro sigue en la tabla, así que se compara contra todo.
                Rule::unique('product', 'sku')->ignore($productId),
            ],
            'name' => [$required, 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:500'],
            'brand' => ['nullable', 'string', 'max:100'],
            'imageUrl' => ['nullable', 'string', 'max:500'],
            'categoryId' => ['nullable', 'integer', 'exists:category,id'],

            'saleMode' => [$required, Rule::enum(SaleMode::class)],
            'netWeightKg' => ['nullable', 'numeric', 'min:0.0001', 'max:99999999'],
            'weightTolerancePercent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'basePrice' => [$required, 'numeric', 'min:0'],
            'priceIncludesTax' => ['boolean'],
            'taxPercent' => ['numeric', 'min:0', 'max:100'],

            'purchaseUnitId' => ['nullable', 'integer', 'exists:unit,id'],
            'saleUnitId' => ['nullable', 'integer', 'exists:unit,id'],

            'trackLots' => ['boolean'],
            'shelfLifeDays' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'expirationAlertDays' => ['integer', 'min:0', 'max:365'],
            'minStockKg' => ['numeric', 'min:0'],
            'maxStockKg' => ['numeric', 'min:0'],
            'minStockUnits' => ['numeric', 'min:0'],
            'maxStockUnits' => ['numeric', 'min:0'],
            'shrinkagePercentPerDay' => ['numeric', 'min:0', 'max:100'],
            'storageTempMin' => ['nullable', 'numeric', 'min:-50', 'max:50'],
            'storageTempMax' => ['nullable', 'numeric', 'min:-50', 'max:50'],

            'sellable' => ['boolean'],
            'purchasable' => ['boolean'],
            'temporarilyOut' => ['boolean'],
            'displayOrder' => ['integer', 'min:0'],
            'active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $saleMode = $this->enum('saleMode', SaleMode::class);

            if ($saleMode === null) {
                return;
            }

            // Un paquete de peso fijo sin peso no se puede inventariar: el kg del
            // kardex se deriva de netWeightKg × unidades.
            if ($saleMode->hasDerivedWeight() && ! $this->filled('netWeightKg')) {
                $validator->errors()->add(
                    'netWeightKg',
                    'Un paquete de peso fijo necesita el peso neto por unidad.',
                );
            }

            $min = $this->input('storageTempMin');
            $max = $this->input('storageTempMax');

            if ($min !== null && $max !== null && (float) $min > (float) $max) {
                $validator->errors()->add(
                    'storageTempMax',
                    'La temperatura máxima no puede ser menor que la mínima.',
                );
            }
        });
    }

    /**
     * `tracksWeight` es consecuencia del modo de venta, no una decisión
     * independiente: se deriva aquí para que no puedan quedar inconsistentes.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();

        if (isset($data['saleMode'])) {
            $saleMode = SaleMode::from($data['saleMode']);
            $data['tracksWeight'] = $saleMode->tracksWeight();

            if (! $saleMode->tracksWeight()) {
                $data['netWeightKg'] = null;
            }
        }

        return $data;
    }
}
