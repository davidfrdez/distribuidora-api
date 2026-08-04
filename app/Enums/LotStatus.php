<?php

namespace App\Enums;

/**
 * Estado de un lote. Sólo ACTIVE participa del FIFO.
 */
enum LotStatus: string
{
    case ACTIVE = 'ACTIVE';
    case DEPLETED = 'DEPLETED';       // agotado; se marca solo al llegar a cero
    case QUARANTINE = 'QUARANTINE';   // retenido: sospecha de calidad o cadena de frío rota
    case EXPIRED = 'EXPIRED';         // vencido; no se puede despachar
    case VOID = 'VOID';               // anulado por error de recepción

    /** ¿Puede salir mercancía de este lote? */
    public function isAvailable(): bool
    {
        return $this === self::ACTIVE;
    }

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Activo',
            self::DEPLETED => 'Agotado',
            self::QUARANTINE => 'En cuarentena',
            self::EXPIRED => 'Vencido',
            self::VOID => 'Anulado',
        };
    }
}
