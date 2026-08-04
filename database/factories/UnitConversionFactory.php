<?php

namespace Database\Factories;

use App\Models\Unit;
use App\Models\UnitConversion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitConversion>
 */
class UnitConversionFactory extends Factory
{
    protected $model = UnitConversion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'productId' => null,
            'fromUnitId' => Unit::factory(),
            'toUnitId' => Unit::factory(),
            'factor' => 1,
        ];
    }
}
