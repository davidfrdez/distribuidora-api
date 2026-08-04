<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->role->canManageInventory();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $warehouseId = $this->route('warehouse')?->id;
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        // En creación es 'nullable' (WarehouseController autogenera si falta);
        // en edición es 'sometimes' para no pisar con null el code existente
        // cuando el cliente no lo envía.
        $codeRule = $this->isMethod('POST') ? 'nullable' : 'sometimes';

        return [
            'code' => [$codeRule, 'string', 'max:20', Rule::unique('warehouse', 'code')->ignore($warehouseId)],
            'name' => [$required, 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:300'],
            'warehouseTypeId' => ['nullable', 'integer', 'exists:warehouse_type,id'],
            'tempMin' => ['nullable', 'numeric', 'min:-50', 'max:50'],
            'tempMax' => ['nullable', 'numeric', 'min:-50', 'max:50'],
            'isDefault' => ['boolean'],
            'isQuarantine' => ['boolean'],
            'sellable' => ['boolean'],
            'active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $min = $this->input('tempMin');
            $max = $this->input('tempMax');

            if ($min !== null && $max !== null && (float) $min > (float) $max) {
                $validator->errors()->add('tempMax', 'La temperatura máxima no puede ser menor que la mínima.');
            }

            // Una bodega de cuarentena que además pudiera despachar anularía el
            // propósito de retener mercancía sospechosa.
            if ($this->boolean('isQuarantine') && $this->boolean('sellable')) {
                $validator->errors()->add(
                    'sellable',
                    'Una bodega de cuarentena no puede despachar: su stock está retenido.',
                );
            }
        });
    }
}
