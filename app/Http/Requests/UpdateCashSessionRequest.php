<?php

namespace App\Http\Requests;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guarda el borrador de un cierre de caja diario
 * (`PUT /api/admin/cash-sessions/{cashSession}`): escalares del día + arqueo
 * por denominación + gastos ("nómina y otros") + cuentas por pagar del día.
 * Todos los bloques son opcionales: el cajero puede ir guardando por partes.
 */
class UpdateCashSessionRequest extends FormRequest
{
    private const DENOMINATIONS = [50, 100, 200, 500, 1000, 2000, 5000, 10000, 20000, 50000, 100000];

    public function authorize(): bool
    {
        return (bool) $this->user()?->role->canManageCash();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'baseAmount' => ['sometimes', 'numeric', 'min:0', 'max:99999999999'],
            'salesCash' => ['sometimes', 'numeric', 'min:0', 'max:99999999999'],
            'salesBank' => ['sometimes', 'numeric', 'min:0', 'max:99999999999'],
            'salesNequi' => ['sometimes', 'numeric', 'min:0', 'max:99999999999'],
            'reportedSalesTotal' => ['sometimes', 'numeric', 'min:0', 'max:99999999999'],
            'zNumber' => ['nullable', 'string', 'max:30'],
            'zInvoiceCount' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'denominations' => ['sometimes', 'array'],
            'denominations.*.denomination' => ['required_with:denominations', 'integer', Rule::in(self::DENOMINATIONS)],
            'denominations.*.quantity' => ['required_with:denominations', 'integer', 'min:0'],

            'expenses' => ['sometimes', 'array'],
            'expenses.*.id' => ['nullable', 'integer', 'exists:expense,id'],
            'expenses.*.description' => ['required_with:expenses', 'string', 'max:200'],
            'expenses.*.amount' => ['required_with:expenses', 'numeric', 'min:0'],
            'expenses.*.category' => ['nullable', Rule::enum(ExpenseCategory::class)],
            'expenses.*.paymentMethod' => ['nullable', Rule::enum(PaymentMethod::class)],

            'payables' => ['sometimes', 'array'],
            'payables.*.id' => ['nullable', 'integer', 'exists:payable,id'],
            'payables.*.supplierId' => ['required_with:payables', 'integer', 'exists:supplier,id'],
            'payables.*.concept' => ['required_with:payables', 'string', 'max:200'],
            'payables.*.invoiceNumber' => ['nullable', 'string', 'max:60'],
            'payables.*.totalAmount' => ['required_with:payables', 'numeric', 'min:0'],
            'payables.*.dueDate' => ['nullable', 'date'],
        ];
    }
}
