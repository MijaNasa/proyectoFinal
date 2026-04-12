<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Idioma>
 */
class IdiomaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->unique()->randomElement(['Español', 'Inglés', 'Francés', 'Alemán', 'Portugués', 'Italiano']),
            'codigo' => $this->faker->unique()->languageCode(),
            'activo' => true,
        ];
    }
}
