<?php

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\Lot;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = MovementType::PURCHASE;

        return [
            'productId' => Product::factory(),
            'lotId' => Lot::factory(),
            'type' => $type->value,
            'direction' => $type->direction()->value,
            'units' => 1,
            'kg' => 1,
            'movementDate' => now(),
        ];
    }

    public function type(MovementType $type): static
    {
        return $this->state(fn () => [
            'type' => $type->value,
            'direction' => $type->direction()->value,
        ]);
    }
}
