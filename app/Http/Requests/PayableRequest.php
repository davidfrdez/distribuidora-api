<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de una cuenta por pagar. Admite la foto de la factura como adjunto
 * (multipart). El archivo se guarda en disco privado desde el controlador.
 */
class PayableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->role->canManageFinances();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'supplierId' => ['required', 'integer', 'exists:supplier,id'],
            'concept' => ['required', 'string', 'max:200'],
            'invoiceNumber' => ['nullable', 'string', 'max:60'],
            'totalAmount' => ['required', 'numeric', 'min:0.01', 'max:99999999999'],
            'issueDate' => ['required', 'date'],
            'dueDate' => ['nullable', 'date', 'after_or_equal:issueDate'],
            'notes' => ['nullable', 'string', 'max:500'],
            // Foto de la factura: imagen o PDF, hasta 8 MB.
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ];
    }
}
