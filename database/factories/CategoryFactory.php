<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parentId' => null,
            'code' => Str::upper(Str::random(6)),
            'name' => fake()->words(2, true),
            'displayOrder' => 0,
            'active' => true,
        ];
    }
}
