<?php

namespace Database\Factories;

use App\Models\Ciudad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sucursal>
 */
class SucursalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Sucursal ' . $this->faker->city(),
            'codigo' => strtoupper($this->faker->unique()->lexify('SUC-???')),
            'ciudad_id' => Ciudad::inRandomOrder()->first()?->id ?: null,
            'calle' => $this->faker->streetName(),
            'numero' => $this->faker->buildingNumber(),
            'telefono' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
            'es_deposito_central' => false,
            'activo' => true,
        ];
    }
}
