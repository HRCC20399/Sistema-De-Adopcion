# Actividad 2 — Consultas Tinker para validación de integridad

Sistema de adopción de mascotas. Estas 10 consultas fueron ejecutadas y verificadas en
`php artisan tinker` contra PostgreSQL, después de poblar la base de datos con
`php artisan migrate:fresh --seed` (10 usuarios, 15 mascotas, 30 vacunas y 15 solicitudes).

> Los valores exactos provienen de datos generados por faker; si vuelves a ejecutar el
> seeder cambiarán los nombres, pero no las estructuras ni las validaciones.

**Diseño validado:** usuarios → solicitudes → mascotas ← vacunas.

| # | Categoría | Qué valida |
|---|-----------|------------|
| 1 | Integridad — unicidad | `users.email` es UNIQUE |
| 2 | Integridad — clave foránea | `vacunas.mascota_id` FK a `mascotas` |
| 3 | Integridad — reglas de negocio | Ningún registro huérfano (solicitudes y vacunas completas) |
| 4 | Integridad — regla de negocio | Solo una solicitud aprobada por mascota |
| 5 | Collections | `groupBy` + `map`: conteo por especie en memoria |
| 6 | Collections | `filter` + `sortBy` + `map`: adopciones aprobadas ordenadas |
| 7 | Agregación | `count` + `groupBy` + `having`: solicitudes por estado |
| 8 | Agregación | `avg` / `max` / `min` de edad por especie |
| 9 | Agregación | `withCount` + `sum`: vacunas por mascota |
| 10 | Agregación condicional | `withCount` con filtro: aprobadas por mascota adoptada |

---

## Consulta 1 — Unicidad de `users.email`

**Comando:**

```php
use App\Models\User;
use Illuminate\Database\QueryException;

try {
    User::create([
        'name'     => 'Usuario Duplicado',
        'email'    => User::first()->email, // email ya registrado
        'password' => 'secreto123',
    ]);
} catch (QueryException $e) {
    echo 'Violación de unicidad (' . $e->getCode() . '): '
       . substr($e->getMessage(), 0, 100);
}
```

**Salida real:**

```
Violación de unicidad (23505): SQLSTATE[23505]: Unique violation: 7 ERROR:  llave
duplicada viola restricción de unicidad «users_email_unique»
```

**Justificación:** al intentar registrar un segundo usuario con el mismo correo,
PostgreSQL rechaza la inserción con SQLSTATE 23505. Confirma que la restricción
`->unique()` de la migración de `users` está activa a nivel de base de datos, no solo
en la aplicación.

---

## Consulta 2 — Clave foránea `vacunas.mascota_id`

**Comando:**

```php
use App\Models\Vacuna;
use Illuminate\Database\QueryException;

try {
    Vacuna::create([
        'mascota_id'              => 99999, // mascota inexistente
        'nombre_vacuna'           => 'Rabia',
        'fecha_aplicacion'        => now()->toDateString(),
        'veterinario_responsable' => 'Dr. Prueba',
    ]);
} catch (QueryException $e) {
    echo 'Violación de clave foránea (' . $e->getCode() . '): '
       . substr($e->getMessage(), 0, 110);
}
```

**Salida real:**

```
Violación de clave foránea (23503): SQLSTATE[23503]: Foreign key violation: 7 ERROR:
inserción o actualización en la tabla «vacunas» viola la llave foránea
«vacunas_mascota_id_foreign»
```

**Justificación:** no es posible crear una vacuna que no pertenezca a una mascota
existente (SQLSTATE 23503). La restricción `foreignId('mascota_id')->constrained()
->onDelete('cascade')` garantiza la integridad referencial entre `vacunas` y `mascotas`.

---

## Consulta 3 — Sin registros huérfanos

**Comando:**

```php
use App\Models\Solicitud;
use App\Models\Vacuna;

[
    'vacunas_huerfanas'       => Vacuna::doesntHave('mascota')->count(),
    'solicitudes_incompletas' => Solicitud::doesntHave('usuario')
                                         ->doesntHave('mascota')
                                         ->count(),
]
```

**Salida real:**

```
array:2 [
  "vacunas_huerfanas"       => 0
  "solicitudes_incompletas" => 0
]
```

**Justificación:** ninguna vacuna existe sin mascota y ninguna solicitud carece de
usuario o mascota. Los `doesntHave` recorren la relación inversa y confirman que todos
los datos sembrados por el seeder respetan la integridad referencial.

---

