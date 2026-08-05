<?php

namespace App\Models;

use App\Enums\LotStatus;
use Database\Factories\LotFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lote de producto: la unidad mínima de trazabilidad.
 * Lleva doble saldo (unidades y kg) porque en peso variable ambos se consumen
 * a ritmos distintos.
 */
class Lot extends Model
{
    /** @use HasFactory<LotFactory> */
    use HasFactory, SoftDeletes;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    const DELETED_AT = 'deletedAt';

    protected $table = 'lot';

    protected $fillable = [
        'productId', 'supplierId',
        'code', 'supplierLotCode', 'purchaseInvoice',
        'initialUnits', 'currentUnits', 'initialKg', 'currentKg',
        'costPerUnit', 'costPerKg', 'totalCost',
        'receivedAt', 'expirationDate', 'manufacturingDate',
        'status', 'qrCode', 'labelPrinted', 'labelPrintedAt', 'notes', 'receivedById',
    ];

    protected $casts = [
        'initialUnits' => 'decimal:4',
        'currentUnits' => 'decimal:4',
        'initialKg' => 'decimal:4',
        'currentKg' => 'decimal:4',
        'costPerUnit' => 'decimal:4',
        'costPerKg' => 'decimal:4',
        'totalCost' => 'decimal:2',
        'receivedAt' => 'date',
        'expirationDate' => 'date',
        'manufacturingDate' => 'date',
        'status' => LotStatus::class,
        'labelPrinted' => 'boolean',
        'labelPrintedAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
    ];

    // ── Relaciones ───────────────────────────────────────────────────────────

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'productId');
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplierId');
    }

    /** @return BelongsTo<User, $this> */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receivedById');
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'lotId');
    }

    // ── Consultas ────────────────────────────────────────────────────────────

    /**
     * Lotes que pueden despachar, en orden FIFO por vencimiento.
     * Los lotes sin fecha de vencimiento van al final: se prefiere sacar
     * primero lo que caduca.
     *
     * @param  Builder<Lot>  $query
     * @return Builder<Lot>
     */
    public function scopeFifo(Builder $query): Builder
    {
        return $query
            ->where('status', LotStatus::ACTIVE->value)
            ->orderByRaw('expirationDate IS NULL ASC')
            ->orderBy('expirationDate')
            ->orderBy('receivedAt')
            ->orderBy('id');
    }

    /**
     * @param  Builder<Lot>  $query
     * @return Builder<Lot>
     */
    public function scopeWithStock(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('currentUnits', '>', 0)->orWhere('currentKg', '>', 0));
    }

    // ── Reglas de negocio ────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expirationDate !== null && $this->expirationDate->isPast();
    }

    /** Días hasta el vencimiento. Negativo si ya venció, null si no caduca. */
    public function daysToExpiration(): ?int
    {
        if ($this->expirationDate === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->expirationDate->startOfDay(), false);
    }

    /**
     * ¿Está dentro de la ventana de alerta de vencimiento?
     *
     * @param  int|null  $alertDays  Umbral explícito; si se omite se toma el del
     *                               producto, lo que obliga a cargar la relación.
     */
    public function isNearExpiration(?int $alertDays = null): bool
    {
        $days = $this->daysToExpiration();

        if ($days === null) {
            return false;
        }

        $threshold = $alertDays ?? (int) $this->product->expirationAlertDays;

        return $days >= 0 && $days <= $threshold;
    }

    public function isDepleted(): bool
    {
        return (float) $this->currentUnits <= 0 && (float) $this->currentKg <= 0;
    }
}
