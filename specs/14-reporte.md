# 14 · Reporte del mes

**Estado:** Borrador
**Depende de:** 01-esquema-y-semilla (tablas `users`, `clientes`, `oportunidades`, `oportunidad_productos`, `productos`; `VerificacionSeeder`), 03-cuentas-roles (middleware `panel` y `rol`; menú con la entrada reservada `reportes.index`; `MenuTest`), 07-asignacion (`App\Services\Cartera::MAXIMO`), 09-equipo (`EquipoController`: mes de Guatemala, `ventasDelMes()`, aviso de mes no válido; `Formato::mesEnPalabras()`), 12-planes (`User::asesoresVisiblesPara()`, `Oportunidad::ABIERTAS`)
**Fecha:** 2026-11-08
**Dueña:** A
**Rama:** `spec-14-reporte`
**Objetivo:** que el administrador y el coordinador vean el reporte del mes (ventas, total vendido, pronóstico y cartera) por asesor, por tratamiento y por equipo, calculado en MySQL.

**Requisitos que cumple:** L6 de `docs/especificacion.md` en su parte "Reporte del mes (`/reportes`)", con las reglas de R7 del enunciado; los criterios de cifras de la sección 11.5 que se miran en el panel; y la parte pendiente del criterio de L4 (reasignación) que se comprueba en el reporte. La parte "Planes (`/oportunidades`)" de L6 la cumplió el spec 12.

---

## 1. Alcance

**Incluye**
- `app/Support/Mes.php`: elige el mes de la consulta (`?mes=AAAA-MM`, por omisión el mes actual de Guatemala, aviso si no es válido). Se extrae de `EquipoController` para que `/equipo` y `/reportes` decidan igual.
- `app/Services/Reporte.php`: las consultas del reporte con el constructor de consultas (ventas del mes, pronóstico, por asesor, por equipo, totales y por tratamiento). `EquipoController` pasa a usar `Reporte::ventasDelMes()` en lugar de su copia.
- `app/Http/Controllers/ReporteController.php` con la acción `index`.
- Ruta `GET /reportes` (`reportes.index`) con `rol:administrador,supervisor`.
- Vista `resources/views/reportes/index.blade.php`: selector `<input type="month">`, tabla por asesor, tabla por equipo (solo administrador) y tabla por tratamiento, cada una con su fila de totales.
- Pruebas `tests/Feature/ReporteTest.php`; ajuste de `tests/Feature/MenuTest.php` (deja de simular `reportes.index`); `EquipoTest` sigue en verde tras el refactor.
- Anotar en `docs/verificacion.md` las filas de L6 (reporte), las de 11.5 del panel y completar la fila de L4 ("reporte pendiente del spec 14").

**No incluye**
- `GET /reportes/resumen` de la API (spec 15, integrante B) ni la vista `#/resumen` de la SPA (spec 22).
- Exportar el reporte a CSV (reto opcional; sería otra ruta en `ReporteController`).
- Gráficas, comparación entre meses o rangos de fechas: L6 pide un mes.
- Guardar el equipo de cada venta: la venta cuenta en el equipo actual del asesor (L6, "Crédito de una venta"). No hay migraciones.
- Enlaces desde las filas del reporte a los planes o al asesor.
- `lang/es/validation.php` (spec 16): aquí no hay formulario que se envíe con POST; el mes no válido se resuelve con aviso.

---

## 2. Modelo de datos

**No hay migraciones nuevas.** Se leen `users` (`id`, `name`, `rol`, `supervisor_id`, `activo`), `clientes.vendedor_id` (cartera), `oportunidades` (`id`, `vendedor_id`, `etapa`, `fecha_cierre`), `oportunidad_productos` (`oportunidad_id`, `producto_id`, `cantidad`, `precio_unitario`) y `productos.nombre`. **Nunca `productos.precio`**: el dinero sale siempre del precio guardado en la línea.

### 2.1 Reglas de cálculo (R7 y sección 11)

