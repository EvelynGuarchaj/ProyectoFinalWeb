# 09 · Mi equipo

**Estado:** Aprobado
**Depende de:** 01-esquema-y-semilla (tablas `users`, `clientes`, `oportunidades`, `oportunidad_productos`; `VerificacionSeeder`), 03-cuentas-roles (middleware `panel` y `rol`; menú con la entrada reservada `equipo.index`; `MenuTest`), 05-pacientes (`Cliente::visiblePara()` y su 404), 07-asignacion (`Cartera::MAXIMO`)
**Fecha:** 2026-11-08
**Dueña:** A
**Rama:** `spec-09-equipo`
**Objetivo:** que el coordinador vea en `/equipo`, para el mes que elija, la cartera y las ventas de cada asesor de su equipo.

**Requisitos que cumple:** L5 de `docs/especificacion.md` en su parte "Página Mi equipo", con las reglas de R7 para el mes y el total de una venta. La parte de L5 "solo ve a su equipo" (404 a lo ajeno, S5) ya la cumplen los specs 05 y 07 para los pacientes; para los planes la cumple el spec 12. La parte "solo asigna a asesores activos de su equipo" la cumple el spec 07.

---

## 1. Alcance

**Incluye**
- `app/Http/Controllers/EquipoController.php` con la acción `index`.
- Ruta `GET /equipo` (nombre `equipo.index`) con el middleware `rol:supervisor`.
- Vista `resources/views/equipo/index.blade.php`: selector de mes y tabla con una fila por asesor del equipo.
- `Formato::mesEnPalabras()` en `app/Support/Formato.php` ('2026-10' → 'octubre 2026'), reutilizable por el reporte (spec 14).
- Pruebas `tests/Feature/EquipoTest.php` y ajuste de `tests/Feature/MenuTest.php` (deja de simular `equipo.index`).
- Anotar en `docs/verificacion.md` las filas de L5 (página Mi equipo).

**No incluye**
- El correo del asesor en la tabla: L5 pide "nombre y estado". Decidido por la integrante el 2026-11-08.
- Pronóstico, fila de totales del equipo y tabla por tratamiento: son del reporte (spec 14, L6).
- Vista del administrador: recibe **403** en `/equipo` (L5); él ve todo en el reporte.
- Enlaces desde la fila a la cartera o a los planes del asesor.
- Asignar, reasignar o liberar desde esta página (spec 07, en `/clientes`).
- `lang/es/validation.php` (spec 16).

---

## 2. Modelo de datos

**No hay migraciones nuevas.** Se leen `users` (`name`, `rol`, `activo`, `supervisor_id`), `clientes.vendedor_id`, `oportunidades` (`vendedor_id`, `etapa`, `fecha_cierre`) y `oportunidad_productos` (`oportunidad_id`, `cantidad`, `precio_unitario`) del spec 01.

### 2.1 Mes elegido

| Entrada `?mes=` | Mes usado | Aviso |
|---|---|---|
| Ausente o vacío | `now('America/Guatemala')->format('Y-m')` | — |
| Texto que cumple `^\d{4}-(0[1-9]|1[0-2])$` | El valor recibido | — |
| Cualquier otra cosa (`2026-13`, `hola`, `2026-1`, `mes[]=x`) | El mes actual de Guatemala | "Elija un mes válido (AAAA-MM)." sobre la tabla |

El mes válido llega a SQL **siempre como parámetro `?`**; nunca se concatena.

### 2.2 Consulta (constructor de consultas, una sola ida a la base)

Asesores del equipo:
- `User::where('rol', 'vendedor')->where('supervisor_id', $coordinador->id)`, activos y desactivados, `orderBy('name')`.
- `withCount('clientes as cartera')`: todos los pacientes con `vendedor_id` del asesor (Iván, desactivado, cuenta su Hotel Los Pinos).

Ventas del mes, como subconsulta unida con `leftJoinSub(..., 'v', 'v.vendedor_id', '=', 'users.id')`:

```php
DB::table('oportunidades as o')
    ->leftJoin('oportunidad_productos as l', 'l.oportunidad_id', '=', 'o.id')
    ->where('o.etapa', 'ganada')
    ->whereRaw("DATE_FORMAT(o.fecha_cierre - INTERVAL 6 HOUR, '%Y-%m') = ?", [$mes])
    ->groupBy('o.vendedor_id')
    ->selectRaw('o.vendedor_id, COUNT(DISTINCT o.id) AS ventas, SUM(l.cantidad * l.precio_unitario) AS total');
```

