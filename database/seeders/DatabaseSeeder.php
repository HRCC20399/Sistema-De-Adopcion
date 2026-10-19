<?php

namespace Database\Seeders;

use App\Models\Mascota;
use App\Models\Solicitud;
use App\Models\User;
use App\Models\Vacuna;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear usuarios
        $usuarios = User::factory(10)->create();

        // 2. Crear mascotas. Todas inician "disponibles"; el proceso de
        //    adopción define su estado final ("en proceso" o "adoptada").
        $mascotas = Mascota::factory(15)->create([
            'estado' => 'disponible',
        ]);

        // 3. Crear vacunas para cada mascota
        $mascotas->each(function ($mascota) {
            Vacuna::factory(rand(1, 3))->create([
                'mascota_id' => $mascota->id,
            ]);
        });

        // 4. Crear solicitudes garantizando reglas de negocio:
        //    - Un usuario no repite postulación por la misma mascota.
        //    - Solo una solicitud aprobada por mascota (queda "adoptada").
        //    - Mascotas con solicitudes pendientes pasan a "en proceso".
        $mascotasAdoptadas = collect();

        foreach ($usuarios as $usuario) {
            $disponibles = $mascotas
                ->whereNotIn('id', $mascotasAdoptadas->pluck('id'))
                ->values();

            if ($disponibles->isEmpty()) {
                continue;
            }

            $mascotasSeleccionadas = $disponibles->random(
                min(rand(1, 2), $disponibles->count())
            );

            foreach ($mascotasSeleccionadas as $mascota) {
                $estado = fake()->randomElement(['en evaluacion', 'aprobada', 'rechazada']);

                Solicitud::factory()->create([
                    'user_id' => $usuario->id,
                    'mascota_id' => $mascota->id,
                    'estado' => $estado,
                ]);

                if ($estado === 'aprobada') {
                    $mascota->update(['estado' => 'adoptada']);
                    $mascotasAdoptadas->push($mascota);
                } elseif ($mascota->estado === 'disponible') {
                    $mascota->update(['estado' => 'en proceso']);
                }
            }
        }
    }
}
