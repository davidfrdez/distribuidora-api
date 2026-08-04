<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(6)),
            'name' => fake()->company(),
            'nit' => fake()->numerify('9########-#'),
            'contactName' => fake()->name(),
            'phone' => fake()->numerify('30########'),
            'city' => 'Bogotá',
            'paymentTermDays' => 30,
            'active' => true,
        ];
    }
}
