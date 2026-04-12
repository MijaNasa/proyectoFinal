<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\User;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Venta>
 */
class VentaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'cliente_id' => Cliente::inRandomOrder()->first()?->id ?: Cliente::factory(),
            'user_id' => User::inRandomOrder()->first()?->id ?: User::factory(),
            'sucursal_id' => Sucursal::inRandomOrder()->first()?->id ?: Sucursal::factory(),
            'tipo' => $this->faker->randomElement(['presencial', 'online']),
            'total' => $this->faker->randomFloat(2, 5000, 50000),
        ];
    }
}
