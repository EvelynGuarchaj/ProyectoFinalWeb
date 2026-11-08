# 12 · Planes de tratamiento (solo lectura)

**Estado:** Implementado
**Depende de:** 01-esquema-y-semilla (tablas `oportunidades`, `oportunidad_productos`, `historial_etapas`, `productos`; modelos `Oportunidad`, `OportunidadProducto`, `HistorialEtapa`; `VerificacionSeeder`), 03-cuentas-roles (middleware `panel` y `rol`; menú con la entrada reservada `oportunidades.index`; `MenuTest`), 05-pacientes (patrón `Cliente::visiblePara()` + `findOrFail()`; vista `clientes/show`; `Formato::quetzales()` y `Formato::fechaGT()`)
**Fecha:** 2026-11-08
**Dueña:** A
**Rama:** `spec-12-planes`
**Objetivo:** que el administrador y el coordinador consulten, en solo lectura, la lista y el detalle de los planes de tratamiento que les corresponden.

**Requisitos que cumple:** L6 de `docs/especificacion.md` en su parte "Planes (`/oportunidades`), solo lectura"; la parte de L5 "solo ve a su equipo" y S5 (lo ajeno responde 404) para los planes. La parte "Reporte del mes" de L6 la cumple el spec 14.

---

## 1. Alcance

**Incluye**
- Scope `Oportunidad::visiblePara(User $u)` y scope `Oportunidad::conTotal()` en `app/Models/Oportunidad.php`.
- `app/Http/Controllers/OportunidadController.php` con las acciones `index` y `show`.
- Rutas `GET /oportunidades` (`oportunidades.index`) y `GET /oportunidades/{oportunidad}` (`oportunidades.show`) con `rol:administrador,supervisor`. Ninguna otra.
- Vistas `resources/views/oportunidades/index.blade.php` y `resources/views/oportunidades/show.blade.php`.
- En `resources/views/clientes/show.blade.php`, el título de cada plan pasa a ser un enlace a `oportunidades.show`.
- Pruebas `tests/Feature/OportunidadesTest.php` y ajuste de `tests/Feature/MenuTest.php` (deja de simular `oportunidades.index`).
- Anotar en `docs/verificacion.md` las filas de L6 (planes) y la de S5 para planes.

**No incluye**
- Crear, editar, cambiar de etapa, borrar planes o tocar sus tratamientos: lo hace el asesor en la SPA (A4, F3). No hay botones, formularios ni rutas para eso.
- Enlace del paciente desde el detalle del plan: el paciente se muestra como texto (si fue reasignado a otro equipo, el enlace daría 404).
- Pronóstico, probabilidad por plan y reporte del mes (spec 14).
- Búsqueda por texto en la lista: L6 solo pide filtros por etapa y por asesor.
- Actividades del paciente en el detalle del plan.
- `lang/es/validation.php` (spec 16): aquí no hay formularios que validar.

---

## 2. Modelo de datos

**No hay migraciones nuevas.** Se leen `oportunidades` (`id`, `cliente_id`, `vendedor_id`, `titulo`, `etapa`, `motivo_perdida`, `fecha_cierre`, `created_at`), `oportunidad_productos` (`oportunidad_id`, `producto_id`, `cantidad`, `precio_unitario`), `productos.nombre`, `historial_etapas` (`usuario_id`, `etapa_anterior`, `etapa_nueva`, `created_at`), `clientes.nombre` y `users` (`name`, `rol`, `supervisor_id`) del spec 01.

### 2.1 Scope `visiblePara`

```php
/**
 * Planes que la persona del panel puede ver (L5, S5). El administrador ve todos;
 * el coordinador, los de los asesores de su equipo (activos o no), según
 * oportunidades.vendedor_id. Se usa con findOrFail(): lo ajeno responde 404.
 */
public function scopeVisiblePara(Builder $query, User $u): Builder
{
    if ($u->esAdmin()) {
        return $query;
    }

    if ($u->esSupervisor()) {
        return $query->whereIn('oportunidades.vendedor_id', User::select('id')
            ->where('rol', 'vendedor')
            ->where('supervisor_id', $u->id));
    }

    // Expresión fija: nadie más ve planes desde el panel.
    return $query->whereRaw('1 = 0');
}
```

