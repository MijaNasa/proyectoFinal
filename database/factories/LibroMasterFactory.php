<?php

namespace Database\Factories;

use App\Models\Autor;
use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LibroMaster>
 */
class LibroMasterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titulo' => $this->faker->sentence(3),
            'titulo_original' => $this->faker->sentence(3),
            'portada' => 'https://picsum.photos/seed/' . bin2hex(random_bytes(5)) . '/400/600',
            'autor_id' => Autor::factory(),
            'categoria_id' => Categoria::factory(),
            'activo' => true,
        ];
    }
}
