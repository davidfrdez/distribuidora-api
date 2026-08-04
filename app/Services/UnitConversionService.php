<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Unit;
use App\Models\UnitConversion;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Convierte cantidades entre unidades de medida.
 *
 * Dos caminos, en este orden:
 *
 *  1. **Mismo `kind`** (peso↔peso, conteo↔conteo): regla de tres con
 *     `unit.factorToBase`. No requiere configuración: 1 arroba = 12,5 kg siempre.
 *
 *  2. **`kind` distinto** (conteo↔peso): requiere una fila en `unit_conversion`,
 *     porque la equivalencia depende del producto — una canastilla de chorizo
 *     santarrosano no pesa lo mismo que una de morcilla. Se busca primero la
 *     conversión específica del producto, después la genérica global, y en
 *     ambos casos también en sentido inverso (usando 1/factor).
 *
 * Si no hay forma de convertir, falla en vez de adivinar: un factor inventado
 * descuadra el inventario en silencio.
 */
class UnitConversionService
{
    /** Cantidad `$quantity` expresada en `$from`, convertida a `$to`. */
    public function convert(float $quantity, Unit $from, Unit $to, ?Product $product = null): float
    {
        if ($from->id === $to->id) {
            return $quantity;
        }

        if ($from->kind === $to->kind) {
            $toFactor = (float) $to->factorToBase;

            if ($toFactor === 0.0) {
                throw new HttpException(
                    500,
                    "La unidad {$to->code} tiene factorToBase en cero: no se puede convertir.",
                );
            }

            return $quantity * ((float) $from->factorToBase / $toFactor);
        }

        $factor = $this->findCrossKindFactor($from, $to, $product);

        if ($factor === null) {
            $scope = $product ? "el producto {$product->sku}" : 'uso general';

            throw new HttpException(
                422,
                "No hay conversión definida de {$from->code} a {$to->code} para {$scope}. " .
                'Configúrala en las equivalencias de unidades.',
            );
        }

        return $quantity * $factor;
    }

    /**
     * Convierte a la unidad base del `kind` de origen (kg, unidad o litro).
     * Es la forma en que el inventario normaliza todo antes de guardar.
     */
    public function toBase(float $quantity, Unit $from): float
    {
        return $quantity * (float) $from->factorToBase;
    }

    /**
     * Factor para pasar de `$from` a `$to` cuando son de distinta naturaleza.
     * Devuelve null si no hay ninguna conversión aplicable.
     */
    private function findCrossKindFactor(Unit $from, Unit $to, ?Product $product): ?float
    {
        // Específica del producto primero, genérica global después.
        $productIds = $product ? [$product->id, null] : [null];

        foreach ($productIds as $productId) {
            if ($direct = $this->lookup($productId, $from->id, $to->id)) {
                return (float) $direct->factor;
            }

            if ($inverse = $this->lookup($productId, $to->id, $from->id)) {
                $factor = (float) $inverse->factor;

                if ($factor !== 0.0) {
                    return 1 / $factor;
                }
            }
        }

        return null;
    }

    private function lookup(?int $productId, int $fromUnitId, int $toUnitId): ?UnitConversion
    {
        return UnitConversion::query()
            ->when(
                $productId === null,
                fn ($query) => $query->whereNull('productId'),
                fn ($query) => $query->where('productId', $productId),
            )
            ->where('fromUnitId', $fromUnitId)
            ->where('toUnitId', $toUnitId)
            ->first();
    }
}
