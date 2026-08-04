<?php

namespace Database\Factories;

use App\Enums\LotStatus;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lot>
 */
class LotFactory extends Factory
{
    protected $model = Lot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $units = 20;
        $kg = 12.5;
        $costPerKg = 24000;

        return [
            'productId' => Product::factory(),
            'warehouseId' => Warehouse::factory(),
            'supplierId' => null,
            'code' => 'LOT-' . Str::upper(Str::random(8)),
            'supplierLotCode' => Str::upper(Str::random(6)),
            'initialUnits' => $units,
            'currentUnits' => $units,
            'initialKg' => $kg,
            'currentKg' => $kg,
            'costPerKg' => $costPerKg,
            'costPerUnit' => round($costPerKg * $kg / $units, 4),
            'totalCost' => round($costPerKg * $kg, 2),
            'receivedAt' => now()->toDateString(),
            'expirationDate' => now()->addDays(30)->toDateString(),
            'status' => LotStatus::ACTIVE->value,
        ];
    }

    public function quantities(float $units, float $kg): static
    {
        return $this->state(fn () => [
            'initialUnits' => $units,
            'currentUnits' => $units,
            'initialKg' => $kg,
            'currentKg' => $kg,
        ]);
    }

    public function expiringIn(int $days): static
    {
        return $this->state(fn () => ['expirationDate' => now()->addDays($days)->toDateString()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expirationDate' => now()->subDay()->toDateString(),
            'status' => LotStatus::EXPIRED->value,
        ]);
    }

    public function neverExpires(): static
    {
        return $this->state(fn () => ['expirationDate' => null]);
    }

    public function status(LotStatus $status): static
    {
        return $this->state(fn () => ['status' => $status->value]);
    }
}
