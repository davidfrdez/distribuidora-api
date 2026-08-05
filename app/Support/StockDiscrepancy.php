<?php

namespace App\Support;

/**
 * Divergencia detectada entre las tres fuentes del inventario para un producto.
 */
final readonly class StockDiscrepancy
{
    public function __construct(
        public int $productId,
        public string $productSku,
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
            '%s — unidades: stock %s / lotes %s / kardex %s; kg: stock %s / lotes %s / kardex %s',
            $this->productSku,
            $this->stockUnits,
            $this->lotUnits,
            $this->movementUnits,
            $this->stockKg,
            $this->lotKg,
            $this->movementKg,
        );
    }
}
