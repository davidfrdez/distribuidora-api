<?php

namespace App\Models;

use Database\Factories\WarehouseTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseType extends Model
{
    /** @use HasFactory<WarehouseTypeFactory> */
    use HasFactory;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $table = 'warehouse_type';

    protected $fillable = [
        'code', 'name', 'defaultTempMin', 'defaultTempMax',
        'requiresColdChain', 'active',
    ];

    protected $casts = [
        'defaultTempMin' => 'decimal:2',
        'defaultTempMax' => 'decimal:2',
        'requiresColdChain' => 'boolean',
        'active' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    /** @return HasMany<Warehouse, $this> */
    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'warehouseTypeId');
    }
}
