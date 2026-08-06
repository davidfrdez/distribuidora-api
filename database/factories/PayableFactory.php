<?php

namespace Database\Factories;

use App\Enums\PayableStatus;
use App\Models\Payable;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payable>
 */
class PayableFactory extends Factory
{
    protected $model = Payable::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplierId' => Supplier::factory(),
            'invoiceNumber' => 'FV-' . fake()->numberBetween(1000, 9999),
            'concept' => fake()->words(3, true),
            'issueDate' => now()->toDateString(),
            'dueDate' => now()->addDays(30)->toDateString(),
            'totalAmount' => 500000,
            'paidAmount' => 0,
            'status' => PayableStatus::PENDING->value,
            'createdById' => null,
        ];
    }

    public function dueOn(string $date): static
    {
        return $this->state(fn () => ['dueDate' => $date]);
    }
}
