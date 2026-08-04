<?php

namespace App\Models;

use App\Enums\UnitKind;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Unidad de medida. `factorToBase` la expresa en la unidad base de su `kind`
 * (kg / unidad / litro), lo que permite convertir sin tablas auxiliares.
 */
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $table = 'unit';

    protected $fillable = [
        'code', 'name', 'kind', 'factorToBase', 'isBase', 'decimals', 'active',
    ];

    protected $casts = [
        'kind' => UnitKind::class,
        'factorToBase' => 'decimal:10',
        'isBase' => 'boolean',
        'decimals' => 'integer',
        'active' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function isWeight(): bool
    {
        return $this->kind === UnitKind::WEIGHT;
    }

    public function isCount(): bool
    {
        return $this->kind === UnitKind::COUNT;
    }
}
