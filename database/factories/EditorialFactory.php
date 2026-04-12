<?php

namespace Database\Factories;

use App\Models\Pais;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Editorial>
 */
class EditorialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->company() . ' Ediciones',
            'razon_social' => $this->faker->company() . ' S.A.',
            'tipo' => $this->faker->randomElement(['Independiente', 'Multinacional', 'Académica']),
            'pais_id' => Pais::inRandomOrder()->first()?->id ?: null,
            'calle' => $this->faker->streetName(),
            'numero' => $this->faker->buildingNumber(),
            'telefono' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
            'activo' => true,
        ];
    }
}