| Cifra | Expresión en MySQL | Filtro |
|---|---|---|
| Mes de una venta | `DATE_FORMAT(o.fecha_cierre - INTERVAL 6 HOUR, '%Y-%m') = ?` con el mes como parámetro | Solo `o.etapa = 'ganada'` |
| Ventas | `COUNT(DISTINCT o.id)` | Ganadas del mes |
| Total vendido | `SUM(l.cantidad * l.precio_unitario)` | Ganadas del mes |
| Pronóstico | `ROUND(SUM(l.cantidad * l.precio_unitario * <probabilidad>), 2)` | `o.etapa IN ('prospecto','calificado','propuesta','negociacion')`, **sin filtro de mes** |
| Cartera | `COUNT(clientes)` del asesor, mostrada como "x de 10" (`Cartera::MAXIMO`) | Sin filtro de mes |

`<probabilidad>` es una expresión fija con decimales exactos (DECIMAL, no FLOAT), en una constante de `Reporte`:

```php
private const PROBABILIDAD = "CASE o.etapa WHEN 'prospecto' THEN 0.10 WHEN 'calificado' THEN 0.25 "
    ."WHEN 'propuesta' THEN 0.50 WHEN 'negociacion' THEN 0.75 ELSE 0 END";
```

- **Redondeo al final.** Las subconsultas devuelven la suma **sin redondear** (`bruto`); cada fila (asesor, equipo, empresa, tratamiento, totales) aplica `ROUND(SUM(bruto), 2)` una sola vez. Como todo es DECIMAL exacto, `ROUND` de la suma de sumas parciales es igual a `ROUND` de la suma de todas las líneas, y nunca se suman valores ya redondeados.
- **Crédito.** La venta y el pronóstico son del asesor de `oportunidades.vendedor_id` y cuentan en el equipo al que ese asesor pertenece hoy (`users.supervisor_id`).
- **Nada en PHP.** Los montos llegan de MySQL como texto DECIMAL; PHP no los suma ni los multiplica. `Formato::quetzales()` solo los convierte para mostrarlos.
- **Datos del usuario.** El único dato que entra a SQL es el mes, siempre como parámetro `?`. `selectRaw` y `whereRaw` solo llevan expresiones fijas.

### 2.2 `App\Support\Mes`

```php
final class Mes
{
    public const PATRON = '/^\d{4}-(0[1-9]|1[0-2])$/';
    public const NO_VALIDO = 'Elija un mes válido (AAAA-MM).';

    /** Mes actual de Guatemala (UTC−6 fijo), 'AAAA-MM'. */
    public static function actual(): string;

    /**
     * Ausente o vacío → [mes actual, null]; cumple PATRON → [valor, null];
     * cualquier otra cosa (incluido un arreglo) → [mes actual, NO_VALIDO].
     */
    public static function elegido(mixed $valor): array;
}
```

Es el mismo comportamiento que `EquipoController::mesElegido()` (spec 09), que se borra y pasa a llamar a `Mes::elegido()`.

### 2.3 `App\Services\Reporte`

Todas las consultas reciben a quien pregunta (`User $quien`) y limitan a los asesores de su alcance con una subconsulta, para no chocar con el alias `s` de `users`:

```php
->whereIn('u.id', User::asesoresVisiblesPara($quien)->select('id'))
// administrador: todos los asesores; coordinador: los de su equipo; activos y desactivados
```

| Método | Devuelve | Detalle |
|---|---|---|
| `ventasDelMes(string $mes): Builder` | `vendedor_id, ventas, total` | La de `EquipoController` (spec 09), sin cambios: `LEFT JOIN` a las líneas, `COUNT(DISTINCT o.id)` y `SUM(l.cantidad * l.precio_unitario)` de las ganadas del mes, agrupado por `o.vendedor_id`. Pública: la usa también `/equipo` |
| `pronosticoAbierto(): Builder` | `vendedor_id, bruto` | `oportunidades o JOIN oportunidad_productos l`, `whereIn('o.etapa', Oportunidad::ABIERTAS)`, `SUM(l.cantidad * l.precio_unitario * (PROBABILIDAD)) AS bruto` agrupado por `o.vendedor_id`. **Sin redondear y sin mes** |
| `porAsesor(User $quien, string $mes): Collection` | Una fila por asesor del alcance | `users u` + `LEFT JOIN users s` (coordinador) + `leftJoinSub` de las dos anteriores + cartera (`withCount('clientes')` o subconsulta `COUNT(*)` de `clientes`). Columnas: `name`, `activo`, `coordinador`, `cartera`, `COALESCE(v.ventas, 0)`, `COALESCE(v.total, 0)`, `ROUND(COALESCE(p.bruto, 0), 2)`. Incluye asesores sin ventas (salen en 0). Orden: coordinador y asesor por nombre |
| `totales(User $quien, string $mes): object` | Una fila | La misma base que `porAsesor` sin agrupar: `COALESCE(SUM(v.ventas), 0)`, `COALESCE(SUM(v.total), 0)`, `ROUND(COALESCE(SUM(p.bruto), 0), 2)`. Para el coordinador es "Total del equipo"; para el administrador, "Toda la clínica" |
| `porEquipo(string $mes): Collection` | Una fila por coordinador | Solo administrador. `users s` (`rol = 'supervisor'`, activos y desactivados) `LEFT JOIN users u` (sus asesores) + las dos subconsultas, `GROUP BY s.id, s.name, s.activo`, mismas tres columnas que `totales`. Un coordinador sin asesores sale en 0. Orden por nombre |
| `porTratamiento(User $quien, string $mes): Collection` | Una fila por tratamiento con movimiento | Ver abajo |
| `totalPorTratamiento(User $quien, string $mes): object` | Una fila | La misma consulta de `porTratamiento` sin `GROUP BY` |

