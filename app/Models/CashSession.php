<?php

namespace App\Models;

use App\Enums\CashSessionStatus;
use Database\Factories\CashSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cierre de caja diario (arqueo), uno por `businessDate`. Replica la hoja de
 * cierre en papel: base, ventas por forma de pago, arqueo de efectivo por
 * denominación, egresos ("nómina y otros" vía `expense`) y cuentas por pagar
 * (`payable`) del día. Los totales y el descuadre (`overShort`) los calcula
 * `CashSessionService::recalculate()`; nunca se escriben a mano.
 */
class CashSession extends Model
{
    /** @use HasFactory<CashSessionFactory> */
    use HasFactory;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $table = 'cash_session';

    protected $fillable = [
        'businessDate', 'baseAmount',
        'salesCash', 'salesBank', 'salesNequi', 'reportedSalesTotal',
        'zNumber', 'zInvoiceCount',
        'countedCashTotal', 'expensesTotal', 'payablesTotal', 'expectedCash', 'overShort',
        'status', 'notes',
        'openedByUserId', 'closedByUserId', 'closedAt',
    ];

    protected $casts = [
        'businessDate' => 'date',
        'baseAmount' => 'decimal:2',
        'salesCash' => 'decimal:2',
        'salesBank' => 'decimal:2',
        'salesNequi' => 'decimal:2',
        'reportedSalesTotal' => 'decimal:2',
        'zInvoiceCount' => 'integer',
        'countedCashTotal' => 'decimal:2',
        'expensesTotal' => 'decimal:2',
        'payablesTotal' => 'decimal:2',
        'expectedCash' => 'decimal:2',
        'overShort' => 'decimal:2',
        'status' => CashSessionStatus::class,
        'closedAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    /** @return HasMany<CashDenomination, $this> */
    public function denominations(): HasMany
    {
        // Orden estable (de menor a mayor denominación) para que el arqueo se
        // muestre igual sin importar en qué orden se guardaron las filas.
        return $this->hasMany(CashDenomination::class, 'cashSessionId')->orderBy('denomination');
    }

    /**
     * Egresos ("nómina y otros") del día ligados a este cierre.
     *
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'cashSessionId');
    }

    /**
     * Cuentas por pagar registradas el día de este cierre (sólo informativo).
     *
     * @return HasMany<Payable, $this>
     */
    public function payables(): HasMany
    {
        return $this->hasMany(Payable::class, 'cashSessionId');
    }

    /** @return BelongsTo<User, $this> */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'openedByUserId');
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closedByUserId');
    }
}