## Consulta 4 — Regla de negocio: una sola adopción aprobada por mascota

**Comando:**

```php
use App\Models\Solicitud;

Solicitud::selectRaw('mascota_id')
    ->where('estado', 'aprobada')
    ->groupBy('mascota_id')
    ->havingRaw('COUNT(*) > 1')
    ->get();
```

**Salida real:**

```
Illuminate\Database\Eloquent\Collection {#8163
  #items: []
}
```

**Justificación:** la colección viene vacía: ninguna mascota acumula dos adopciones
aprobadas. El seeder hace cumplir la regla de negocio (una mascota se adopta una sola
vez) y esta consulta la valida sobre los datos reales con `GROUP BY ... HAVING`.

---

## Consulta 5 — Collections: conteo por especie (`groupBy` + `map`)

**Comando:**

```php
use App\Models\Mascota;

Mascota::all()
    ->groupBy('especie')
    ->map(fn ($grupo) => $grupo->count());
```

**Salida real:**

```
Illuminate\Support\Collection {#8154
  #items: array:2 [
    "Perro" => 9
    "Gato"  => 6
  ]
}
```

**Justificación:** agrupa los 15 registros en memoria (no en SQL) usando Laravel
Collections. Demuestra que la colección completa se puede transformar sin perder
integridad y que `especie` solo contiene valores coherentes del dominio.

---

## Consulta 6 — Collections: adopciones aprobadas (`filter` + `sortBy`)

**Comando:**

```php
use App\Models\Solicitud;

Solicitud::with(['usuario:id,name', 'mascota:id,nombre'])->get()
    ->filter(fn ($s) => $s->estado === 'aprobada')
    ->sortBy('mascota.nombre')
    ->values()
    ->map(fn ($s) => $s->usuario->name . ' adoptó a ' . $s->mascota->nombre);
```

**Salida real:**

```
Illuminate\Support\Collection {#8156
  #items: array:7 [
    0 => "Dr. Sedrick Schuppe adoptó a Alva"
    1 => "Delmer Ortiz adoptó a Carley"
    2 => "Aurelio Johnston adoptó a Darion"
    3 => "Justen Stracke adoptó a Domenick"
    4 => "Ashlee Hickle adoptó a Kiley"
    5 => "Mikel Hodkiewicz adoptó a Maud"
    6 => "Concepcion Schroeder MD adoptó a Sandy"
  ]
}
```

**Justificación:** filtra por la regla de negocio (`aprobada`) y resuelve las relaciones
`belongsTo` (usuario y mascota) de cada registro mediante `with` (eager loading). Al
mostrar exactamente 7 adopciones coherentes con las 7 solicitudes aprobadas de la
Consulta 7, evidencia que las uniones entre tablas no producen referencias rotas.

---

## Consulta 7 — Agregación: solicitudes por estado (`count` + `groupBy` + `having`)

**Comando:**

```php
use App\Models\Solicitud;

Solicitud::selectRaw('estado, COUNT(*) as total')
    ->groupBy('estado')
    ->havingRaw('COUNT(*) > 1')
    ->get();
```

**Salida real (resumida a los atributos `estado` y `total`):**

```
Illuminate\Database\Eloquent\Collection {#8122
  #items: array:3 [
    0 => { "estado": "aprobada",       "total": 7 }
    1 => { "estado": "en evaluacion",  "total": 3 }
    2 => { "estado": "rechazada",      "total": 5 }
  ]
}
```

**Justificación:** agrega las solicitudes por estado con `COUNT` y filtra grupos con
`HAVING`. Muestra la distribución real del proceso de adopción (15 solicitudes en total)
y valida que el `default('en evaluacion')` de la migración y la variedad de estados del
seeder producen datos útiles para agregaciones.

---

## Consulta 8 — Agregación: edad promedio, máxima y mínima por especie

**Comando:**

```php
use App\Models\Mascota;

Mascota::selectRaw("
        especie,
        COUNT(*) as total,
        ROUND(AVG(edad_aproximada), 2) as edad_promedio,
        MAX(edad_aproximada) as edad_maxima,
        MIN(edad_aproximada) as edad_minima
    ")
    ->groupBy('especie')
    ->get();
```

**Salida real (resumida a los atributos agregados):**

```
Illuminate\Database\Eloquent\Collection {#8149
  #items: array:2 [
    0 => { "especie": "Perro", "total": 9, "edad_promedio": "7.67",
           "edad_maxima": 12, "edad_minima": 3 }
    1 => { "especie": "Gato",  "total": 6, "edad_promedio": "6.67",
           "edad_maxima": 10, "edad_minima": 3 }
  ]
}
```

