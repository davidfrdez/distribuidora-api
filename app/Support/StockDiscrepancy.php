<?php

namespace App\Support;

/**
 * Divergencia detectada entre las tres fuentes del inventario para una
 * combinación (producto, bodega).
 */
final readonly class StockDiscrepancy
{
    public function __construct(
        public int $productId,
        public int $warehouseId,
        public string $productSku,
        public string $warehouseCode,
        public float $stockUnits,
        public float $lotUnits,
        public float $movementUnits,
        public float $stockKg,
        public float $lotKg,
        public float $movementKg,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'productId' => $this->productId,
            'productSku' => $this->productSku,
            'warehouseId' => $this->warehouseId,
            'warehouseCode' => $this->warehouseCode,
            'units' => [
                'stock' => $this->stockUnits,
                'lots' => $this->lotUnits,
                'movements' => $this->movementUnits,
            ],
            'kg' => [
                'stock' => $this->stockKg,
                'lots' => $this->lotKg,
                'movements' => $this->movementKg,
            ],
        ];
    }

    public function summary(): string
    {
        return sprintf(
            '%s en %s — unidades: stock %s / lotes %s / kardex %s; kg: stock %s / lotes %s / kardex %s',
            $this->productSku,
            $this->warehouseCode,
            $this->stockUnits,
            $this->lotUnits,
            $this->movementUnits,
            $this->stockKg,
            $this->lotKg,
            $this->movementKg,
        );
    }
}