**Por tratamiento.** Una sola consulta sobre las líneas de los planes del alcance que son **ganados del mes o abiertos**:

```php
DB::table('oportunidad_productos as l')
    ->join('oportunidades as o', 'o.id', '=', 'l.oportunidad_id')
    ->join('productos as pr', 'pr.id', '=', 'l.producto_id')   // solo para el nombre
    ->whereIn('o.vendedor_id', User::asesoresVisiblesPara($quien)->select('id'))
    ->where(fn ($q) => $q
        ->where(fn ($g) => $g->where('o.etapa', 'ganada')
            ->whereRaw("DATE_FORMAT(o.fecha_cierre - INTERVAL 6 HOUR, '%Y-%m') = ?", [$mes]))
        ->orWhereIn('o.etapa', Oportunidad::ABIERTAS))
    ->groupBy('pr.id', 'pr.nombre')
    ->selectRaw('pr.nombre')
    ->selectRaw("COALESCE(SUM(CASE WHEN o.etapa = 'ganada' THEN l.cantidad END), 0) AS unidades")
    ->selectRaw("COALESCE(SUM(CASE WHEN o.etapa = 'ganada' THEN l.cantidad * l.precio_unitario END), 0) AS total")
    ->selectRaw('ROUND(COALESCE(SUM(l.cantidad * l.precio_unitario * ('.self::PROBABILIDAD.')), 0), 2) AS pronostico')
    ->orderBy('pr.nombre');
```

- Como el `where` ya deja solo las ganadas **de ese mes**, el `CASE` de unidades y total no repite el mes. El `CASE` de probabilidad da 0 a las ganadas, así que el pronóstico solo suma abiertas.
- Solo salen tratamientos con ventas en el mes o con líneas en planes abiertos (activos o desactivados en el catálogo). Ninguna fila queda en 0 · Q0.00 · Q0.00.
- La fila de totales de esta tabla debe coincidir en total vendido y pronóstico con la de `totales()` (los planes sin líneas no suman dinero).

### 2.4 `ReporteController@index`

```php
public function index(Request $request, Reporte $reporte): View
{
    [$mes, $avisoMes] = Mes::elegido($request->query('mes'));
    $quien = $request->user();

    return view('reportes.index', [
        'mes' => $mes,
        'avisoMes' => $avisoMes,
        'esAdmin' => $quien->esAdmin(),
        'asesores' => $reporte->porAsesor($quien, $mes),
        'totales' => $reporte->totales($quien, $mes),
        'equipos' => $quien->esAdmin() ? $reporte->porEquipo($mes) : collect(),
        'tratamientos' => $reporte->porTratamiento($quien, $mes),
        'totalTratamientos' => $reporte->totalPorTratamiento($quien, $mes),
    ]);
}
```

### 2.5 Ruta

En `routes/web.php`, dentro del grupo `auth` + `panel` y del grupo `rol:administrador,supervisor`:

| Método y URL | Nombre | Acción |
|---|---|---|
| `GET /reportes` | `reportes.index` | `ReporteController@index` |

El menú ya tiene "Reporte" para administrador y coordinador y lo muestra en cuanto existe la ruta (spec 03). Un asesor no entra al panel (middleware `panel`).

### 2.6 Vista `reportes/index.blade.php`

