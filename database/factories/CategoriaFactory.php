<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Categoria>
 */
class CategoriaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->unique()->randomElement([
                'Novela', 'Ciencia Ficción', 'Fantasía', 'Terror', 'Historia', 
                'Biografía', 'Ensayo', 'Poesía', 'Infantil', 'Autoayuda', 
                'Cocina', 'Arte', 'Viajes', 'Deportes', 'Ciencia'
            ]),
            'descripcion' => $this->faker->sentence(),
            'activo' => true,
        ];
    }
}
