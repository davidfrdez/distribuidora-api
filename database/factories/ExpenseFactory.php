<?php

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\CashSession;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => ExpenseCategory::ASEO->value,
            'description' => fake()->words(3, true),
            'amount' => fake()->numberBetween(10, 500) * 1000,
            'expenseDate' => now()->toDateString(),
            'paymentMethod' => PaymentMethod::CASH->value,
            'supplierId' => null,
            'createdById' => null,
            'cashSessionId' => null,
        ];
    }

    /** Liga el gasto a un cierre de caja diario (el "nómina y otros" del arqueo). */
    public function forSession(CashSession $session): static
    {
        return $this->state(fn () => [
            'cashSessionId' => $session->id,
            'expenseDate' => $session->businessDate->toDateString(),
        ]);
    }
}
