<?php

namespace App\Enums;

/**
 * Roles del sistema. El valor string coincide con la columna `user.role`.
 *
 * Son los puestos reales de una distribuidora de salsamentaria. El sistema
 * atiende a un solo negocio (no hay multi-tenancy, ni sedes, ni impersonación),
 * pero por encima de `ADMINISTRADOR` existe `SUPERADMIN`: la cuenta del
 * proveedor/soporte del software, que puede gestionar incluso a los
 * administradores. No confundir con un rol de plataforma multi-tenant: sigue
 * siendo un único negocio, sólo que con un nivel de soporte por encima del dueño.
 *
 * Al añadir un rol: primero aquí (el cast de User depende de este enum), después
 * los helpers de capacidad que apliquen.
 */
enum UserRole: string
{
    /** Cuenta de soporte/proveedor del software. Puede gestionar cualquier usuario, incluidos administradores. */
    case SUPERADMIN = 'SUPERADMIN';

    /** Dueño o gerente. Acceso total, incluidos costos, márgenes y cartera. */
    case ADMINISTRADOR = 'ADMINISTRADOR';

    /** Toma pedidos (mostrador, teléfono, WhatsApp) y gestiona clientes. */
    case VENDEDOR = 'VENDEDOR';

    /** Opera la caja: cobros, arqueo, cierre de turno. */
    case CAJERO = 'CAJERO';

    /** Recibe mercancía, mueve bodegas, hace conteos y registra mermas. */
    case ALMACENISTA = 'ALMACENISTA';

    /** Alista y empaca pedidos: pesa producto y arma canastillas. */
    case EMPACADOR = 'EMPACADOR';

    /** Opera órdenes de maquila: despiece, porcionado, empacado al vacío. */
    case MAQUILADOR = 'MAQUILADOR';

    /** Transporta y entrega los despachos. */
    case DOMICILIARIO = 'DOMICILIARIO';

    /** Roles autorizados a gestionar usuarios. */
    public function canManageUsers(): bool
    {
        return in_array($this, [self::SUPERADMIN, self::ADMINISTRADOR], true);
    }

    /** Roles autorizados a editar los datos y parámetros del negocio. */
    public function canManageCompany(): bool
    {
        return in_array($this, [self::SUPERADMIN, self::ADMINISTRADOR], true);
    }

    /** Roles autorizados a operar inventario: compras, recepción, traslados, conteos. */
    public function canManageInventory(): bool
    {
        return in_array($this, [self::SUPERADMIN, self::ADMINISTRADOR, self::ALMACENISTA], true);
    }

    /** Roles que pueden alistar y empacar pedidos (capturan el peso real). */
    public function canPickAndPack(): bool
    {
        return in_array($this, [self::SUPERADMIN, self::ADMINISTRADOR, self::ALMACENISTA, self::EMPACADOR], true);
    }

    /** Roles que pueden ejecutar órdenes de maquila / despiece. */
    public function canProcess(): bool
    {
        return in_array($this, [self::SUPERADMIN, self::ADMINISTRADOR, self::MAQUILADOR], true);
    }

    /** Roles que registran mermas. Autorizarlas es otro permiso. */
    public function canReportWaste(): bool
    {
        return in_array($this, [
            self::SUPERADMIN,
            self::ADMINISTRADOR,
            self::ALMACENISTA,
            self::EMPACADOR,
            self::MAQUILADOR,
        ], true);
    }

    /** Roles que autorizan descuentos manuales, anulaciones, mermas y sobrecupo. */
    public function canAuthorizeOverrides(): bool
    {
        return in_array($this, [self::SUPERADMIN, self::ADMINISTRADOR], true);
    }

    /** Roles que pueden abrir y operar una caja. */
    public function canHandleCash(): bool
    {
        return in_array($this, [self::SUPERADMIN, self::ADMINISTRADOR, self::CAJERO], true);
    }

    /** Roles que pueden crear y editar pedidos. */
    public function canTakeOrders(): bool
    {
        return in_array($this, [self::SUPERADMIN, self::ADMINISTRADOR, self::VENDEDOR, self::CAJERO], true);
    }

    /** Roles autorizados a ver costos, márgenes y cartera. */
    public function canSeeFinances(): bool
    {
        return in_array($this, [self::SUPERADMIN, self::ADMINISTRADOR], true);
    }

    /** Roles autorizados a gestionar gastos y cuentas por pagar del negocio. */
    public function canManageFinances(): bool
    {
        return in_array($this, [self::SUPERADMIN, self::ADMINISTRADOR], true);
    }

    /** El rol de soporte/proveedor, por encima incluso del administrador. */
    public function isSuperAdmin(): bool
    {
        return $this === self::SUPERADMIN;
    }

    public function label(): string
    {
        return match ($this) {
            self::SUPERADMIN => 'Superadministrador',
            self::ADMINISTRADOR => 'Administrador',
            self::VENDEDOR => 'Vendedor',
            self::CAJERO => 'Cajero',
            self::ALMACENISTA => 'Almacenista',
            self::EMPACADOR => 'Empacador',
            self::MAQUILADOR => 'Maquilador',
            self::DOMICILIARIO => 'Domiciliario',
        };
    }
}
