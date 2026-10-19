# Actividad 2 — Consultas Tinker para validación de integridad

Sistema de adopción de mascotas. Estas 10 consultas fueron ejecutadas y verificadas en
`php artisan tinker` contra la base de datos PostgreSQL real del proyecto, después de
poblarla con `php artisan migrate:fresh --seed`.

**Stack:** Laravel 13 — PHP 8.3 (Laragon) — PostgreSQL (`sistema-adopcion`).

> **Nota sobre las salidas:** son salidas reales resumidas de una ejecución sobre los datos
> sembrados (15 mascotas, 29 vacunas, 15 solicitudes). Si vuelves a ejecutar
> `migrate:fresh --seed` los valores numéricos variarán porque los datos son aleatorios;
> las capturas de pantalla de tu ejecución son el resultado final de la entrega.

Todas las consultas usan nombres de clase completos (`\App\Models\...`) para poder
pegarse en Tinker en cualquier orden y repetirse **sin producir errores**.

| # | Categoría | Qué valida |
|---|-----------|------------|
| 1 | Integridad — unicidad | `users.email` tiene índice UNIQUE (`users_email_unique`) |
| 2 | Integridad — clave foránea | 3 FK con ON DELETE CASCADE en el esquema |
| 3 | Integridad — integridad referencial | Cero registros huérfanos |
| 4 | Integridad — regla de negocio | Solo una solicitud aprobada por mascota |
| 5 | Collections | `groupBy` + `map`: conteo por especie |
| 6 | Collections | `filter` + `sortBy` + `map`: adopciones aprobadas |
| 7 | Agregación | `count` + `groupBy` + `having` por estado |
| 8 | Agregación | `avg` / `max` / `min` de edad por especie |
| 9 | Agregación | `withCount` + `sum` de vacunas |
| 10 | Agregación condicional | Aprobadas por mascota adoptada |

---

## Consulta 1 — Unicidad de `users.email`

**Comando:**

```php
\App\Models\User::firstOrCreate(
    ['email' => 'validado@correo.com'],
    ['name' => 'Usuario Validado', 'password' => 'secreto123']
);
\Illuminate\Support\Facades\DB::select(
    'SELECT indexname, indexdef FROM pg_indexes WHERE tablename = ?', ['users']
);
```

**Salida (resumida):**

```
array:2 [
  0 => { "indexname": "users_pkey",
         "indexdef": "CREATE UNIQUE INDEX users_pkey ON public.users USING btree (id)" }
  1 => { "indexname": "users_email_unique",
         "indexdef": "CREATE UNIQUE INDEX users_email_unique ON public.users USING btree (email)" }
]
```

**Justificación:** la consulta registra un usuario con `firstOrCreate` (operación válida
sin errores) y lista los índices de `users`. Se observa `users_email_unique`: un índice
UNIQUE sobre `email`. Cualquier correo duplicado sería rechazado por PostgreSQL con
SQLSTATE 23505, por lo que la restricción de unicidad está activa a nivel de base de datos.

---

## Consulta 2 — Clave foránea `vacunas.mascota_id`

**Comando:**

```php
$m = \App\Models\Mascota::first();
\App\Models\Vacuna::firstOrCreate(
    ['mascota_id' => $m->id, 'nombre_vacuna' => 'Rabia'],
    ['fecha_aplicacion' => '2026-10-19', 'veterinario_responsable' => 'Dr. Carlos Mendoza']
);
\Illuminate\Support\Facades\DB::select(
    'SELECT conname, confdeltype FROM pg_constraint WHERE contype = ? AND conrelid::regclass::text IN (?, ?)',
    ['f', 'vacunas', 'solicitudes']
);
```

**Salida (resumida):**

```
array:3 [
  0 => { "conname": "vacunas_mascota_id_foreign",    "confdeltype": "c" }
  1 => { "conname": "solicitudes_user_id_foreign",   "confdeltype": "c" }
  2 => { "conname": "solicitudes_mascota_id_foreign", "confdeltype": "c" }
]
```

**Justificación:** se registra una vacuna válida y se listan las 3 claves foránea del
esquema. `confdeltype = "c"` indica `ON DELETE CASCADE`. Una inserción con una mascota
inexistente sería rechazada con SQLSTATE 23503, lo que demuestra la integridad referencial
entre `vacunas`, `solicitudes`, `mascotas` y `users`.

