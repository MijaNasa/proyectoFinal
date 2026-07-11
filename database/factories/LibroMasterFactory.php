<?php

namespace Database\Factories;

use App\Models\Autor;
use App\Models\Categoria;
use App\Models\Editorial;
use App\Models\Idioma;
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
            'editorial_id' => Editorial::factory(),
            'idioma_id' => Idioma::factory(),
            'formato' => $this->faker->randomElement(['Tankobon', 'B6', 'A5', 'Kanzenban', 'Omnibus']),
            'synopsis' => $this->faker->paragraphs(3, true),
            'activo' => true,
        ];
    }
}
