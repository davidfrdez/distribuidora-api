<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductBarcode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductBarcode>
 */
class ProductBarcodeFactory extends Factory
{
    protected $model = ProductBarcode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'productId' => Product::factory(),
            'barcode' => fake()->unique()->ean13(),
            'isWeightEmbedded' => false,
            'isPrimary' => true,
        ];
    }
}