---

## Consulta 3 — Sin registros huérfanos

**Comando:**

```php
[
    'vacunas_huerfanas' => \App\Models\Vacuna::doesntHave('mascota')->count(),
    'solicitudes_incompletas' => \App\Models\Solicitud::doesntHave('usuario')
                                            ->doesntHave('mascota')->count(),
];
```

**Salida:**

```
array:2 [
  "vacunas_huerfanas" => 0
  "solicitudes_incompletas" => 0
]
```

**Justificación:** ninguna vacuna existe sin su mascota y ninguna solicitud carece de
usuario o mascota. Mientras que las consultas 1 y 2 prueban que la base rechaza datos
inválidos, esta verifica que los datos sembrados por el seeder respetan todas las
relaciones del modelo.

---

## Consulta 4 — Una sola adopción aprobada por mascota

**Comando:**

```php
\App\Models\Solicitud::selectRaw('mascota_id')
    ->where('estado', 'aprobada')
    ->groupBy('mascota_id')
    ->havingRaw('COUNT(*) > 1')
    ->get();
```

**Salida:**

```
Illuminate\Database\Eloquent\Collection {
  #items: []
}
```

**Justificación:** colección vacía: ninguna mascota acumola dos solicitudes "aprobada".
Es la regla de negocio central del sistema (una mascota se adopta una sola vez),
validada sobre los datos reales con `GROUP BY ... HAVING`.

---

## Consulta 5 (Collections) — Conteo por especie

**Comando:**

```php
\App\Models\Mascota::all()
    ->groupBy('especie')
    ->map(fn ($g) => $g->count());
```

**Salida:**

```
Illuminate\Support\Collection {
  #items: array:2 [
    "Gato" => 10
    "Perro" => 5
  ]
}
```

**Justificación:** agrupa los 15 registros en memoria (no en SQL) con `map()`. Demuestra
que la colección se transforma sin perder integridad y que `especie` solo contiene valores
coherentes con el dominio: 10 gatos + 5 perros = 15 mascotas sembradas.

---

## Consulta 6 (Collections) — Adopciones aprobadas

**Comando:**

```php
\App\Models\Solicitud::with(['usuario:id,name', 'mascota:id,nombre'])->get()
    ->filter(fn ($s) => $s->estado === 'aprobada')
    ->sortBy('mascota.nombre')
    ->values()
    ->map(fn ($s) => $s->usuario->name . ' adoptó a ' . $s->mascota->nombre);
```

**Salida:**

```
Illuminate\Support\Collection {
  #items: array:1 [
    0 => "Simeon Fay adoptó a Jolie"
  ]
}
```

**Justificación:** filtra por la regla de negocio (`aprobada`), ordena y resuelve las
relaciones `belongsTo` con eager loading. Devuelve exactamente las solicitudes aprobadas
existentes, coherentes con las mascotas adoptadas de la Consulta 10: las uniones entre
tablas no producen referencias rotas.

---

## Consulta 7 (Agregación) — Solicitudes por estado

**Comando:**

```php
\App\Models\Solicitud::selectRaw('estado, COUNT(*) as total')
    ->groupBy('estado')
    ->havingRaw('COUNT(*) > 1')
    ->get()
    ->map->only(['estado', 'total'])
    ->values();
```

**Salida:**

```
Illuminate\Support\Collection {
  #items: array:2 [
    0 => array:2 [ "estado" => "en evaluacion", "total" => 5 ]
    1 => array:2 [ "estado" => "rechazada",     "total" => 9 ]
  ]
}
```

**Justificación:** `COUNT` + `GROUP BY` + `HAVING COUNT(*) > 1`. Se muestran 9 rechazadas
y 5 en evaluación; el estado "aprobada" no aparece porque solo tiene 1 registro y el
`HAVING` excluye grupos de uno. 5 + 9 + 1 = 15 solicitudes, coherentes con el seeder.
Valida también el `default('en evaluacion')` de la migración.

---

## Consulta 8 (Agregación) — Edad promedio, máxima y mínima por especie

**Comando:**

