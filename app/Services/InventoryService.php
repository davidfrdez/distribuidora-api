<?php

namespace App\Services;

use App\Enums\LotStatus;
use App\Enums\MovementDirection;
use App\Enums\MovementType;
use App\Enums\QuantityDriver;
use App\Enums\ReservationStatus;
use App\Enums\SaleMode;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\Supplier;
use App\Support\ConsumptionLine;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Motor de inventario con DOBLE SALDO (unidades + kg) y FIFO por vencimiento.
 *
 * Reglas que este servicio garantiza y que no se deben relajar:
 *
 *  1. **Todo movimiento tiene lote.** Si no hay lote que respalde una salida,
 *     la operación falla. DaliOrder lo dejaba pasar con un `Log::warning` y el
 *     inventario quedaba descuadrado sin que nadie se enterara; en cárnicos eso
 *     además destruye la trazabilidad exigida para un retiro de producto.
 *
 *  2. **Las tres fuentes cuadran siempre.** `stock`, la suma de `lot` y la suma
 *     de `stock_movement` se actualizan en la misma transacción con los MISMOS
 *     números ya redondeados. No se recalculan por separado.
 *
 *  3. **El costo de venta sale del lote (FIFO real).** `product.averageCost*`
 *     existe sólo como referencia para fijar precio, nunca para valorar salidas.
 *
 *  4. **La cantidad conductora decide el reparto.** Ver `QuantityDriver`.
 */
class InventoryService
{
    /** Decimales con los que se persiste toda cantidad. Coincide con el esquema. */
    private const SCALE = 4;

    public function __construct(private CodeGeneratorService $codeGenerator) {}

