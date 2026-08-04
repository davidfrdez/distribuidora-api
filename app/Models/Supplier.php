<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory, SoftDeletes;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    const DELETED_AT = 'deletedAt';

    protected $table = 'supplier';

    protected $fillable = [
        'code', 'name', 'nit', 'contactName', 'phone', 'email',
        'address', 'city', 'invimaRegistration', 'paymentTermDays', 'notes', 'active',
    ];

    protected $casts = [
        'paymentTermDays' => 'integer',
        'active' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
    ];

    /** @return HasMany<Lot, $this> */
    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class, 'supplierId');
    }
}