- La visibilidad sigue al **asesor del plan** (`oportunidades.vendedor_id`), no al asesor actual del paciente: un plan cerrado de un paciente reasignado o liberado sigue siendo del asesor que lo trabajó (crédito de L6).
- No se usan políticas ni `abort(403)`: lo ajeno no existe para quien pregunta.

### 2.2 Scope `conTotal` (total en SQL)

`withSum` no sirve porque no multiplica. Se agrega una subconsulta correlacionada con `addSelect`:

```php
public function scopeConTotal(Builder $query): Builder
{
    return $query
        ->select('oportunidades.*')
        ->addSelect(['total' => OportunidadProducto::selectRaw('COALESCE(SUM(cantidad * precio_unitario), 0)')
            ->whereColumn('oportunidad_productos.oportunidad_id', 'oportunidades.id')]);
}
```

- `selectRaw` con expresión fija, sin datos del usuario.
- El total sale de MySQL como texto DECIMAL. PHP no lo suma ni lo multiplica: solo `Formato::quetzales()` lo convierte para mostrarlo.
- Un plan sin líneas tiene total `0.00` ("Seguimiento anual").

### 2.3 Lista: `OportunidadController@index`

Filtros de la consulta (`?etapa=` y `?asesor=`, combinables):

| Entrada | Se usa si | Si no es válida |
|---|---|---|
| `etapa` | Es texto y está en `Oportunidad::ETAPAS` | Se ignora sin aviso (lista sin filtro de etapa) |
| `asesor` | Es texto y `ctype_digit` | Se ignora sin aviso |

- Un asesor de otro equipo en `?asesor=` no rompe nada: el scope ya excluye sus planes y la lista sale vacía. Nunca hay página de error.
- Los valores llegan a SQL como parámetros (`where('etapa', $etapa)`, `where('vendedor_id', (int) $asesor)`).

Consulta:

```php
Oportunidad::visiblePara($usuario)
    ->conTotal()
    ->with(['cliente:id,nombre', 'vendedor:id,name'])
    ->when($etapa !== '', fn (Builder $q) => $q->where('etapa', $etapa))
    ->when($asesor !== '', fn (Builder $q) => $q->where('vendedor_id', (int) $asesor))
    ->orderByDesc('created_at')
    ->orderByDesc('id')
    ->paginate(15)
    ->withQueryString();
```

Opciones del filtro de asesor: las mismas que `ClienteController::asesoresVisibles()` (administrador: todos los asesores; coordinador: los de su equipo; activos y desactivados, por nombre). Se extrae a un método reutilizable para no duplicarlo (ver 3, paso 2).

### 2.4 Detalle: `OportunidadController@show`

```php
$plan = Oportunidad::visiblePara($request->user())
    ->conTotal()
    ->with(['cliente:id,nombre', 'vendedor:id,name'])
    ->findOrFail($oportunidad);

$lineas = $plan->lineas()
    ->with('producto:id,nombre')
    ->select('oportunidad_productos.*')
    ->selectRaw('cantidad * precio_unitario AS subtotal')
    ->orderBy('id')
    ->get();

$historial = $plan->historial()
    ->with('usuario:id,name')
    ->orderBy('created_at')
    ->orderBy('id')
    ->get();
```

- Sin enlace implícito de modelo: el parámetro llega como `string` y se busca con el scope, como en `ClienteController`. Un id inexistente, no numérico o ajeno → **404**.
- El subtotal de cada línea y el total salen de MySQL; el precio es el guardado en la línea (`precio_unitario`), no el del catálogo.

### 2.5 Rutas

En `routes/web.php`, dentro del grupo `auth` + `panel` y del grupo `rol:administrador,supervisor` que ya tiene `clientes`:

| Método y URL | Nombre | Acción |
|---|---|---|
| `GET /oportunidades` | `oportunidades.index` | `OportunidadController@index` |
| `GET /oportunidades/{oportunidad}` | `oportunidades.show` | `OportunidadController@show` |

