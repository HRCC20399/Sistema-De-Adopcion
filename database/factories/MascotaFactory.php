<?php

namespace Database\Factories;

use App\Models\Mascota;
use Illuminate\Database\Eloquent\Factories\Factory;

class MascotaFactory extends Factory
{
    protected $model = Mascota::class;

    public function definition(): array
    {
        $especie = $this->faker->randomElement(['Perro', 'Gato']);
        $razas = $especie === 'Perro' 
            ? ['Labrador', 'Pastor Alemán', 'Mestizo', 'Poodle', 'Beagle']
            : ['Siamés', 'Persa', 'Mestizo', 'Angora'];

        return [
            'nombre' => $this->faker->firstName(),
            'especie' => $especie,
            'raza' => $this->faker->randomElement($razas),
            'edad_aproximada' => $this->faker->numberBetween(1, 12),
            'sexo' => $this->faker->randomElement(['Macho', 'Hembra']),
            'fotografia' => $this->faker->imageUrl(640, 480, 'animals', true),
            'estado' => $this->faker->randomElement(['disponible', 'en proceso', 'adoptada']),
        ];
    }
}