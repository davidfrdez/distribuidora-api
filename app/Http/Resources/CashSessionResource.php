<?php

namespace App\Http\Resources;

use App\Models\CashSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashSession
 */
class CashSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'businessDate' => $this->businessDate->toDateString(),

            'baseAmount' => $this->baseAmount,
            'salesCash' => $this->salesCash,
            'salesBank' => $this->salesBank,
            'salesNequi' => $this->salesNequi,
            'reportedSalesTotal' => $this->reportedSalesTotal,

            'zNumber' => $this->zNumber,
            'zInvoiceCount' => $this->zInvoiceCount,

            'countedCashTotal' => $this->countedCashTotal,
            'expensesTotal' => $this->expensesTotal,
            'payablesTotal' => $this->payablesTotal,
            'expectedCash' => $this->expectedCash,
            'overShort' => $this->overShort,

            'status' => $this->status,
            'statusLabel' => $this->status->label(),
            'notes' => $this->notes,

            'openedByUserId' => $this->openedByUserId,
            'openedByName' => $this->whenLoaded('openedBy', fn () => $this->openedBy?->name),
            'closedByUserId' => $this->closedByUserId,
            'closedByName' => $this->whenLoaded('closedBy', fn () => $this->closedBy?->name),
            'closedAt' => $this->closedAt,

            'createdAt' => $this->createdAt,

            'denominations' => CashDenominationResource::collection($this->whenLoaded('denominations')),
            'expenses' => ExpenseResource::collection($this->whenLoaded('expenses')),
            'payables' => PayableResource::collection($this->whenLoaded('payables')),
        ];
    }
}
