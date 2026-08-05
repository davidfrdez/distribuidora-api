<?php

namespace App\Models;

use App\Enums\SaleMode;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Producto del catálogo. Entidad única: lo que se compra y lo que se vende.
 * Ver `saleMode` para entender cómo se cobra y si el peso interviene.
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    const DELETED_AT = 'deletedAt';

    protected $table = 'product';

    protected $fillable = [
        'categoryId', 'sku', 'name', 'description', 'brand', 'imageUrl',
        'saleMode', 'tracksWeight', 'netWeightKg', 'weightTolerancePercent',
        'basePrice', 'priceIncludesTax', 'taxPercent',
        'averageCostPerKg', 'averageCostPerUnit', 'lastCostPerKg', 'lastCostPerUnit', 'costUpdatedAt',
        'purchaseUnitId', 'saleUnitId',
        'trackLots', 'shelfLifeDays', 'expirationAlertDays',
        'minStockKg', 'maxStockKg', 'minStockUnits', 'maxStockUnits',
        'shrinkagePercentPerDay',
        'sellable', 'purchasable', 'temporarilyOut', 'displayOrder', 'active',
    ];

    protected $casts = [
        'saleMode' => SaleMode::class,
        'tracksWeight' => 'boolean',
        'netWeightKg' => 'decimal:4',
        'weightTolerancePercent' => 'decimal:2',
        'basePrice' => 'decimal:2',
        'priceIncludesTax' => 'boolean',
        'taxPercent' => 'decimal:2',
        'averageCostPerKg' => 'decimal:4',
        'averageCostPerUnit' => 'decimal:4',
        'lastCostPerKg' => 'decimal:4',
        'lastCostPerUnit' => 'decimal:4',
        'costUpdatedAt' => 'datetime',
        'trackLots' => 'boolean',
        'shelfLifeDays' => 'integer',
        'expirationAlertDays' => 'integer',
        'minStockKg' => 'decimal:4',
        'maxStockKg' => 'decimal:4',
        'minStockUnits' => 'decimal:4',
        'maxStockUnits' => 'decimal:4',
        'shrinkagePercentPerDay' => 'decimal:4',
        'sellable' => 'boolean',
        'purchasable' => 'boolean',
        'temporarilyOut' => 'boolean',
        'displayOrder' => 'integer',
        'active' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
    ];

    // ── Relaciones ───────────────────────────────────────────────────────────

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'categoryId');
    }

    /** @return BelongsTo<Unit, $this> */
    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchaseUnitId');
    }

    /** @return BelongsTo<Unit, $this> */
    public function saleUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'saleUnitId');
    }

    /** @return HasMany<ProductBarcode, $this> */
    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class, 'productId');
    }

    /** @return HasMany<UnitConversion, $this> */
    public function unitConversions(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'productId');
    }

    // ── Reglas de negocio ────────────────────────────────────────────────────

    /** ¿El inventario de este producto lleva saldo en kg? */
    public function tracksWeight(): bool
    {
        return $this->saleMode->tracksWeight() && $this->tracksWeight;
    }

    /**
     * Tolerancia de peso efectiva: la del producto, o la del negocio si no tiene.
     * Se usa para validar la diferencia entre lo pedido y lo despachado.
     */
    public function effectiveWeightTolerancePercent(): float
    {
        if ($this->weightTolerancePercent !== null) {
            return (float) $this->weightTolerancePercent;
        }

        return (float) Company::current()->defaultWeightTolerancePercent;
    }

    /**
     * Peso en kg que corresponde a una cantidad de unidades.
     * Sólo tiene sentido cuando el peso se deriva (FIXED_PACK) o cuando se quiere
     * una ESTIMACIÓN para WEIGHT usando el peso promedio de pieza.
     */
    public function estimateKgForUnits(float $units): ?float
    {
        if ($this->netWeightKg === null) {
            return null;
        }

        return round($units * (float) $this->netWeightKg, 4);
    }
}