Mismo estilo que `equipo/index.blade.php`. Vocabulario dental en pantalla.

1. **Selector:** formulario `GET` a `reportes.index` con `<x-text-input type="month" name="mes" :value="$mes">` y botón "Ver". Si hay `$avisoMes`, el aviso amarillo "Elija un mes válido (AAAA-MM).".
2. **Título:** "Reporte de octubre 2026" (`Formato::mesEnPalabras($mes)`).
3. **Por asesor:** columnas Asesor (con la etiqueta "Desactivado" si aplica), Coordinador (solo administrador), Cartera ("3 de 10"), Ventas del mes, Total vendido, Pronóstico. Fila final en `<tfoot>`: "Total del equipo" (coordinador) o "Toda la clínica" (administrador), con ventas, total y pronóstico; la celda de cartera vacía. Sin asesores: "Su equipo todavía no tiene asesores."
4. **Por equipo (solo administrador):** columnas Coordinador ("Equipo de Sergio Ruiz", con "Desactivado" si aplica), Ventas del mes, Total vendido, Pronóstico. Fila final "Toda la clínica" con los mismos valores de `totales()`.
5. **Por tratamiento:** columnas Tratamiento, Unidades vendidas, Total vendido, Pronóstico. Fila final "Total". Sin filas: "No hay ventas ni planes abiertos en este mes."
6. Montos con `Formato::quetzales()`; todo con `{{ }}`, nunca `{!! !!}`.

---

## 3. Plan de implementación

Cada paso deja `php artisan test` en verde.

1. **`Mes` y refactor de `/equipo`.** Crear `App\Support\Mes`; `EquipoController` usa `Mes::elegido()` y borra `mesElegido()`, `PATRON_MES` y `MES_NO_VALIDO`. `EquipoTest` sigue en verde sin cambios de cifras.
2. **Servicio `Reporte`.** Mover `ventasDelMes()` de `EquipoController` a `Reporte` (pública) y que `/equipo` la use. Agregar `pronosticoAbierto()`, `porAsesor()`, `totales()` y `porEquipo()`. Pruebas del servicio con las cifras de 11.1 (CA1, CA3, CA4, CA5, CA6).
3. **Por tratamiento.** `porTratamiento()` y `totalPorTratamiento()`. Pruebas CA2 y la parte de tratamientos de CA4.
4. **Ruta, controlador y vista.** `ReporteController@index`, ruta `reportes.index`, vista (2.6). Quitar `reportes.index` de `RESERVADAS` en `MenuTest`. Pruebas HTTP CA1–CA7, CA10, CA11, CA12.
5. **Casos que mueven datos.** Pruebas CA8 (cambio de precio), CA9 (prueba central: plan ganado hoy) y CA13 (reasignación de L4).
6. **Cierre.** `php artisan route:list --path=reportes` (una sola ruta GET); `git grep -n "{!!" laravel/resources/views/reportes` vacío; `git grep -n "productos.precio\|pr.precio" laravel/app/Services/Reporte.php` vacío; anotar en `docs/verificacion.md`.

**Pruebas:** contra MySQL `crm_test`, con `VerificacionSeeder` en `setUp()` y `actingAs` de Alicia, Sergio o Sandra, como en los specs anteriores. Las cifras se comparan con el texto formateado en la página (`assertSeeInOrder`) y, en las pruebas del servicio, con el texto DECIMAL que devuelve MySQL (`'9250.00'`, `'2512.88'`), nunca con floats.

---

## 4. Criterios de aceptación

Salen de los Dado/Cuando/Entonces de L6 (reporte), de la sección 11.5 y del criterio de L4. Semilla: `php artisan migrate:fresh --seeder=VerificacionSeeder`.

