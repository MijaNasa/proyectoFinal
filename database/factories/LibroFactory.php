<?php

namespace Database\Factories;

use App\Models\LibroMaster;
use App\Models\Editorial;
use App\Models\Idioma;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Libro>
 */
class LibroFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'isbn' => $this->faker->unique()->isbn13(),
            'master_id' => LibroMaster::factory(),
            'año_edicion' => $this->faker->year(),
            'cantidad_paginas' => $this->faker->numberBetween(100, 1000),
            'activo' => true,
        ];
    }
}
