<?php

namespace Database\Factories;

use App\Models\TipoCliente;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cliente>
 */
class ClienteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tipo_cliente_id' => TipoCliente::inRandomOrder()->first()?->id ?: 1,
            'saldo_actual' => $this->faker->randomFloat(2, 0, 10000),
            'preferencias' => json_encode(['newsletter' => true, 'intereses' => ['Ficción', 'Historia']]),
            'estado_abono' => $this->faker->randomElement(['activo', 'pendiente', 'mora']),
        ];
    }
}
