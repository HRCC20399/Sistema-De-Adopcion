<?php

namespace Database\Factories;

use App\Models\Mascota;
use App\Models\Vacuna;
use Illuminate\Database\Eloquent\Factories\Factory;

class VacunaFactory extends Factory
{
    protected $model = Vacuna::class;

    public function definition(): array
    {
        return [
            'mascota_id' => Mascota::factory(),
            'nombre_vacuna' => $this->faker->randomElement(['Rabia', 'Quíntuple', 'Triple Felina', 'Parvovirus']),
            'fecha_aplicacion' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'veterinario_responsable' => 'Dr. ' . $this->faker->lastName(),
        ];
    }
}