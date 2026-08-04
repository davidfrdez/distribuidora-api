<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Datos del negocio y parámetros de operación. FILA ÚNICA.
 *
 * Este sistema es a la medida de una sola distribuidora: no hay tenants, sedes ni
 * aislamiento de datos. Se accede con `Company::current()`, que memoriza la fila
 * durante la petición para no consultarla en cada uso.
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $table = 'company';

    protected $fillable = [
        'name', 'businessName', 'nit', 'address', 'city', 'phone', 'whatsappPhone',
        'email', 'invimaRegistration', 'timezone', 'currency',
        'logoPath', 'brandColor', 'tagline',
        'minOrderAmount', 'defaultWeightTolerancePercent', 'reservationTtlMinutes',
    ];

    protected $casts = [
        'minOrderAmount' => 'decimal:2',
        'defaultWeightTolerancePercent' => 'decimal:2',
        'reservationTtlMinutes' => 'integer',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    protected $appends = ['logoUrl'];

    /** Caché por petición: la fila no cambia en medio de un request. */
    private static ?self $current = null;

    /**
     * La única fila de `company`. Si no existe todavía devuelve una instancia sin
     * persistir con los valores por defecto, para que consultar un parámetro nunca
     * reviente en una instalación recién migrada.
     */
    public static function current(): self
    {
        return self::$current ??= self::query()->first() ?? new self([
            'name' => 'El Dorado Distribuidora',
            'minOrderAmount' => 0,
            'defaultWeightTolerancePercent' => 10,
            'reservationTtlMinutes' => 240,
        ]);
    }

    /** Invalida la caché. Necesario tras editar los datos y entre tests. */
    public static function forgetCurrent(): void
    {
        self::$current = null;
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetCurrent());
        static::deleted(fn () => self::forgetCurrent());
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logoPath ? Storage::disk('public')->url($this->logoPath) : null;
    }
}
