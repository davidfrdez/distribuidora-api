<?php

namespace App\Models;

use Database\Factories\CashDenominationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila del arqueo de efectivo por denominación (billete/moneda + cantidad
 * contada) de un cierre de caja diario. El valor (`denomination × quantity`)
 * no se guarda: se calcula.
 */
class CashDenomination extends Model
{
    /** @use HasFactory<CashDenominationFactory> */
    use HasFactory;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $table = 'cash_denomination';

    protected $fillable = ['cashSessionId', 'denomination', 'quantity'];

    protected $casts = [
        'denomination' => 'integer',
        'quantity' => 'integer',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    /** @return BelongsTo<CashSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cashSessionId');
    }

    /** Valor de la fila: denominación × cantidad contada. */
    public function value(): float
    {
        return (float) $this->denomination * (float) $this->quantity;
    }
}
