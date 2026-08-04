<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'businessName' => $name . ' S.A.S.',
            'nit' => fake()->numerify('9########-#'),
            'address' => fake()->streetAddress(),
            'city' => 'Bogotá',
            'phone' => fake()->numerify('60#######'),
            'email' => fake()->unique()->companyEmail(),
            'timezone' => 'America/Bogota',
            'currency' => 'COP',
            'minOrderAmount' => 0,
            'defaultWeightTolerancePercent' => 10,
            'reservationTtlMinutes' => 240,
        ];
    }
}