`Route::resource('oportunidades', OportunidadController::class)->only(['index', 'show'])`. Un `PATCH`, `PUT` o `DELETE` a `/oportunidades/{id}` responde **405** y no cambia nada. El menú ya tiene "Planes de tratamiento" y lo muestra en cuanto existe `oportunidades.index` (spec 03).

### 2.6 Vista `oportunidades/index.blade.php`

- Encabezado "Planes de tratamiento".
- Formulario `GET` a `oportunidades.index` con:
  - `<select name="etapa">`: "Todas las etapas" (valor vacío) y las seis etapas con `Oportunidad::ETAPAS_EN_PANTALLA`.
  - `<select name="asesor">`: "Todos los asesores" (valor vacío) y los asesores de 2.3 (con "(desactivado)" si aplica).
  - Botón "Filtrar" y enlace "Quitar filtros".
- Columnas: **Paciente**, **Título** (enlace a `oportunidades.show`), **Asesor**, **Etapa** (nombre en pantalla), **Total** (`Formato::quetzales`), **Fecha de cierre** (`Formato::fechaGT`; "—" si está abierto).
- Sin planes: "No hay planes de tratamiento con esos filtros."
- Paginación con `$planes->links()`.
- Sin botones de crear, editar ni borrar.

### 2.7 Vista `oportunidades/show.blade.php`

- Título del plan y enlace "Volver a planes de tratamiento".
- Datos: Paciente (texto), Asesor, Etapa (nombre en pantalla), Fecha de cierre (hora de Guatemala o "—"), y **Motivo del rechazo** solo si la etapa es `perdida`.
- Tabla "Tratamientos": **Tratamiento**, **Cantidad**, **Precio** (guardado), **Subtotal**; pie con **Total**. Sin líneas: "Este plan todavía no tiene tratamientos." y total Q0.00.
- Tabla "Historial de etapas", del más antiguo al más reciente: **Etapa anterior** ("—" si es NULL), **Etapa nueva**, **Quién** (`usuario->name`), **Cuándo** (`Formato::fechaGT`).
- Sin botones de editar, cambiar de etapa ni agregar tratamientos.

### 2.8 Enlace desde el paciente

En `clientes/show.blade.php`, la celda del título pasa de texto a `<a href="{{ route('oportunidades.show', $plan->id) }}">{{ $plan->titulo }}</a>`. Para el coordinador, un plan de un asesor de otro equipo que aparezca en un paciente disponible enlazaría a un 404; en ese caso el título se muestra sin enlace. `ClienteController@show` calcula los ids visibles con `Oportunidad::visiblePara($usuario)->where('cliente_id', $cliente->id)->pluck('id')` y la vista enlaza solo esos.

Todo texto en las vistas con `{{ }}`, nunca `{!! !!}`.

---

## 3. Plan de implementación

Cada paso deja `php artisan test` en verde.

1. **Scopes.** `visiblePara` y `conTotal` en `Oportunidad`. Pruebas de modelo: Alicia ve 11, Sergio 9, Sandra 2, un asesor 0; total de "Renovación de planes" = `5650.00` y de "Seguimiento anual" = `0.00`.
2. **Ruta, controlador y lista.** `OportunidadController@index` con filtros (2.3), vista `index` (2.6). Mover `asesoresVisibles()` al scope `User::asesoresVisiblesPara(User $u)` y usarlo en `ClienteController` y aquí. Quitar `oportunidades.index` de `RESERVADAS` en `MenuTest`. Pruebas CA1, CA2, CA3, CA8, CA9, CA10.
3. **Detalle.** `OportunidadController@show` (2.4) y vista `show` (2.7). Pruebas CA4, CA5, CA6, CA7, CA11.
4. **Enlace desde el paciente** (2.8). Prueba CA12.
5. **Cierre.** `php artisan route:list --path=oportunidades` (solo dos GET); `git grep -n "{!!" laravel/resources/views/oportunidades` vacío; anotar en `docs/verificacion.md`.

**Pruebas:** contra MySQL `crm_test`, con `VerificacionSeeder` en `setUp()` y `actingAs` de Alicia, Sergio o Sandra, como en los specs anteriores.

---

