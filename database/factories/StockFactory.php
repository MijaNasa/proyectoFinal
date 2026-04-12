<?php

namespace Database\Factories;

use App\Models\Libro;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Stock>
 */
class StockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cantidad = $this->faker->numberBetween(0, 50);
        return [
            'libro_id' => Libro::factory(),
            'sucursal_id' => Sucursal::factory(),
            'cantidad_disponible' => $cantidad,
            'cantidad_reservada' => 0,
            'ubicacion_text' => 'Estantería ' . $this->faker->randomLetter() . '-' . $this->faker->numberBetween(1, 10),
        ];
    }
}