- Columnas de la consulta principal: `users.id`, `users.name`, `users.activo`, `COALESCE(v.ventas, 0) AS ventas`, `COALESCE(v.total, 0) AS total` (con `selectRaw` de expresión fija).
- La venta cuenta para el asesor de `oportunidades.vendedor_id` (crédito de L6). Un plan ganado de un paciente ya reasignado o liberado sigue siendo venta de su asesor (María Coyoy, CA2).
- `COUNT(DISTINCT o.id)` con `LEFT JOIN` cuenta una venta aunque tenga varias líneas, y no la pierde si no tuviera ninguna.
- El total sale de MySQL como texto DECIMAL. PHP no lo suma ni lo multiplica: solo `Formato::quetzales()` lo convierte para mostrarlo.

### 2.3 `Formato::mesEnPalabras(string $mes): string`

'2026-10' → 'octubre 2026', con una constante de meses completos (sin `intl` ni `APP_LOCALE`, igual que `MESES`).

### 2.4 Ruta

En `routes/web.php`, dentro del grupo `auth` + `panel`, un grupo nuevo:

| Método y URL | Nombre | Middleware | Acción |
|---|---|---|---|
| `GET /equipo` | `equipo.index` | `rol:supervisor` | `EquipoController@index` |

El menú ya tiene la entrada "Mi equipo" (solo coordinador) y la muestra en cuanto existe la ruta `equipo.index` (spec 03).

### 2.5 Vista `equipo/index.blade.php`

- Encabezado "Mi equipo".
- Formulario `GET` a `equipo.index` con `<input type="month" name="mes">` (valor = mes usado) y botón "Ver".
- Aviso de mes no válido (2.1), si aplica.
- Título de la tabla: "Ventas de {mes en palabras}", por ejemplo "Ventas de octubre 2026".
- Columnas: **Asesor** (nombre; si `activo` es falso, la etiqueta "Desactivado"), **Cartera** ("x de 10", con `Cartera::MAXIMO`), **Ventas del mes** (entero), **Total vendido** (`Formato::quetzales`).
- Equipo sin asesores: "Su equipo todavía no tiene asesores."
- Todo con `{{ }}`, nunca `{!! !!}`.

---

## 3. Plan de implementación

Cada paso deja `php artisan test` en verde.

1. **Formato.** `Formato::mesEnPalabras()` con su prueba (enero, octubre, diciembre).
2. **Ruta, controlador y vista mínima.** `EquipoController@index` con el mes de 2.1 y la lista de asesores con su cartera; vista con selector, aviso y columnas Asesor y Cartera. Quitar `equipo.index` de `RESERVADAS` en `MenuTest`. Pruebas CA4, CA5, CA6, CA7, CA8 y la parte de cartera de CA1 y CA3.
3. **Ventas del mes.** Subconsulta de 2.2 y columnas Ventas y Total vendido. Pruebas CA1, CA2, CA3 completas y CA9.
4. **Cierre.** `php artisan route:list --path=equipo`; `git grep -n "{!!" laravel/resources/views/equipo` vacío; anotar en `docs/verificacion.md`.

**Pruebas:** contra MySQL `crm_test`, con `VerificacionSeeder` en `setUp()` y `actingAs` de Alicia, Sergio o Sandra, como en los specs anteriores. El mes por omisión se prueba con `Carbon::setTestNow()`.

---

## 4. Criterios de aceptación

Salen de los Dado/Cuando/Entonces de L5. Semilla: `php artisan migrate:fresh --seeder=VerificacionSeeder`.

- **CA1 · Equipo de Sergio, octubre.** Dado Sergio, cuando abre `/equipo?mes=2026-10`, entonces ve "Ventas de octubre 2026", a Diego Castillo con "2 de 10", 1 venta y Q6,000.00, y a Valeria López con "3 de 10", 2 ventas y Q9,250.00, en ese orden; no ve a Carlos Ajú ni a Iván Toj.
- **CA2 · Equipo de Sergio, noviembre.** Dado Sergio, cuando abre `/equipo?mes=2026-11`, entonces Valeria tiene 1 venta y Q3,600.00 ("Equipo para sucursal", cerrada el `2026-11-01 03:30:00` UTC, cuenta en noviembre y no en octubre), y Diego 0 y Q0.00.
- **CA3 · Equipo de Sandra con desactivado.** Dado Sandra, cuando abre `/equipo?mes=2026-10`, entonces ve a Carlos Ajú con "9 de 10", 1 venta y Q7,200.00, y a Iván Toj con la etiqueta "Desactivado", "1 de 10", 0 ventas y Q0.00.
- **CA4 · Administrador.** Dado Alicia, cuando abre `/equipo`, entonces el panel responde **403**.
- **CA5 · Mes por omisión en hora de Guatemala.** Dado el reloj en `2026-11-01 03:30:00` UTC, cuando Sergio abre `/equipo` sin `mes`, entonces ve "Ventas de octubre 2026" y el selector con `2026-10`; dado el reloj en `2026-11-01 06:30:00` UTC, ve "Ventas de noviembre 2026".
- **CA6 · Mes no válido.** Dado Sergio, cuando abre `/equipo?mes=2026-13`, `/equipo?mes=hola` o `/equipo?mes[]=2026-10`, entonces ve "Elija un mes válido (AAAA-MM).", la tabla del mes actual de Guatemala y responde 200 (nunca una página de error).
- **CA7 · Menú.** Dado Sergio, entonces el menú muestra "Mi equipo" con enlace a `/equipo`; dado Alicia, no lo muestra.
- **CA8 · Sin sesión.** Dado un visitante sin sesión, cuando abre `/equipo`, entonces se le redirige al inicio de sesión.
- **CA9 · XSS.** Dado un asesor del equipo de Sergio con nombre `<img src=x onerror=alert(1)>`, cuando Sergio abre `/equipo`, entonces se ve el texto literal; `git grep -n "{!!" laravel/resources/views/equipo` está vacío.
- **CA10 · Lo ajeno sigue dando 404.** Dado Sergio, cuando abre a mano `/clientes/{Abarrotes San Juan}`, entonces el panel responde **404** (ya lo prueba `ClientesTest`; el de un plan de Sandra lo prueba el spec 12).
- **CA11 · Pruebas.** `cd laravel && php artisan test` termina en verde, incluidas `EquipoTest` y `MenuTest` ajustada.