## 4. Criterios de aceptación

Salen de los Dado/Cuando/Entonces de L6 (planes), L5 y S5. Semilla: `php artisan migrate:fresh --seeder=VerificacionSeeder`.

- **CA1 · Administrador ve todo y filtra.** Dado Alicia, cuando abre `/oportunidades`, entonces ve los 11 planes; con `?etapa=ganada` (filtro "Aceptado") ve 5; con `?asesor={id de Diego}` ve 2 ("Licencias de caja" y "Mantenimiento"); con los dos filtros juntos ve 1 ("Licencias de caja").
- **CA2 · Coordinador ve su equipo.** Dado Sergio, cuando abre `/oportunidades`, entonces ve 9 planes (7 de Valeria y 2 de Diego) y ninguno de Carlos ("Plan anual", "Cotización de equipo" no aparecen); el filtro de asesor solo ofrece a Diego Castillo y Valeria López. Dado Sandra, ve 2 planes, los de Carlos.
- **CA3 · Total y fecha de cierre en la lista.** Dado Alicia en la lista, cuando mira "Equipo para sucursal", entonces ve el paciente Ferretería Xela, el asesor Valeria López, la etapa "Aceptado", el total Q3,600.00 y la fecha de cierre `31 oct 2026, 21:30` (en la base es `2026-11-01 03:30:00` UTC); "Seguimiento anual" muestra "Interesado", Q0.00 y "—".
- **CA4 · Detalle con líneas e historial.** Dado Alicia, cuando abre el detalle de "Renovación de planes", entonces ve Plan avanzado 2 × Q2,600.00 = Q5,200.00, Instalación 1 × Q450.00 = Q450.00, total Q5,650.00 y 5 registros de historial de Valeria López, el primero con etapa anterior "—" y etapa nueva "Interesado", y el último "En negociación" → "Aceptado".
- **CA5 · Motivo del rechazo.** Dado Alicia, cuando abre el detalle de "Plan familiar", entonces ve la etapa "Rechazado" y el motivo "Eligió a la competencia"; el detalle de "Renovación de planes" no muestra la fila del motivo.
- **CA6 · Precio guardado.** Dado Alicia, cuando abre "Plan familiar", entonces la línea "Licencia anterior" muestra Q999.99 (el precio guardado en la línea), aunque el producto esté desactivado o su precio de catálogo sea otro.
- **CA7 · Lo ajeno responde 404.** Dado Sergio, cuando abre a mano `/oportunidades/{id de "Plan anual"}` (de Carlos, equipo de Sandra), entonces el panel responde **404**; también `/oportunidades/999999` y `/oportunidades/abc`.
- **CA8 · Filtros no válidos.** Dado Alicia, cuando abre `/oportunidades?etapa=xyz`, `?asesor=abc` o `?etapa[]=ganada`, entonces responde 200 y ve los 11 planes; dado Sergio con `?asesor={id de Carlos}`, ve "No hay planes de tratamiento con esos filtros." y responde 200.
- **CA9 · Solo lectura.** Dado el panel, cuando se envía a mano un `PATCH` o `DELETE` a `/oportunidades/{id}` (con token CSRF), entonces responde 405 y en la base no cambia nada; `php artisan route:list --path=oportunidades` muestra solo dos rutas GET; las vistas no tienen botones de crear, editar ni borrar.
- **CA10 · Menú y acceso.** Dado Alicia o Sergio, el menú muestra "Planes de tratamiento" con enlace a `/oportunidades`. Dado un visitante sin sesión, `/oportunidades` lo redirige al inicio de sesión.
- **CA11 · XSS.** Dado un plan con título `<img src=x onerror=alert(1)>`, cuando Alicia abre la lista y el detalle, entonces se ve el texto literal; `git grep -n "{!!" laravel/resources/views/oportunidades` está vacío.
- **CA12 · Enlace desde el paciente.** Dado Alicia en el detalle de Comercial El Quetzal, cuando pulsa "Renovación de planes", entonces llega a `/oportunidades/{id}` con su detalle.
- **CA13 · Pruebas.** `cd laravel && php artisan test` termina en verde, incluidas `OportunidadesTest` y `MenuTest` ajustada.