- **CA1 · Administrador, por asesor y por equipo.** Dado Alicia, cuando abre `/reportes?mes=2026-10`, entonces ve Valeria 3 de 10 · 2 · Q9,250.00 · Q2,512.88; Diego 2 de 10 · 1 · Q6,000.00 · Q112.50; Carlos 9 de 10 · 1 · Q7,200.00 · Q4,125.75; Iván "Desactivado" 1 de 10 · 0 · Q0.00; en la tabla por equipo, Equipo de Sergio Ruiz 3 · Q15,250.00 · Q2,625.38 y Equipo de Sandra Pérez 1 · Q7,200.00 · Q4,125.75; y "Toda la clínica" 4 · Q22,450.00 · Q6,751.13.
- **CA2 · Administrador, por tratamiento.** Dado lo mismo, cuando mira la tabla por tratamiento, entonces ve Plan básico 5 · Q6,000.00; Plan avanzado 2 · Q5,200.00 · pronóstico Q6,188.63; Instalación 1 · Q450.00 · pronóstico Q562.50; Soporte anual 3 · Q10,800.00 (sección 11.2), y la fila "Total" con Q22,450.00 y Q6,751.13.
- **CA3 · El pronóstico no depende del mes.** Dado Alicia, cuando abre `/reportes?mes=2026-11`, entonces Valeria tiene 1 venta, Q3,600.00 y el mismo pronóstico Q2,512.88.
- **CA4 · Coordinador Sergio.** Dado Sergio, cuando abre `/reportes?mes=2026-10`, entonces ve solo a Valeria y Diego (ni Carlos ni Iván), sin columna de coordinador ni tabla por equipo, "Total del equipo" 3 · Q15,250.00 · Q2,625.38, y por tratamiento Plan básico 5 · Q6,000.00, Plan avanzado 2 · Q5,200.00, Instalación 1 · Q450.00 y Soporte anual 1 · Q3,600.00, con fila "Total" de Q15,250.00 y Q2,625.38.
- **CA5 · Coordinadora Sandra.** Dado Sandra, cuando abre `/reportes?mes=2026-10`, entonces ve a Carlos y a Iván "Desactivado" con 0 · Q0.00 · Q0.00, y "Total del equipo" 1 · Q7,200.00 · Q4,125.75.
- **CA6 · Redondeo al final.** Dado el reporte de Alicia de octubre, cuando se comparan las filas, entonces el pronóstico de "Toda la clínica" (Q6,751.13) sale de `ROUND(SUM(...), 2)` sobre todas las líneas abiertas: `Reporte.php` no suma columnas `pronostico` ya redondeadas y ningún pronóstico se calcula en PHP.
- **CA7 · Mes por omisión y mes no válido.** Dado `Carbon::setTestNow('2026-11-01 03:00:00 UTC')` (31 de octubre en Guatemala), cuando Alicia abre `/reportes` sin `mes`, entonces el selector muestra `2026-10` y las cifras de CA1. Cuando abre `/reportes?mes=2026-13`, `?mes=abc` o `?mes[]=2026-10`, entonces responde 200 con el aviso "Elija un mes válido (AAAA-MM)." y el reporte del mes actual de Guatemala.
- **CA8 · Cambiar un precio no cambia el reporte.** Dado Alicia, cuando cambia el precio de Plan avanzado en el catálogo a Q3,000.00 y vuelve a abrir `/reportes?mes=2026-10`, entonces CA1 y CA2 siguen idénticos.
- **CA9 · Prueba central.** Dado un plan de Valeria con líneas que se marca ganado con `fecha_cierre` = ahora (como lo hace la SPA), cuando Alicia abre `/reportes` (mes actual), entonces la fila de Valeria suma esa venta y su total.
- **CA10 · Acceso y menú.** Dado Alicia o Sergio, el menú muestra "Reporte" con enlace a `/reportes`. Un visitante sin sesión es redirigido al inicio de sesión. `php artisan route:list --path=reportes` muestra solo `GET /reportes`.
- **CA11 · Mes como parámetro.** Dado el código, cuando se ejecuta `git grep -n "Raw" laravel/app/Services/Reporte.php`, entonces el mes solo aparece como `?` con su arreglo de parámetros y ninguna expresión concatena datos del usuario.
- **CA12 · XSS.** Dado un asesor con nombre `<b>x</b>`, cuando Alicia abre el reporte, entonces se ve el texto literal; `git grep -n "{!!" laravel/resources/views/reportes` está vacío.
- **CA13 · Reasignación (L4).** Dado Sergio, cuando reasigna Comercial El Quetzal de Valeria a Diego y abre `/reportes?mes=2026-10`, entonces Valeria conserva 2 · Q9,250.00 con pronóstico Q450.00, y Diego queda con pronóstico Q2,175.38 y cartera 3 de 10.
- **CA14 · Pruebas.** `cd laravel && php artisan test` termina en verde, incluidas `ReporteTest`, `EquipoTest` tras el refactor y `MenuTest` ajustada.

---

