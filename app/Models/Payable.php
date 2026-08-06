<?php

namespace App\Models;

use App\Enums\PayableStatus;
use Database\Factories\PayableFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuenta por pagar a un proveedor. El saldo pendiente es derivado
 * (`totalAmount - paidAmount`); `PayableService` mantiene `paidAmount` y `status`.
 */
class Payable extends Model
{
    /** @use HasFactory<PayableFactory> */
    use HasFactory;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $table = 'payable';

    protected $fillable = [
        'supplierId', 'invoiceNumber', 'concept',
        'issueDate', 'dueDate', 'totalAmount', 'paidAmount',
        'status', 'attachmentPath', 'notes', 'createdById',
    ];

    protected $casts = [
        'issueDate' => 'date',
        'dueDate' => 'date',
        'totalAmount' => 'decimal:2',
        'paidAmount' => 'decimal:2',
        'status' => PayableStatus::class,
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplierId');
    }

    /** @return HasMany<PayablePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(PayablePayment::class, 'payableId');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createdById');
    }

    // ── Reglas de negocio ──────────────────────────────────────────────────────

    /** Saldo que falta pagar. */
    public function balance(): float
    {
        return round((float) $this->totalAmount - (float) $this->paidAmount, 2);
    }

    /** ¿Está vencida? Sólo aplica mientras siga debiendo. */
    public function isOverdue(): bool
    {
        return $this->status->isOpen()
            && $this->dueDate !== null
            && $this->dueDate->isPast();
    }

    /**
     * Cuentas que todavía deben plata (para "¿cuánto debo?").
     *
     * @param  Builder<Payable>  $query
     * @return Builder<Payable>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [PayableStatus::PENDING->value, PayableStatus::PARTIAL->value]);
    }
}