---

## 5. Decisiones tomadas y descartadas

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| Scope `visiblePara` + `findOrFail` (lo ajeno → 404) | Políticas que responden 403 | S5 y CLAUDE.md: lo ajeno responde 404 en las dos puertas. Mismo patrón que `Cliente::visiblePara()` |
| Visibilidad por `oportunidades.vendedor_id` | Por el asesor actual del paciente | L6: el coordinador ve los planes de los asesores de su equipo; los planes conservan su asesor al reasignar o liberar |
| Total con subconsulta `addSelect` + `selectRaw` fija | `withSum` | `withSum` no multiplica `cantidad * precio_unitario`. Decisión cerrada por la integrante |
| `COALESCE(..., 0)` en el total | Dejar NULL y que lo resuelva `Formato` | El valor ya sale correcto de la base para cualquier uso futuro (spec 14) |
| Subtotal de línea en SQL (`selectRaw`) | Multiplicar en PHP | Regla de L6: el dinero no se suma ni se multiplica en PHP |
| Orden por `created_at` y `id` descendentes, 15 por página con `withQueryString()` | Por título; sin paginar | Decidido por la integrante el 2026-11-08; igual que Pacientes |
| Filtros no válidos se ignoran sin aviso | Aviso "El filtro no es válido." | Decidido por la integrante el 2026-11-08; igual que el filtro de asesor de Pacientes |
| Título enlaza al detalle desde la lista y desde el paciente; el paciente en el detalle es texto | Enlazar también al paciente | Decidido por la integrante el 2026-11-08: un paciente reasignado a otro equipo daría 404 |
| Historial del más antiguo al más reciente, "—" en la etapa anterior de la creación | "Creación"; orden descendente | Decidido por la integrante el 2026-11-08: se lee el recorrido de arriba abajo |
| `Route::resource(...)->only(['index', 'show'])` | Rutas de edición deshabilitadas en la vista | L6: no hay formularios ni rutas para tocar planes; sin ruta, un PATCH a mano responde 405 |
| Sin enlace implícito de modelo | `Route model binding` | El binding buscaría sin el scope y abriría lo ajeno |
| Reutilizar la lista de asesores visibles de `ClienteController` como scope `User::asesoresVisiblesPara(User $u)` | Copiarla; método estático que devuelve la colección | Un solo lugar decide qué asesores ve cada rol. Scope decidido por la integrante el 2026-11-08, mismo estilo que los `visiblePara` |
| Enlaces desde el paciente: el controlador pasa los ids visibles calculados con `Oportunidad::visiblePara()` | Comparar `supervisor_id` en la vista | Decidido por la integrante el 2026-11-08: el scope es la única fuente de la regla |

---

## 6. Riesgos

- **Mismo total que la API y el reporte.** `GET /oportunidades/:id` (spec 11, integrante B) y el reporte (spec 14) deben dar el mismo total con `SUM(cantidad * precio_unitario)` sobre el precio guardado. Si alguno usa `productos.precio`, "Plan familiar" no coincidirá (Licencia anterior Q999.99).
- **Refactor de `asesoresVisibles()`.** Moverlo toca `ClienteController` (dueña A). Si se cambia su comportamiento, `ClientesTest` debe seguir en verde.
- **Enlace desde el paciente para el coordinador.** Un paciente disponible puede mostrar planes cerrados de asesores de otro equipo; si se enlazan sin comprobar, Sergio llegaría a un 404 (2.8).
- **Línea de `MenuTest`.** Si no se quita `oportunidades.index` de `RESERVADAS`, la prueba registra una ruta simulada con el mismo nombre encima de la real y oculta errores.
- **`select('oportunidades.*')` en `conTotal`.** Si otra consulta encadena un `select` después, puede perder la columna `total`; el scope se llama siempre después del `visiblePara` y antes de `with`.

---

## Lo que **no** entra en este spec

- Crear, editar, cambiar de etapa o borrar planes desde el panel.
- Enlace al paciente desde el detalle del plan.
- Pronóstico y reporte del mes (spec 14).
- Búsqueda por texto en la lista.
