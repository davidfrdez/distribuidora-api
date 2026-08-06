<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Payable;
use App\Models\PayablePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayablePayment>
 */
class PayablePaymentFactory extends Factory
{
    protected $model = PayablePayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payableId' => Payable::factory(),
            'amount' => 100000,
            'paymentDate' => now()->toDateString(),
            'paymentMethod' => PaymentMethod::TRANSFER->value,
            'createdById' => null,
        ];
    }
}
