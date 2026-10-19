<?php

namespace Database\Factories;

use App\Models\Mascota;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SolicitudFactory extends Factory
{
    protected $model = Solicitud::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'mascota_id' => Mascota::factory(),
            'tipo_vivienda' => $this->faker->randomElement(['Casa con patio', 'Departamento', 'Finca']),
            'experiencia_previa' => $this->faker->sentence(),
            'estado' => $this->faker->randomElement(['en evaluacion', 'aprobada', 'rechazada']),
            'observacion_admin' => $this->faker->optional(0.6)->sentence(),
        ];
    }
}