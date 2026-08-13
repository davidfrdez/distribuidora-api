<?php

namespace Database\Factories;

use App\Enums\CashSessionStatus;
use App\Models\CashSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashSession>
 */
class CashSessionFactory extends Factory
{
    protected $model = CashSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // `businessDate` es único: cada instancia toma un día distinto para
            // no chocar entre sí cuando el test crea varias.
            'businessDate' => now()->subDays(fake()->unique()->numberBetween(0, 3650))->toDateString(),
            'baseAmount' => 100000,
            'status' => CashSessionStatus::OPEN->value,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => CashSessionStatus::CLOSED->value, 'closedAt' => now()]);
    }
}
