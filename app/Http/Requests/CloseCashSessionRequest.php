<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->role->canHandleCash();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'countedAmount' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