    // ─────────────────────────────────────────────────────────────────────────
    // RECEPCIÓN
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Recibe mercancía creando un lote nuevo.
     *
     * @param  float  $units  Piezas / paquetes recibidos.
     * @param  float  $kg  Peso recibido. Ignorado en productos por unidad y
     *                     recalculado en paquetes de peso fijo.
     * @param  float  $totalCost  Lo que se pagó por el lote completo (de la factura).
     */
    public function receive(
        Product $product,
        float $units,
        float $kg,
        float $totalCost,
        ?Supplier $supplier = null,
        ?string $supplierLotCode = null,
        ?string $purchaseInvoice = null,
        ?CarbonInterface $expirationDate = null,
        ?CarbonInterface $manufacturingDate = null,
        ?CarbonInterface $receivedAt = null,
        ?int $userId = null,
        ?string $notes = null,
        string $referenceType = 'manual',
        ?int $referenceId = null,
    ): Lot {
        [$units, $kg] = $this->normalizeReceivedQuantities($product, $units, $kg);

        if ($totalCost < 0) {
            throw new HttpException(422, 'El costo del lote no puede ser negativo.');
        }

        return DB::transaction(function () use (
            $product,
            $units,
            $kg,
            $totalCost,
            $supplier,
            $supplierLotCode,
            $purchaseInvoice,
            $expirationDate,
            $manufacturingDate,
            $receivedAt,
            $userId,
            $notes,
            $referenceType,
            $referenceId,
        ) {
            $receivedAt ??= now();
            $expirationDate ??= $this->deriveExpiration($product, $manufacturingDate, $receivedAt);

            $costPerKg = $kg > 0 ? $this->scale($totalCost / $kg) : 0.0;
            $costPerUnit = $units > 0 ? $this->scale($totalCost / $units) : 0.0;

            $lot = Lot::create([
                'productId' => $product->id,
                'supplierId' => $supplier?->id,
                'code' => $this->codeGenerator->generateFixed(
                    'LOT',
                    'lot',
                    'code',
                    6,
                ),
                'supplierLotCode' => $supplierLotCode,
                'purchaseInvoice' => $purchaseInvoice,
                'initialUnits' => $units,
                'currentUnits' => $units,
                'initialKg' => $kg,
                'currentKg' => $kg,
                'costPerUnit' => $costPerUnit,
                'costPerKg' => $costPerKg,
                'totalCost' => round($totalCost, 2),
                'receivedAt' => $receivedAt->toDateString(),
                'expirationDate' => $expirationDate?->toDateString(),
                'manufacturingDate' => $manufacturingDate?->toDateString(),
                'status' => LotStatus::ACTIVE->value,
                'notes' => $notes,
                'receivedById' => $userId,
            ]);

            // El promedio ponderado se calcula con el saldo ANTERIOR a esta entrada.
            $this->refreshAverageCost($product, $units, $kg, $totalCost);

            $stock = $this->lockStock($product);

            $movement = $this->writeMovement(
                lot: $lot,
                stock: $stock,
                type: MovementType::PURCHASE,
                units: $units,
                kg: $kg,
                referenceType: $referenceType,
                referenceId: $referenceId,
                userId: $userId,
                notes: $notes,
            );

            $this->applyToStock($stock, $movement);

            return $lot->refresh();
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONSUMO
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Descuenta `$quantity` (expresada en la unidad conductora del producto)
     * repartiéndola entre los lotes en orden FIFO por vencimiento.
     *
     * Dentro de cada lote, el saldo NO conductor se toma en proporción a lo que
     * se llevó del conductor: sacar el 40 % de los kilos de un lote se lleva
     * ~40 % de sus piezas, que es lo que ocurre físicamente. Cuando el conductor
     * de un lote llega a cero se arrastra TODO el residuo del otro saldo, para
     * no dejar kilos o piezas huérfanas que nadie podría consumir después.
     *
     * @return list<ConsumptionLine>
     */
    public function consumeFifo(
        Product $product,
        float $quantity,
        MovementType $type,
        string $referenceType,
        ?int $referenceId = null,
        ?int $userId = null,
        ?string $notes = null,
    ): array {
        if ($type->direction() !== MovementDirection::OUT) {
            throw new HttpException(422, "El tipo {$type->value} no es una salida de inventario.");
        }

        if ($quantity <= 0) {
            throw new HttpException(422, 'La cantidad a consumir debe ser mayor que cero.');
        }

        return DB::transaction(function () use (
            $product,
            $quantity,
            $type,
            $referenceType,
            $referenceId,
            $userId,
            $notes,
        ) {
            $driver = $product->saleMode->driver();
            $stock = $this->lockStock($product);
            $lots = $this->lockFifoLots($product);

            $this->assertEnoughStock($product, $lots, $driver, $quantity);

            $remaining = $this->scale($quantity);
            $lines = [];

            foreach ($lots as $lot) {
                if ($remaining <= 0) {
                    break;
                }

                $lotDriver = $this->lotBalance($lot, $driver);

                if ($lotDriver <= 0) {
                    continue;
                }

                $takeDriver = $this->scale(min($remaining, $lotDriver));
                $depletesLot = $takeDriver >= $lotDriver;

                [$units, $kg] = $this->splitLotTake($lot, $product, $driver, $takeDriver, $depletesLot);

                $movement = $this->writeMovement(
                    lot: $lot,
                    stock: $stock,
                    type: $type,
                    units: $units,
                    kg: $kg,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                    userId: $userId,
                    notes: $notes,
                );

                $this->drawDownLot($lot, $units, $kg);
                $this->applyToStock($stock, $movement);

                $lines[] = new ConsumptionLine(
                    lot: $lot,
                    units: $units,
                    kg: $kg,
                    cost: (float) $movement->totalCost,
                    movement: $movement,
                );

                $remaining = $this->scale($remaining - $takeDriver);
            }

            return $lines;
        });
    }

    /**
     * Descuenta de UN lote elegido explícitamente. Es la vía del alistamiento
     * (el empacador escanea el lote y pesa) y de las mermas, donde el lote no lo
     * decide el sistema sino la persona.
     */
    public function consumeFromLot(
        Lot $lot,
        float $units,
        float $kg,
        MovementType $type,
        string $referenceType,
        ?int $referenceId = null,
        ?int $userId = null,
        ?string $notes = null,
    ): ConsumptionLine {
        if ($type->direction() !== MovementDirection::OUT) {
            throw new HttpException(422, "El tipo {$type->value} no es una salida de inventario.");
        }

        if ($units <= 0 && $kg <= 0) {
            throw new HttpException(422, 'Hay que indicar unidades o kilos a descontar.');
        }

        return DB::transaction(function () use (
            $lot,
            $units,
            $kg,
            $type,
            $referenceType,
            $referenceId,
            $userId,
            $notes,
        ) {
            $product = $lot->product;

            // ORDEN DE BLOQUEO: primero `stock`, después `lot`. Todos los métodos
            // de este servicio lo respetan; invertirlo en uno solo abriría la
            // puerta a deadlocks entre operaciones concurrentes.
            $stock = $this->lockStock($product);

            /** @var Lot $lot */
            $lot = Lot::whereKey($lot->id)->lockForUpdate()->firstOrFail();

            if (! $lot->status->isAvailable()) {
                throw new HttpException(
                    409,
                    "El lote {$lot->code} está {$lot->status->label()} y no puede despachar.",
                );
            }

            $units = $this->scale($units);
            $kg = $product->tracksWeight() ? $this->scale($kg) : 0.0;

            if ($units > (float) $lot->currentUnits) {
                throw new HttpException(
                    409,
                    "El lote {$lot->code} tiene {$lot->currentUnits} unidades; se pidieron {$units}.",
                );
            }

            if ($kg > (float) $lot->currentKg) {
                throw new HttpException(
                    409,
                    "El lote {$lot->code} tiene {$lot->currentKg} kg; se pidieron {$kg}.",
                );
            }

            $movement = $this->writeMovement(
                lot: $lot,
                stock: $stock,
                type: $type,
                units: $units,
                kg: $kg,
                referenceType: $referenceType,
                referenceId: $referenceId,
                userId: $userId,
                notes: $notes,
            );

            $this->drawDownLot($lot, $units, $kg);
            $this->applyToStock($stock, $movement);

            return new ConsumptionLine(
                lot: $lot,
                units: $units,
                kg: $kg,
                cost: (float) $movement->totalCost,
                movement: $movement,
            );
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJUSTES Y ANULACIONES
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ajuste manual sobre un lote (resultado de un conteo físico o corrección).
     * Los deltas pueden ser negativos; el signo decide la dirección del asiento.
     */
    public function adjust(
        Lot $lot,
        float $unitsDelta,
        float $kgDelta,
        string $reason,
        ?int $userId = null,
        string $referenceType = 'adjustment',
        ?int $referenceId = null,
    ): StockMovement {
        if ($unitsDelta === 0.0 && $kgDelta === 0.0) {
            throw new HttpException(422, 'Un ajuste debe mover unidades o kilos.');
        }

        // Un asiento tiene una sola dirección: no puede sumar piezas y restar
        // kilos a la vez. Si hace falta, son dos ajustes.
        if ($unitsDelta !== 0.0 && $kgDelta !== 0.0 && ($unitsDelta > 0) !== ($kgDelta > 0)) {
            throw new HttpException(
                422,
                'Un ajuste no puede sumar en un saldo y restar en el otro. Registra dos ajustes.',
            );
        }

        return DB::transaction(function () use (
            $lot,
            $unitsDelta,
            $kgDelta,
            $reason,
            $userId,
            $referenceType,
            $referenceId,
        ) {
            $product = $lot->product;

            // Orden de bloqueo: `stock` antes de `lot` (ver consumeFromLot).
            $stock = $this->lockStock($product);

            /** @var Lot $lot */
            $lot = Lot::whereKey($lot->id)->lockForUpdate()->firstOrFail();

            $isIncrease = ($unitsDelta + $kgDelta) > 0;
            $type = $isIncrease ? MovementType::ADJUSTMENT_IN : MovementType::ADJUSTMENT_OUT;

            $units = $this->scale(abs($unitsDelta));
            $kg = $product->tracksWeight() ? $this->scale(abs($kgDelta)) : 0.0;

            if (! $isIncrease) {
                if ($units > (float) $lot->currentUnits || $kg > (float) $lot->currentKg) {
                    throw new HttpException(
                        409,
                        "El ajuste dejaría el lote {$lot->code} en negativo.",
                    );
                }
            }

            $movement = $this->writeMovement(
                lot: $lot,
                stock: $stock,
                type: $type,
                units: $units,
                kg: $kg,
                referenceType: $referenceType,
                referenceId: $referenceId,
                userId: $userId,
                notes: $reason,
            );

            $isIncrease
                ? $this->buildUpLot($lot, $units, $kg)
                : $this->drawDownLot($lot, $units, $kg);

            $this->applyToStock($stock, $movement);

            return $movement;
        });
    }

    /**
     * Anula un lote completo: saca todo su saldo del inventario y lo marca VOID.
     * Se usa cuando la recepción fue un error (producto o cantidad equivocada).
     */
    public function voidLot(Lot $lot, string $reason, ?int $userId = null): Lot
    {
        return DB::transaction(function () use ($lot, $reason, $userId) {
            // Orden de bloqueo: `stock` antes de `lot` (ver consumeFromLot).
            $stock = $this->lockStock($lot->product);

            /** @var Lot $lot */
            $lot = Lot::whereKey($lot->id)->lockForUpdate()->firstOrFail();

            if ($lot->status === LotStatus::VOID) {
                throw new HttpException(409, "El lote {$lot->code} ya está anulado.");
            }

            $units = (float) $lot->currentUnits;
            $kg = (float) $lot->currentKg;

            if ($units > 0 || $kg > 0) {
                $movement = $this->writeMovement(
                    lot: $lot,
                    stock: $stock,
                    type: MovementType::VOID_LOT,
                    units: $units,
                    kg: $kg,
                    referenceType: 'void',
                    referenceId: null,
                    userId: $userId,
                    notes: $reason,
                );

                $this->drawDownLot($lot, $units, $kg);
                $this->applyToStock($stock, $movement);
            }

            $lot->forceFill([
                'status' => LotStatus::VOID->value,
                'notes' => trim(($lot->notes ?? '') . " | Anulado: {$reason}"),
            ])->save();

            return $lot->refresh();
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RESERVAS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Aparta stock para un documento (típicamente un pedido confirmado).
     * No mueve el kardex: el stock sigue ahí, sólo deja de estar disponible.
     */
    public function reserve(
        Product $product,
        float $units,
        float $kg,
        string $referenceType,
        int $referenceId,
        ?CarbonInterface $expiresAt = null,
        ?int $userId = null,
        ?string $notes = null,
    ): StockReservation {
        if ($units <= 0 && $kg <= 0) {
            throw new HttpException(422, 'Una reserva debe apartar unidades o kilos.');
        }

        return DB::transaction(function () use (
            $product,
            $units,
            $kg,
            $referenceType,
            $referenceId,
            $expiresAt,
            $userId,
            $notes,
        ) {
            $stock = $this->lockStock($product);

            $units = $this->scale($units);
            $kg = $product->tracksWeight() ? $this->scale($kg) : 0.0;

            if ($units > (float) $stock->availableUnits) {
                throw new HttpException(
                    409,
                    "Sólo hay {$stock->availableUnits} unidades disponibles de {$product->name}; se pidieron {$units}.",
                );
            }

            if ($kg > (float) $stock->availableKg) {
                throw new HttpException(
                    409,
                    "Sólo hay {$stock->availableKg} kg disponibles de {$product->name}; se pidieron {$kg}.",
                );
            }

            $reservation = StockReservation::create([
                'productId' => $product->id,
                'units' => $units,
                'kg' => $kg,
                'status' => ReservationStatus::ACTIVE->value,
                'referenceType' => $referenceType,
                'referenceId' => $referenceId,
                'expiresAt' => $expiresAt,
                'createdById' => $userId,
                'notes' => $notes,
            ]);

            $stock->forceFill([
                'reservedUnits' => $this->scale((float) $stock->reservedUnits + $units),
                'reservedKg' => $this->scale((float) $stock->reservedKg + $kg),
            ])->save();

            return $reservation;
        });
    }

    /** Libera una reserva devolviendo el stock a disponible. */
    public function releaseReservation(
        StockReservation $reservation,
        ReservationStatus $status = ReservationStatus::RELEASED,
    ): StockReservation {
        if ($status->holdsStock()) {
            throw new HttpException(422, 'Para liberar una reserva el estado destino no puede ser ACTIVE.');
        }

        return DB::transaction(function () use ($reservation, $status) {
            /** @var StockReservation $reservation */
            $reservation = StockReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if (! $reservation->status->holdsStock()) {
                // Idempotente: liberar dos veces no debe devolver stock dos veces.
                return $reservation;
            }

            $stock = $this->lockStock($reservation->product);

            $stock->forceFill([
                'reservedUnits' => max(0, $this->scale((float) $stock->reservedUnits - (float) $reservation->units)),
                'reservedKg' => max(0, $this->scale((float) $stock->reservedKg - (float) $reservation->kg)),
            ])->save();

            $reservation->forceFill([
                'status' => $status->value,
                'resolvedAt' => now(),
            ])->save();

            return $reservation;
        });
    }

    /**
     * Barre las reservas activas cuyo `expiresAt` ya pasó y devuelve su stock.
     * Sin esto un pedido abandonado bloquea inventario para siempre.
     *
     * @return int Cantidad de reservas liberadas.
     */
    public function expireStaleReservations(): int
    {
        $expired = StockReservation::query()->expirable()->get();

        foreach ($expired as $reservation) {
            $this->releaseReservation($reservation, ReservationStatus::EXPIRED);
        }

        return $expired->count();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INTERNOS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Valida y ajusta las cantidades de una recepción según el modo de venta.
     *
     * @return array{0: float, 1: float} [units, kg]
     */
    private function normalizeReceivedQuantities(Product $product, float $units, float $kg): array
    {
        $units = $this->scale(max(0, $units));
        $kg = $this->scale(max(0, $kg));

        if (! $product->tracksWeight()) {
            if ($units <= 0) {
                throw new HttpException(422, "«{$product->name}» se maneja por unidad: indica cuántas unidades.");
            }

            return [$units, 0.0];
        }

        if ($product->saleMode->hasDerivedWeight()) {
            if ($units <= 0) {
                throw new HttpException(422, "«{$product->name}» viene en paquetes: indica cuántos paquetes.");
            }

            if ($product->netWeightKg === null || (float) $product->netWeightKg <= 0) {
                throw new HttpException(
                    422,
                    "«{$product->name}» es de peso fijo pero no tiene netWeightKg configurado.",
                );
            }

            // El peso no se pide: se deriva. Un valor distinto en la entrada se ignora.
            return [$units, $this->scale($units * (float) $product->netWeightKg)];
        }

        if ($product->saleMode === SaleMode::BLOCK) {
            // El bloque se cuenta por piezas y se pesa: ambos saldos son reales.
            if ($units <= 0) {
                throw new HttpException(422, "«{$product->name}» se maneja por bloques: indica cuántas piezas.");
            }

            if ($kg <= 0) {
                throw new HttpException(422, "«{$product->name}» se pesa al recibir: indica los kilos.");
            }

            return [$units, $kg];
        }

        if ($kg <= 0) {
            throw new HttpException(422, "«{$product->name}» se vende por peso: indica los kilos recibidos.");
        }

        return [$units, $kg];
    }

    /** Vencimiento derivado de la vida útil cuando no viene explícito. */
    private function deriveExpiration(
        Product $product,
        ?CarbonInterface $manufacturingDate,
        CarbonInterface $receivedAt,
    ): ?CarbonInterface {
        if ($product->shelfLifeDays === null || $product->shelfLifeDays <= 0) {
            return null;
        }

        return ($manufacturingDate ?? $receivedAt)->copy()->addDays($product->shelfLifeDays);
    }

    /**
     * Promedio ponderado del costo, sólo como REFERENCIA para fijar precio.
     * El costo de una salida siempre sale del lote, nunca de aquí.
     */
    private function refreshAverageCost(Product $product, float $units, float $kg, float $totalCost): void
    {
        $existing = Stock::query()
            ->where('productId', $product->id)
            ->selectRaw('COALESCE(SUM(currentUnits), 0) AS units, COALESCE(SUM(currentKg), 0) AS kg')
            ->first();

        $existingUnits = (float) ($existing->units ?? 0);
        $existingKg = (float) ($existing->kg ?? 0);

        $newCostPerKg = $kg > 0 ? $totalCost / $kg : 0.0;
        $newCostPerUnit = $units > 0 ? $totalCost / $units : 0.0;

        $attributes = [
            'lastCostPerKg' => $this->scale($newCostPerKg),
            'lastCostPerUnit' => $this->scale($newCostPerUnit),
            'costUpdatedAt' => now(),
        ];

        if ($kg > 0) {
            $attributes['averageCostPerKg'] = $this->scale(
                ($existingKg * (float) $product->averageCostPerKg + $kg * $newCostPerKg)
                / ($existingKg + $kg),
            );
        }

        if ($units > 0) {
            $attributes['averageCostPerUnit'] = $this->scale(
                ($existingUnits * (float) $product->averageCostPerUnit + $units * $newCostPerUnit)
                / ($existingUnits + $units),
            );
        }

        $product->forceFill($attributes)->save();
    }

    /** Fila de saldo con bloqueo pesimista; la crea si no existía. */
    private function lockStock(Product $product): Stock
    {
        Stock::query()->firstOrCreate(['productId' => $product->id], []);

        /** @var Stock $stock */
        $stock = Stock::query()
            ->where('productId', $product->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $stock;
    }

    /**
     * Lotes disponibles de un producto, en orden FIFO y bloqueados.
     *
     * @return Collection<int, Lot>
     */
    private function lockFifoLots(Product $product): Collection
    {
        return Lot::query()
            ->where('productId', $product->id)
            ->fifo()
            ->withStock()
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  Collection<int, Lot>  $lots
     */
    private function assertEnoughStock(
        Product $product,
        Collection $lots,
        QuantityDriver $driver,
        float $quantity,
    ): void {
        $available = $this->scale(
            $lots->sum(fn (Lot $lot) => $this->lotBalance($lot, $driver)),
        );

        if ($available + 1e-9 < $quantity) {
            $unit = $driver === QuantityDriver::KG ? 'kg' : 'unidades';

            throw new HttpException(
                409,
                "Stock insuficiente de «{$product->name}»: hay {$available} {$unit} en lotes disponibles, " .
                "se necesitan {$quantity} {$unit}.",
            );
        }
    }

    private function lotBalance(Lot $lot, QuantityDriver $driver): float
    {
        return $driver === QuantityDriver::KG
            ? (float) $lot->currentKg
            : (float) $lot->currentUnits;
    }

    /**
     * Reparte lo que se saca de un lote entre los dos saldos.
     *
     * @return array{0: float, 1: float} [units, kg]
     */
    private function splitLotTake(
        Lot $lot,
        Product $product,
        QuantityDriver $driver,
        float $takeDriver,
        bool $depletesLot,
    ): array {
        $lotUnits = (float) $lot->currentUnits;
        $lotKg = (float) $lot->currentKg;

        if (! $product->tracksWeight()) {
            return [$takeDriver, 0.0];
        }

        if ($driver === QuantityDriver::UNITS) {
            // Paquete de peso fijo: el kg es exacto, no proporcional.
            $kg = $product->saleMode->hasDerivedWeight()
                ? $this->scale($takeDriver * (float) $product->netWeightKg)
                : $this->proportional($takeDriver, $lotUnits, $lotKg);

            return [$takeDriver, $depletesLot ? $lotKg : min($kg, $lotKg)];
        }

        // Conductor kg: las piezas salen en proporción al peso retirado.
        $units = $depletesLot
            ? $lotUnits
            : min($this->proportional($takeDriver, $lotKg, $lotUnits), $lotUnits);

        return [$units, $takeDriver];
    }

    /** Regla de tres redondeada: cuánto del total B corresponde a `$part` de A. */
    private function proportional(float $part, float $wholeA, float $totalB): float
    {
        if ($wholeA <= 0) {
            return 0.0;
        }

        return $this->scale($totalB * ($part / $wholeA));
    }

    /** Inserta el asiento del kardex con los saldos antes y después. */
    private function writeMovement(
        Lot $lot,
        Stock $stock,
        MovementType $type,
        float $units,
        float $kg,
        string $referenceType,
        ?int $referenceId,
        ?int $userId,
        ?string $notes,
    ): StockMovement {
        $sign = $type->direction()->sign();
        $unitsBefore = (float) $stock->currentUnits;
        $kgBefore = (float) $stock->currentKg;

        // El costo de la línea se valora con el saldo conductor del producto.
        $totalCost = $lot->product->saleMode->driver() === QuantityDriver::KG
            ? $kg * (float) $lot->costPerKg
            : $units * (float) $lot->costPerUnit;

        return StockMovement::create([
            'productId' => $lot->productId,
            'lotId' => $lot->id,
            'type' => $type->value,
            'direction' => $type->direction()->value,
            'units' => $units,
            'kg' => $kg,
            'costPerUnit' => $lot->costPerUnit,
            'costPerKg' => $lot->costPerKg,
            'totalCost' => round($totalCost, 2),
            'unitsBefore' => $unitsBefore,
            'unitsAfter' => $this->scale($unitsBefore + $sign * $units),
            'kgBefore' => $kgBefore,
            'kgAfter' => $this->scale($kgBefore + $sign * $kg),
            'referenceType' => $referenceType,
            'referenceId' => $referenceId,
            'userId' => $userId,
            'notes' => $notes,
            'movementDate' => now(),
        ]);
    }

    /** Aplica al saldo desnormalizado los MISMOS números que quedaron en el asiento. */
    private function applyToStock(Stock $stock, StockMovement $movement): void
    {
        $stock->forceFill([
            'currentUnits' => (float) $movement->unitsAfter,
            'currentKg' => (float) $movement->kgAfter,
            'lastMovementAt' => now(),
        ])->save();
    }

    private function drawDownLot(Lot $lot, float $units, float $kg): void
    {
        $newUnits = $this->scale((float) $lot->currentUnits - $units);
        $newKg = $this->scale((float) $lot->currentKg - $kg);

        $lot->forceFill([
            'currentUnits' => $newUnits,
            'currentKg' => $newKg,
            'status' => ($newUnits <= 0 && $newKg <= 0)
                ? LotStatus::DEPLETED->value
                : $lot->status->value,
        ])->save();
    }

    private function buildUpLot(Lot $lot, float $units, float $kg): void
    {
        $lot->forceFill([
            'currentUnits' => $this->scale((float) $lot->currentUnits + $units),
            'currentKg' => $this->scale((float) $lot->currentKg + $kg),
            // Reponer sobre un lote agotado lo devuelve a la circulación.
            'status' => $lot->status === LotStatus::DEPLETED
                ? LotStatus::ACTIVE->value
                : $lot->status->value,
        ])->save();
    }

    private function scale(float $value): float
    {
        return round($value, self::SCALE);
    }
}
