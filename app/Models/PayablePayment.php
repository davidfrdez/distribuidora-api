<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\PayablePaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un abono (parcial o total) contra una cuenta por pagar.
 */
class PayablePayment extends Model
{
    /** @use HasFactory<PayablePaymentFactory> */
    use HasFactory;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $table = 'payable_payment';

    protected $fillable = [
        'payableId', 'amount', 'paymentDate', 'paymentMethod',
        'reference', 'notes', 'createdById',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paymentDate' => 'date',
        'paymentMethod' => PaymentMethod::class,
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    /** @return BelongsTo<Payable, $this> */
    public function payable(): BelongsTo
    {
        return $this->belongsTo(Payable::class, 'payableId');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createdById');
    }
}
