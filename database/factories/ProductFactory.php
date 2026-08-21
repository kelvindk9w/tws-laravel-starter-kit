<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'description' => fake()->sentence(12),
            // Centavos: R$ 9,90 – R$ 9.999,90.
            'price' => fake()->numberBetween(990, 999_990),
            'image' => 'https://picsum.photos/seed/'.fake()->unique()->numberBetween(1, 100_000).'/600/400',
        ];
    }
}
