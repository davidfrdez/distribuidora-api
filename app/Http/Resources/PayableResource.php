<?php

namespace App\Http\Resources;

use App\Models\Payable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payable
 */
class PayableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplierId' => $this->supplierId,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'invoiceNumber' => $this->invoiceNumber,
            'concept' => $this->concept,

            'issueDate' => $this->issueDate->toDateString(),
            'dueDate' => $this->dueDate?->toDateString(),

            'totalAmount' => $this->totalAmount,
            'paidAmount' => $this->paidAmount,
            'balance' => $this->balance(),

            'status' => $this->status,
            'statusLabel' => $this->status->label(),
            'isOverdue' => $this->isOverdue(),

            'hasAttachment' => $this->attachmentPath !== null,
            'notes' => $this->notes,
            'createdAt' => $this->createdAt,

            'payments' => PayablePaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
