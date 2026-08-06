<?php

namespace Database\Factories;

use App\Enums\CashSessionStatus;
use App\Models\CashSession;
use App\Models\User;
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
            'openedById' => User::factory(),
            'openingAmount' => 100000,
            'openedAt' => now(),
            'status' => CashSessionStatus::OPEN->value,
        ];
    }
}
