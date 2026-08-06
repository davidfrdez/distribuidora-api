<?php

namespace Database\Factories;

use App\Enums\CashMovementType;
use App\Models\CashMovement;
use App\Models\CashSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashMovement>
 */
class CashMovementFactory extends Factory
{
    protected $model = CashMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = CashMovementType::INCOME;

        return [
            'cashSessionId' => CashSession::factory(),
            'type' => $type->value,
            'direction' => $type->direction()->value,
            'amount' => 50000,
            'concept' => fake()->words(3, true),
            'createdById' => null,
        ];
    }
}