---

## 5. Decisiones tomadas y descartadas

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| Solo nombre, estado, cartera, ventas y total | Agregar el correo | Decidido por la integrante el 2026-11-08: L5 no lo pide y no se programa nada que no esté en la especificación |
| Ruta con `rol:supervisor` (403 para el administrador) | Mostrar todos los equipos al administrador | L5 lo fija: el administrador ve todo en el reporte |
| Ventas en la base con el constructor de consultas y `whereRaw` de expresión fija con el mes como `?` | Traer los planes y filtrar o sumar en PHP | Regla del panel (Eloquent o constructor) y de R7: el dinero no pasa por float y el mes se agrupa igual que en la API |
| `DATE_FORMAT(fecha_cierre - INTERVAL 6 HOUR, '%Y-%m')` | `CONVERT_TZ` o calcular en PHP | Es la expresión común de CLAUDE.md; da el mismo resultado que la API sin tablas de zonas horarias |
| Mes por omisión con `now('America/Guatemala')` | `now()->subHours(6)` | Decidido por la integrante. Guatemala no tiene horario de verano, así que coincide con UTC−6 fijo |
| Mes no válido → mes actual con aviso "Elija un mes válido (AAAA-MM)." | Ignorarlo sin aviso; error de validación con redirección | Decidido por la integrante: la persona sabe que su mes no se usó y nunca ve una página de error |
| `<input type="month">` con botón "Ver" | `<select>` con los últimos 12 meses | Decidido por la integrante: permite cualquier mes y envía AAAA-MM directo |
| Asesores por nombre, activos y desactivados mezclados; título "Ventas de octubre 2026" | Activos primero | Decidido por la integrante |
| Una consulta con `withCount` + `leftJoinSub` | Una consulta por asesor | Evita N+1 y deja todo el cálculo en MySQL |
| `LEFT JOIN` a las líneas y `COUNT(DISTINCT o.id)` | `JOIN` y `COUNT(*)` | `COUNT(*)` contaría líneas, no ventas; el `JOIN` perdería una venta sin líneas |
| Crédito al `oportunidades.vendedor_id` | Al asesor actual del paciente | Regla de L6 y sección 5: los planes cerrados conservan su asesor al reasignar o liberar |
| `Formato::mesEnPalabras()` en `Formato` | Formatear el mes en la vista | El reporte (spec 14) lo necesita igual; un solo lugar sin depender de `intl` |

---

## 6. Riesgos

- **Mismas cifras que el reporte y la API.** Si el spec 14 o `GET /resumen` (spec 15, integrante B) usan otra expresión para el mes o el total, las cifras de Valeria (Q9,250.00 en octubre) podrían no coincidir entre páginas. Los dos deben copiar la expresión de 2.2.
- **Línea de `MenuTest`.** Si no se quita `equipo.index` de `RESERVADAS`, la prueba registra una ruta simulada con el mismo nombre encima de la real y oculta errores.
- **Reloj de la prueba.** CA5 depende de que el controlador use `now('America/Guatemala')` y no la hora del sistema; con `Carbon::setTestNow()` se controla.

---

## Lo que **no** entra en este spec

- Correo del asesor en la tabla.
- Pronóstico, totales del equipo y tabla por tratamiento (spec 14).
- Página del equipo para el administrador.
- Acciones sobre pacientes o planes desde esta página.
