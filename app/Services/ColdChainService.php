<?php

namespace App\Services;

use App\Models\TemperatureLog;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Bitácora de cadena de frío.
 *
 * Registrar una lectura fuera de rango NO genera merma automáticamente: cuánto
 * producto se perdió es un juicio humano que depende de cuánto duró la
 * desviación y de qué había en la bodega. Lo que sí hace el servicio es dejar
 * la desviación registrada y visible para que el almacenista decida.
 */
class ColdChainService
{
    /** Registra una lectura y determina si está fuera del rango vigente. */
    public function record(
        Warehouse $warehouse,
        float $temperature,
        ?int $recordedById = null,
        string $source = 'MANUAL',
        ?string $notes = null,
        ?Carbon $recordedAt = null,
    ): TemperatureLog {
        [$min, $max] = $warehouse->effectiveTempRange();

        $outOfRange = ($min !== null && $temperature < $min)
            || ($max !== null && $temperature > $max);

        $entry = TemperatureLog::create([
            'warehouseId' => $warehouse->id,
            'temperature' => $temperature,
            'expectedMin' => $min,
            'expectedMax' => $max,
            'outOfRange' => $outOfRange,
            'source' => $source,
            'notes' => $notes,
            'recordedById' => $recordedById,
            'recordedAt' => $recordedAt ?? now(),
        ]);

        if ($outOfRange) {
            Log::warning('Cadena de frío fuera de rango.', [
                'warehouseId' => $warehouse->id,
                'warehouse' => $warehouse->name,
                'temperature' => $temperature,
                'expectedMin' => $min,
                'expectedMax' => $max,
            ]);
        }

        return $entry;
    }

    /**
     * Desviaciones de una bodega en una ventana de tiempo, para revisar
     * si hubo ruptura sostenida y decidir la merma.
     *
     * @return Collection<int, TemperatureLog>
     */
    public function deviations(Warehouse $warehouse, Carbon $since, ?Carbon $until = null): Collection
    {
        return TemperatureLog::query()
            ->where('warehouseId', $warehouse->id)
            ->where('outOfRange', true)
            ->where('recordedAt', '>=', $since)
            ->when($until, fn ($query) => $query->where('recordedAt', '<=', $until))
            ->orderBy('recordedAt')
            ->get();
    }

    /**
     * ¿Hubo una ruptura sostenida? Es decir: lecturas fuera de rango que cubren
     * al menos `$minMinutes` entre la primera y la última, sin ninguna lectura
     * normal de por medio.
     *
     * Una lectura aislada fuera de rango suele ser la puerta abierta al recibir
     * mercancía; lo que arruina el producto es la desviación que se mantiene.
     */
    public function hasSustainedBreach(Warehouse $warehouse, Carbon $since, int $minMinutes = 60): bool
    {
        $readings = TemperatureLog::query()
            ->where('warehouseId', $warehouse->id)
            ->where('recordedAt', '>=', $since)
            ->orderBy('recordedAt')
            ->get(['outOfRange', 'recordedAt']);

        $streakStart = null;

        foreach ($readings as $reading) {
            if (! $reading->outOfRange) {
                $streakStart = null;

                continue;
            }

            $streakStart ??= $reading->recordedAt;

            if ($streakStart->diffInMinutes($reading->recordedAt) >= $minMinutes) {
                return true;
            }
        }

        return false;
    }
}
