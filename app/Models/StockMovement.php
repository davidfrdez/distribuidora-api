<?php

namespace App\Models;

use App\Enums\MovementDirection;
use App\Enums\MovementType;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asiento del libro mayor de inventario. INMUTABLE.
 *
 * El modelo bloquea `update` y `delete` en sus eventos: si hace falta corregir,
 * se registra un movimiento en sentido contrario. Así el kardex siempre se puede
 * reconstruir sumando filas, y nadie puede reescribir la historia.
 */
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $table = 'stock_movement';

    protected $fillable = [
        'productId', 'lotId',
        'type', 'direction', 'units', 'kg',
        'costPerUnit', 'costPerKg', 'totalCost',
        'unitsBefore', 'unitsAfter', 'kgBefore', 'kgAfter',
        'referenceType', 'referenceId', 'userId', 'notes', 'movementDate',
    ];

    protected $casts = [
        'type' => MovementType::class,
        'direction' => MovementDirection::class,
        'units' => 'decimal:4',
        'kg' => 'decimal:4',
        'costPerUnit' => 'decimal:4',
        'costPerKg' => 'decimal:4',
        'totalCost' => 'decimal:2',
        'unitsBefore' => 'decimal:4',
        'unitsAfter' => 'decimal:4',
        'kgBefore' => 'decimal:4',
        'kgAfter' => 'decimal:4',
        'movementDate' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException(
                'stock_movement es inmutable: registra un movimiento contrario en lugar de editar.',
            );
        });

        static::deleting(function (): never {
            throw new \LogicException(
                'stock_movement es inmutable: registra un movimiento contrario en lugar de borrar.',
            );
        });
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'productId');
    }

    /** @return BelongsTo<Lot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class, 'lotId');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    /** Unidades con signo: negativas si es salida. */
    public function signedUnits(): float
    {
        return (float) $this->units * $this->direction->sign();
    }

    /** Kilos con signo: negativos si es salida. */
    public function signedKg(): float
    {
        return (float) $this->kg * $this->direction->sign();
    }
}
