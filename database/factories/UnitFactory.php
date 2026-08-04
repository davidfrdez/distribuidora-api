<?php

namespace Database\Factories;

use App\Enums\UnitKind;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(4)),
            'name' => fake()->word(),
            'kind' => UnitKind::WEIGHT->value,
            'factorToBase' => 1,
            'isBase' => false,
            'decimals' => 3,
            'active' => true,
        ];
    }

    /** Kilogramo: unidad base de peso. */
    public function kilogram(): static
    {
        return $this->state(fn () => [
            'code' => 'KG',
            'name' => 'Kilogramo',
            'kind' => UnitKind::WEIGHT->value,
            'factorToBase' => 1,
            'isBase' => true,
            'decimals' => 3,
        ]);
    }

    /** Unidad: unidad base de conteo. */
    public function each(): static
    {
        return $this->state(fn () => [
            'code' => 'UN',
            'name' => 'Unidad',
            'kind' => UnitKind::COUNT->value,
            'factorToBase' => 1,
            'isBase' => true,
            'decimals' => 0,
        ]);
    }

    public function kind(UnitKind $kind): static
    {
        return $this->state(fn () => ['kind' => $kind->value]);
    }
}
