<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Proveedor>
 */
class ProveedorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre_empresa' => $this->faker->company(),
            'nombre_contacto' => $this->faker->name(),
            'email' => $this->faker->companyEmail(),
            'telefono' => $this->faker->phoneNumber(),
            'direccion' => $this->faker->address(),
            'activo' => true,
        ];
    }
}
