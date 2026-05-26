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
        $tipo      = $this->faker->randomElement(['presencial', 'online']);
        $tipoEnvio = $tipo === 'online' ? $this->faker->randomElement(['domicilio', 'retiro']) : null;

        $calles = ['Av. Pellegrini', 'San Martín', 'Córdoba', 'Santa Fe', 'Urquiza', 'Mitre', 'Rivadavia', '9 de Julio', 'Corrientes', 'Entre Ríos'];
        $estados = ['pendiente_pago', 'en_preparacion', 'listo_para_retirar', 'enviado', 'entregado'];

        return [
            'fecha'           => $this->faker->dateTimeBetween('-1 month', 'now'),
            'cliente_id'      => Cliente::inRandomOrder()->first()?->id ?: Cliente::factory(),
            'user_id'         => User::inRandomOrder()->first()?->id ?: User::factory(),
            'sucursal_id'     => Sucursal::inRandomOrder()->first()?->id ?: Sucursal::factory(),
            'tipo'            => $tipo,
            'estado'          => $tipo === 'online' ? $this->faker->randomElement($estados) : null,
            'tipo_envio'      => $tipoEnvio,
            'direccion_envio' => $tipoEnvio === 'domicilio'
                ? $this->faker->randomElement($calles) . ' ' . $this->faker->numberBetween(100, 9999) . ', Rosario'
                : null,
            'total'           => $this->faker->randomFloat(2, 5000, 50000),
        ];
    }
}
