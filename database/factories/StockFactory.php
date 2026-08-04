<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stock>
 */
class StockFactory extends Factory
{
    protected $model = Stock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'productId' => Product::factory(),
            'warehouseId' => Warehouse::factory(),
            'currentUnits' => 0,
            'reservedUnits' => 0,
            'currentKg' => 0,
            'reservedKg' => 0,
        ];
    }
}
