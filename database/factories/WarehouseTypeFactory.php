<?php

namespace Database\Factories;

use App\Models\WarehouseType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WarehouseType>
 */
class WarehouseTypeFactory extends Factory
{
    protected $model = WarehouseType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(6)),
            'name' => fake()->word(),
            'defaultTempMin' => 0,
            'defaultTempMax' => 4,
            'requiresColdChain' => true,
            'active' => true,
        ];
    }

    public function freezer(): static
    {
        return $this->state(fn () => [
            'code' => 'CONG',
            'name' => 'Congelación',
            'defaultTempMin' => -18,
            'defaultTempMax' => -12,
            'requiresColdChain' => true,
        ]);
    }

    public function dry(): static
    {
        return $this->state(fn () => [
            'code' => 'SECO',
            'name' => 'Almacenamiento seco',
            'defaultTempMin' => null,
            'defaultTempMax' => null,
            'requiresColdChain' => false,
        ]);
    }
}
