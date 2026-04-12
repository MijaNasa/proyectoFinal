<?php

namespace Database\Factories;

use App\Models\Libro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PrecioLibro>
 */
class PrecioLibroFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $precioCompra = $this->faker->randomFloat(2, 5000, 20000);
        return [
            'libro_id' => Libro::factory(),
            'precio_compra' => $precioCompra,
            'precio_venta' => $precioCompra * 1.5,
            'fecha_desde' => now(),
            'activo' => true,
        ];
    }
}
