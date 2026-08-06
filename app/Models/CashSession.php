<?php

namespace App\Models;

use App\Enums\CashSessionStatus;
use Database\Factories\CashSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Turno de caja. `closingExpected/Counted/difference` los llena `CashService`
 * al cerrar; mientras está abierta son null.
 */
class CashSession extends Model
{
    /** @use HasFactory<CashSessionFactory> */
    use HasFactory;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $table = 'cash_session';

    protected $fillable = [
        'openedById', 'openingAmount', 'openedAt',
        'closedById', 'closingExpected', 'closingCounted', 'difference', 'closedAt',
        'status', 'notes',
    ];

    protected $casts = [
        'openingAmount' => 'decimal:2',
        'openedAt' => 'datetime',
        'closingExpected' => 'decimal:2',
        'closingCounted' => 'decimal:2',
        'difference' => 'decimal:2',
        'closedAt' => 'datetime',
        'status' => CashSessionStatus::class,
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'openedById');
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closedById');
    }

    /** @return HasMany<CashMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'cashSessionId');
    }
}
