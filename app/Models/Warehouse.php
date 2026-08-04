<?php

namespace App\Models;

use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $table = 'warehouse';

    protected $fillable = [
        'warehouseTypeId', 'code', 'name', 'description',
        'tempMin', 'tempMax', 'isDefault', 'isQuarantine', 'sellable', 'active',
    ];

    protected $casts = [
        'tempMin' => 'decimal:2',
        'tempMax' => 'decimal:2',
        'isDefault' => 'boolean',
        'isQuarantine' => 'boolean',
        'sellable' => 'boolean',
        'active' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    /** @return BelongsTo<WarehouseType, $this> */
    public function warehouseType(): BelongsTo
    {
        return $this->belongsTo(WarehouseType::class, 'warehouseTypeId');
    }

    /** @return HasMany<TemperatureLog, $this> */
    public function temperatureLogs(): HasMany
    {
        return $this->hasMany(TemperatureLog::class, 'warehouseId');
    }

    /**
     * Rango de temperatura efectivo: el propio de la bodega, o el del tipo.
     * Devuelve [min, max]; cualquiera puede ser null si no está configurado.
     *
     * @return array{0: float|null, 1: float|null}
     */
    public function effectiveTempRange(): array
    {
        $min = $this->tempMin ?? $this->warehouseType?->defaultTempMin;
        $max = $this->tempMax ?? $this->warehouseType?->defaultTempMax;

        return [
            $min === null ? null : (float) $min,
            $max === null ? null : (float) $max,
        ];
    }

    /** ¿De esta bodega se puede despachar mercancía a un cliente? */
    public function canDispatch(): bool
    {
        return $this->active && $this->sellable && ! $this->isQuarantine;
    }
}
