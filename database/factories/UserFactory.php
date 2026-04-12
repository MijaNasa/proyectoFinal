<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Ciudad;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->firstName(),
            'apellido' => $this->faker->lastName(),
            'dni' => $this->faker->unique()->numerify('########'),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'telefono' => $this->faker->phoneNumber(),
            'calle' => $this->faker->streetName(),
            'numero' => $this->faker->buildingNumber(),
            'codigo_postal' => $this->faker->postcode(),
            'ciudad_id' => Ciudad::inRandomOrder()->first()?->id ?: null,
            'fecha_nacimiento' => $this->faker->date(),
            'activo' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
