<?php

namespace App\Models;

use App\Enums\CashMovementType;
use App\Enums\MovementDirection;
use Database\Factories\CashMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ingreso o egreso de efectivo dentro de un turno de caja.
 */
class CashMovement extends Model
{
    /** @use HasFactory<CashMovementFactory> */
    use HasFactory;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $table = 'cash_movement';

    protected $fillable = [
        'cashSessionId', 'type', 'direction', 'amount', 'concept', 'createdById',
    ];

    protected $casts = [
        'type' => CashMovementType::class,
        'direction' => MovementDirection::class,
        'amount' => 'decimal:2',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    /** @return BelongsTo<CashSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cashSessionId');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createdById');
    }

    /** Monto con signo: negativo si es egreso. */
    public function signedAmount(): float
    {
        return (float) $this->amount * $this->direction->sign();
    }
}