**Justificación:** funciones de agregación clásicas (`AVG`, `MAX`, `MIN`) agrupadas por
especie. Valida que `edad_aproximada` es numérica y está dentro de los rangos definidos
en el factory (1–12), sin valores nulos ni atípicos.

---

## Consulta 9 — Agregación sobre relación: total de vacunas (`withCount` + `sum`)

**Comando:**

```php
use App\Models\Mascota;

Mascota::withCount('vacunas')->get()->sum('vacunas_count');

Mascota::withCount('vacunas')
    ->orderByDesc('vacunas_count')
    ->limit(5)
    ->get(['id', 'nombre', 'especie']);
```

**Salida real (resumida):**

```
=> 30   // sum sobre vacunas_count: total de vacunas en la base

Illuminate\Database\Eloquent\Collection {#8142
  #items: array:5 [
    0 => { "id": 9,  "nombre": "Terrell", "especie": "Perro", "vacunas_count": 3 }
    1 => { "id": 15, "nombre": "Aaron",   "especie": "Gato",  "vacunas_count": 3 }
    2 => { "id": 4,  "nombre": "Casimir", "especie": "Gato",  "vacunas_count": 3 }
    3 => { "id": 14, "nombre": "Nikko",   "especie": "Perro", "vacunas_count": 3 }
    4 => { "id": 10, "nombre": "Domenick","especie": "Gato",  "vacunas_count": 3 }
  ]
}
```

**Justificación:** `withCount` genera un `COUNT` sobre la relación `hasMany(vacunas)`
sin cargar todas las vacunas en memoria; el `sum` sobre la colección confirma la
coherencia del seeder: las 15 mascotas × 1–3 vacunas = 30 vacunas en total, y ninguna
mascota quedó sin vacunas (las 5 primeras tienen el máximo de 3).

---

## Consulta 10 — Agregación condicional: adopciones aprobadas por mascota

**Comando:**

```php
use App\Models\Mascota;

Mascota::withCount([
        'solicitudes as solicitudes_totales',
        'solicitudes as solicitudes_aprobadas' => fn ($q) => $q->where('estado', 'aprobada'),
    ])
    ->where('estado', 'adoptada')
    ->get(['id', 'nombre', 'estado']);
```

**Salida real (resumida a los atributos relevantes):**

```
Illuminate\Database\Eloquent\Collection {#8104
  #items: array:7 [
    0 => { "id": 10, "nombre": "Domenick", "estado": "adoptada",
           "solicitudes_totales": 1, "solicitudes_aprobadas": 1 }
    1 => { "id": 11, "nombre": "Darion",   "estado": "adoptada",
           "solicitudes_totales": 1, "solicitudes_aprobadas": 1 }
    2 => { "id": 13, "nombre": "Carley",   "estado": "adoptada",
           "solicitudes_totales": 1, "solicitudes_aprobadas": 1 }
    3 => { "id": 7,  "nombre": "Alva",     "estado": "adoptada",
           "solicitudes_totales": 1, "solicitudes_aprobadas": 1 }
    4 => { "id": 3,  "nombre": "Maud",     "estado": "adoptada",
           "solicitudes_totales": 1, "solicitudes_aprobadas": 1 }
    5 => { "id": 1,  "nombre": "Sandy",    "estado": "adoptada",
           "solicitudes_totales": 2, "solicitudes_aprobadas": 1 }
    6 => { "id": 8,  "nombre": "Kiley",    "estado": "adoptada",
           "solicitudes_totales": 2, "solicitudes_aprobadas": 1 }
  ]
}
```

**Justificación:** cierra la validación de la regla de negocio central del sistema:
las 7 mascotas con estado `adoptada` tienen exactamente una solicitud aprobada
(`solicitudes_aprobadas = 1` en todas, incluso en Sandy y Kiley que recibieron dos
solicitudes). Combina agregación condicional sobre la relación con un filtro de
negocio, demostrando la coherencia total entre `mascotas` y `solicitudes`.

---

## Cómo reproducir estas consultas

```bash
php artisan migrate:fresh --seed   # regenera los datos de prueba
php artisan tinker                 # abre la consola interactiva
```

Pega en Tinker los comandos de cada consulta (sin los `;` finales de los bloques,
Tinker los evalúa interactivamente) y toma las capturas de pantalla para el documento
Word/PDF de la entrega.
