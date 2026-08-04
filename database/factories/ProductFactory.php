<?php

namespace Database\Factories;

use App\Enums\SaleMode;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'categoryId' => null,
            'sku' => Str::upper(Str::random(8)),
            'name' => fake()->words(3, true),
            'saleMode' => SaleMode::WEIGHT->value,
            'tracksWeight' => true,
            'netWeightKg' => 0.85,
            'basePrice' => fake()->numberBetween(15, 60) * 1000,
            'priceIncludesTax' => true,
            'taxPercent' => 0,
            'trackLots' => true,
            'shelfLifeDays' => 30,
            'expirationAlertDays' => 7,
            'sellable' => true,
            'purchasable' => true,
            'active' => true,
        ];
    }

    /** Peso variable: precio por kg, se pesa al alistar. */
    public function byWeight(): static
    {
        return $this->state(fn () => [
            'saleMode' => SaleMode::WEIGHT->value,
            'tracksWeight' => true,
        ]);
    }

    /** Por unidad: el peso no interviene en el precio ni en el inventario. */
    public function byUnit(): static
    {
        return $this->state(fn () => [
            'saleMode' => SaleMode::UNIT->value,
            'tracksWeight' => false,
            'netWeightKg' => null,
        ]);
    }

    /** Paquete de peso fijo: se cobra por unidad y el kg se deriva. */
    public function fixedPack(float $netWeightKg = 0.5): static
    {
        return $this->state(fn () => [
            'saleMode' => SaleMode::FIXED_PACK->value,
            'tracksWeight' => true,
            'netWeightKg' => $netWeightKg,
        ]);
    }

    public function withoutLots(): static
    {
        return $this->state(fn () => ['trackLots' => false]);
    }
}
