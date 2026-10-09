<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state (tabla heredada sistema.p_lgin).
     * create() exige filas validas en p_usrios si hay FK; usar solo en pruebas con datos reales.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'de_lgin' => fake()->unique()->userName(),
            'de_pass' => sha1('password'),
            'in_esta' => '1',
            'cdgo_prfle' => 1,
            'cdgo_usrios' => 1,
        ];
    }
}
