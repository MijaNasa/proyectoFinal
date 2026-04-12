<?php

namespace Database\Factories;

use App\Models\Pais;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Autor>
 */
class AutorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->firstName(),
            'apellido' => $this->faker->lastName(),
            'pais_id' => Pais::inRandomOrder()->first()?->id ?: null,
            'fecha_nacimiento' => $this->faker->date('Y-m-d', '-30 years'),
            'biografia' => $this->faker->paragraph(),
            'activo' => true,
        ];
    }
}
