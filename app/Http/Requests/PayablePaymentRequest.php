<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pago (abono) contra una cuenta por pagar. Que el monto no exceda el saldo lo
 * valida `PayableService` (necesita el bloqueo de la fila), no aquí.
 */
class PayablePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->role->canManageFinances();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999999'],
            'paymentMethod' => ['required', Rule::enum(PaymentMethod::class)],
            'paymentDate' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:300'],
        ];
    }
}
