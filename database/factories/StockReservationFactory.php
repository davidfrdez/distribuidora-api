<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Product;
use App\Models\StockReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockReservation>
 */
class StockReservationFactory extends Factory
{
    protected $model = StockReservation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'productId' => Product::factory(),
            'lotId' => null,
            'units' => 0,
            'kg' => 0,
            'status' => ReservationStatus::ACTIVE->value,
            'referenceType' => 'order',
            'referenceId' => 1,
            'expiresAt' => now()->addHours(4),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expiresAt' => now()->subHour()]);
    }

    public function status(ReservationStatus $status): static
    {
        return $this->state(fn () => ['status' => $status->value]);
    }
}
