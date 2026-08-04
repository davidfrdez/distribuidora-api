<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Usuario del sistema. Todos pertenecen al mismo negocio; lo único que los
 * diferencia es su `role`. No hay sedes, tenants ni impersonación.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $table = 'user';

    protected $fillable = [
        'name', 'email', 'password', 'role', 'phone', 'documentNumber',
        'active', 'lastLoginAt',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'active' => 'boolean',
        'role' => UserRole::class,
        // Red de seguridad: si algún punto nuevo olvida Hash::make, el cast
        // hashea automáticamente. Los valores ya hasheados no se re-hashean.
        'password' => 'hashed',
        'lastLoginAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];
}
