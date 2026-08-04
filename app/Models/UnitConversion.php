<?php

namespace App\Models;

use Database\Factories\UnitConversionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Equivalencia entre dos unidades que depende del producto:
 * 1 canastilla de chorizo santarrosano = 12,5 kg.
 */
class UnitConversion extends Model
{
    /** @use HasFactory<UnitConversionFactory> */
    use HasFactory;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $table = 'unit_conversion';

    protected $fillable = [
        'productId', 'fromUnitId', 'toUnitId', 'factor',
    ];

    protected $casts = [
        'factor' => 'decimal:10',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'productId');
    }

    /** @return BelongsTo<Unit, $this> */
    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'fromUnitId');
    }

    /** @return BelongsTo<Unit, $this> */
    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'toUnitId');
    }
}
