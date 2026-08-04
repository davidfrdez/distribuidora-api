<?php

namespace App\Models;

use Database\Factories\StockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saldo por (producto, bodega). Caché de consulta: la verdad está en el kardex.
 *
 * `availableUnits` y `availableKg` son columnas GENERADAS por la base de datos
 * y por eso NO son asignables: escribirlas provocaría un error de SQL.
 */
class Stock extends Model
{
    /** @use HasFactory<StockFactory> */
    use HasFactory;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $table = 'stock';

    protected $fillable = [
        'productId', 'warehouseId',
        'currentUnits', 'reservedUnits', 'currentKg', 'reservedKg',
        'lastMovementAt', 'lastCountAt',
    ];

    // `availableUnits` y `availableKg` quedan deliberadamente FUERA de $fillable:
    // las calcula la base de datos y asignarlas rompería el INSERT.

    protected $casts = [
        'currentUnits' => 'decimal:4',
        'reservedUnits' => 'decimal:4',
        'availableUnits' => 'decimal:4',
        'currentKg' => 'decimal:4',
        'reservedKg' => 'decimal:4',
        'availableKg' => 'decimal:4',
        'lastMovementAt' => 'datetime',
        'lastCountAt' => 'datetime',
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
}
