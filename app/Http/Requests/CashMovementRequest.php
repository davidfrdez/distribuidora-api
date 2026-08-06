<?php

namespace App\Http\Requests;

use App\Enums\CashMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->role->canHandleCash();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CashMovementType::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999999'],
            'concept' => ['required', 'string', 'max:200'],
        ];
    }
}
