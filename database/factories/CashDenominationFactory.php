<?php

namespace Database\Factories;

use App\Models\CashDenomination;
use App\Models\CashSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashDenomination>
 */
class CashDenominationFactory extends Factory
{
    protected $model = CashDenomination::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cashSessionId' => CashSession::factory(),
            'denomination' => fake()->randomElement([50, 100, 200, 500, 1000, 2000, 5000, 10000, 20000, 50000, 100000]),
            'quantity' => fake()->numberBetween(0, 50),
        ];
    }
}