## 5. Decisiones tomadas y descartadas

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| Tabla por tratamiento con unidades, total **y pronóstico** | Solo unidades y total | L6 lo pide y tiene criterios (Plan avanzado Q6,188.63, Instalación Q562.50). Decidido por la integrante el 2026-11-08 |
| Administrador: tabla por asesor con columna Coordinador (ordenada por equipo) + tabla por equipo aparte, las dos con fila "Toda la clínica" | Una sola tabla con subtotales por equipo intercalados | Decidido por la integrante el 2026-11-08. Cumple "agrupados por coordinador" de L6 y deja las cifras de equipo en una tabla fácil de leer |
| Mes no válido → mes actual de Guatemala con aviso, compartido con `/equipo` en `App\Support\Mes` | `validate()` con error de formulario | Decidido por la integrante el 2026-11-08: mismo comportamiento que el spec 09, sin página de error, y una sola regla para las dos páginas |
| Por tratamiento, solo filas con movimiento (ventas del mes o líneas abiertas) | Todo el catálogo en 0 | Decidido por la integrante el 2026-11-08: la tabla es más corta y sale de la misma consulta |
| Consultas en `App\Services\Reporte` | Todo dentro del controlador | `/equipo` reutiliza `ventasDelMes()` y las cifras se prueban sin HTTP. Ya existe `App\Services\Cartera` como precedente |
| Subconsultas con la suma **sin redondear** y `ROUND` una sola vez por fila | Redondear por asesor y sumar | R7 y la integrante: el pronóstico de un grupo es `ROUND(SUM(...), 2)`, no la suma de redondeados |
| Probabilidad con `CASE` y decimales en SQL | Tabla de probabilidades; multiplicar en PHP con `Oportunidad::PROBABILIDAD` | Sección 11.4: nunca con float en PHP. El `CASE` es una expresión fija sin datos del usuario |
| Alcance con `whereIn('u.id', User::asesoresVisiblesPara($quien)->select('id'))` | Llamar el scope sobre la consulta con `JOIN users s` | El scope usa `rol` y `supervisor_id` sin tabla, que serían ambiguos con el alias `s`; la subconsulta reutiliza la misma regla sin ambigüedad |
| La venta cuenta en el equipo actual del asesor | Guardar el equipo en cada venta | L6 ya lo decide así; el modelo no guarda el equipo de la venta (pregunta abierta de la sección 6, sin cambio aquí) |
| Pruebas comparan texto DECIMAL o texto formateado | Comparar floats | Evita aceptar un cálculo hecho con float |

---

## 6. Riesgos

- **Mismas cifras que la API.** `GET /reportes/resumen` (spec 15, integrante B) debe usar las mismas expresiones (mes con `- INTERVAL 6 HOUR`, precio de la línea, `CASE` con decimales, `ROUND` de la suma). Si difieren, la fila de un asesor en el panel no coincidirá con su `#/resumen`.
- **Refactor de `/equipo`.** Mover `mesElegido()` y `ventasDelMes()` toca `EquipoController`; `EquipoTest` debe seguir en verde sin cambiar cifras.
- **`COUNT(DISTINCT)` y `LEFT JOIN` a líneas.** Si alguien cambia el `JOIN` de ventas a `INNER`, un plan ganado sin líneas dejaría de contar como venta; si quita el `DISTINCT`, un plan con varias líneas contaría varias veces ("Renovación de planes" tiene dos).
- **Asesor que cambia de rol.** Un plan cuyo `vendedor_id` ya no es `vendedor` no aparece en ninguna tabla (el alcance filtra por `rol = 'vendedor'`), así que las tablas siguen cuadrando entre sí. L2 impide ese cambio mientras tenga pacientes, pero no si solo tiene planes cerrados.
- **Línea de `MenuTest`.** Si no se quita `reportes.index` de `RESERVADAS`, la prueba simula una ruta con el mismo nombre encima de la real y oculta errores.
- **Semilla del sector.** Si `SectorSeeder` (spec 17) agrega planes a los asesores de la verificación, CA1–CA5 solo valen con `--seeder=VerificacionSeeder` (pregunta abierta de la sección 11).

---

## Lo que **no** entra en este spec

- API `GET /reportes/resumen` (spec 15) y vista `#/resumen` (spec 22).
- Exportar a CSV.
- Gráficas, comparación de meses o rangos.
- Guardar el equipo de cada venta.