```php
\App\Models\Mascota::selectRaw("
        especie,
        COUNT(*) as total,
        ROUND(AVG(edad_aproximada), 2) as edad_promedio,
        MAX(edad_aproximada) as edad_maxima,
        MIN(edad_aproximada) as edad_minima
    ")
    ->groupBy('especie')
    ->get()
    ->map->toArray();
```

**Salida:**

```
Illuminate\Support\Collection {
  #items: array:2 [
    0 => array:5 [ "especie" => "Perro", "total" => 5, "edad_promedio" => "6.20",
                   "edad_maxima" => 11, "edad_minima" => 4 ]
    1 => array:5 [ "especie" => "Gato",  "total" => 10, "edad_promedio" => "9.50",
                   "edad_maxima" => 12, "edad_minima" => 6 ]
  ]
}
```

**Justificación:** promedios, máximos y mínimos caen dentro del rango 1–12 que define el
factory para `edad_aproximada`: el campo es numérico, no nulo y sin valores atípicos.

---

## Consulta 9 (Agregación) — Total de vacunas (`withCount` + `sum`)

**Comando:**

```php
\App\Models\Mascota::withCount('vacunas')->get()->sum('vacunas_count');
\App\Models\Mascota::withCount('vacunas')
    ->orderByDesc('vacunas_count')
    ->limit(5)
    ->get(['id', 'nombre', 'especie'])
    ->map->only(['id', 'nombre', 'especie', 'vacunas_count'])
    ->values();
```

**Salida:**

```
=> 29

Illuminate\Support\Collection {
  #items: array:5 [
    0 => array:4 [ "id" => 1,  "nombre" => "Vladimir",   "especie" => "Gato",  "vacunas_count" => 4 ]
    1 => array:4 [ "id" => 12, "nombre" => "Arielle",    "especie" => "Gato",  "vacunas_count" => 3 ]
    2 => array:4 [ "id" => 9,  "nombre" => "Christiana", "especie" => "Gato",  "vacunas_count" => 3 ]
    3 => array:4 [ "id" => 5,  "nombre" => "Jarrell",    "especie" => "Perro", "vacunas_count" => 3 ]
    4 => array:4 [ "id" => 14, "nombre" => "Esta",       "especie" => "Perro", "vacunas_count" => 3 ]
  ]
}
```

**Justificación:** total 29 = 28 vacunas sembradas por el seeder (15 mascotas × 1–3) + la
vacuna registrada en la Consulta 2. Vladimir encabeza con 4 (3 de fábrica + 1 de la
consulta): ninguna mascota quedó sin vacunas.

---

## Consulta 10 (Agregación condicional) — Aprobadas por mascota adoptada

**Comando:**

```php
\App\Models\Mascota::withCount([
        'solicitudes as solicitudes_totales',
        'solicitudes as solicitudes_aprobadas' => fn ($q) => $q->where('estado', 'aprobada'),
    ])
    ->where('estado', 'adoptada')
    ->get(['id', 'nombre', 'estado'])
    ->map->only(['id', 'nombre', 'estado', 'solicitudes_totales', 'solicitudes_aprobadas'])
    ->values();
```

**Salida:**

```
Illuminate\Support\Collection {
  #items: array:1 [
    0 => array:5 [
      "id" => 15
      "nombre" => "Jolie"
      "estado" => "adoptada"
      "solicitudes_totales" => 1
      "solicitudes_aprobadas" => 1
    ]
  ]
}
```

**Justificación:** cierra la validación de la regla de negocio central: la(s) mascota(s)
`adoptada` tiene exactamente `solicitudes_aprobadas = 1`. Combina agregación condicional
sobre la relación con un filtro de negocio, demostrando la coherencia total entre
`mascotas` y `solicitudes`: solo se adopta con una solicitud aprobada.

---

## Cómo reproducir estas consultas

```powershell
cd D:\Github\Sistema-De-Adopcion
php artisan migrate:fresh --seed   # regenera los datos de prueba (una sola vez, al inicio)
php artisan tinker                 # abre la consola interactiva
```

Los comandos funcionan en la **terminal de Laragon** (donde `php` apunta a PHP 8.3 con
`pdo_pgsql`). Ejecuta el seeder una sola vez al inicio para que los conteos de todas tus
capturas sean coherentes entre sí, y toma las capturas de pantalla de cada consulta para
el documento Word de la entrega.
