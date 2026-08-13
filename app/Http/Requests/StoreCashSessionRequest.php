<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Abre (o recupera) el cierre de caja diario de una fecha
 * (`POST /api/admin/cash-sessions`).
 */
class StoreCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->role->canManageCash();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'businessDate' => ['required', 'date'],
            'baseAmount' => ['required', 'numeric', 'min:0', 'max:99999999999'],
        ];
    }
}
