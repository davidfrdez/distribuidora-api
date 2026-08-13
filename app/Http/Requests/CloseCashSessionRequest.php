<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cierre de un cierre de caja diario (`POST cash-sessions/{cashSession}/close`).
 * No recibe body: el arqueo, ventas y egresos ya se guardaron por `PUT`
 * (borrador); cerrar sólo recalcula y bloquea la edición.
 */
class CloseCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->role->canManageCash();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
