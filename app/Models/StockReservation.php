<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Database\Factories\StockReservationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Apartado de stock con dueño y vencimiento. Sin esto dos pedidos confirmados
 * pueden prometer el mismo lote.
 */
class StockReservation extends Model
{
    /** @use HasFactory<StockReservationFactory> */
    use HasFactory;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $table = 'stock_reservation';

    protected $fillable = [
        'productId', 'warehouseId', 'lotId',
        'units', 'kg', 'status', 'referenceType', 'referenceId',
        'expiresAt', 'resolvedAt', 'createdById', 'notes',
    ];

    protected $casts = [
        'units' => 'decimal:4',
        'kg' => 'decimal:4',
        'status' => ReservationStatus::class,
        'expiresAt' => 'datetime',
        'resolvedAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'productId');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouseId');
    }

    /** @return BelongsTo<Lot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class, 'lotId');
    }

    /**
     * @param  Builder<StockReservation>  $query
     * @return Builder<StockReservation>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::ACTIVE->value);
    }

    /**
     * Reservas activas cuya fecha de expiración ya pasó. Las que no tienen
     * `expiresAt` no expiran nunca por sí solas.
     *
     * @param  Builder<StockReservation>  $query
     * @return Builder<StockReservation>
     */
    public function scopeExpirable(Builder $query): Builder
    {
        return $query->active()->whereNotNull('expiresAt')->where('expiresAt', '<=', now());
    }
}
