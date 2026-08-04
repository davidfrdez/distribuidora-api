<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouseTypeId' => null,
            'code' => Str::upper(Str::random(6)),
            'name' => fake()->words(2, true),
            'tempMin' => 0,
            'tempMax' => 4,
            'isDefault' => false,
            'isQuarantine' => false,
            'sellable' => true,
            'active' => true,
        ];
    }

    public function quarantine(): static
    {
        return $this->state(fn () => [
            'code' => 'CUAR',
            'name' => 'Cuarentena',
            'isQuarantine' => true,
            'sellable' => false,
        ]);
    }

    /** Sin rango propio: hereda el del tipo de bodega. */
    public function withoutOwnRange(): static
    {
        return $this->state(fn () => ['tempMin' => null, 'tempMax' => null]);
    }
}
