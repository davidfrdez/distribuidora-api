<?php

namespace App\Models;

use Database\Factories\TemperatureLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lectura de temperatura de una bodega. Es el registro que respalda la cadena
 * de frío y justifica una merma por ruptura.
 */
class TemperatureLog extends Model
{
    /** @use HasFactory<TemperatureLogFactory> */
    use HasFactory;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $table = 'temperature_log';

    protected $fillable = [
        'warehouseId', 'temperature', 'expectedMin', 'expectedMax',
        'outOfRange', 'source', 'notes', 'recordedById', 'recordedAt',
    ];

    protected $casts = [
        'temperature' => 'decimal:2',
        'expectedMin' => 'decimal:2',
        'expectedMax' => 'decimal:2',
        'outOfRange' => 'boolean',
        'recordedAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouseId');
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recordedById');
    }
}
