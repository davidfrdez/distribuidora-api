<?php

namespace App\Enums;

/**
 * Estado de una cuenta por pagar. `OVERDUE` (vencida) NO es un estado: se deriva
 * de `dueDate` contra hoy mientras la cuenta siga abierta, para no tener que
 * correr un job que cambie filas todas las noches.
 */
enum PayableStatus: string
{
    case PENDING = 'PENDING';   // sin ningún pago
    case PARTIAL = 'PARTIAL';   // pagada en parte
    case PAID = 'PAID';         // saldada
    case VOID = 'VOID';         // anulada (factura errada, devolución total)

    /** ¿La cuenta sigue debiendo plata? */
    public function isOpen(): bool
    {
        return $this === self::PENDING || $this === self::PARTIAL;
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::PARTIAL => 'Parcial',
            self::PAID => 'Pagada',
            self::VOID => 'Anulada',
        };
    }
}
