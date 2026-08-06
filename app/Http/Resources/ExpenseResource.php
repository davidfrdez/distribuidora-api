<?php

namespace App\Http\Resources;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'categoryLabel' => $this->category->label(),
            'description' => $this->description,
            'amount' => $this->amount,
            'expenseDate' => $this->expenseDate->toDateString(),
            'paymentMethod' => $this->paymentMethod,
            'paymentMethodLabel' => $this->paymentMethod->label(),
            'supplierId' => $this->supplierId,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'hasAttachment' => $this->attachmentPath !== null,
            'notes' => $this->notes,
            'createdAt' => $this->createdAt,
        ];
    }
}
