# 04 · Catálogo de tratamientos

**Estado:** Implementado
**Depende de:** 01-esquema-y-semilla (tabla `productos`, `chk_productos_precio`, columna `requiere_evaluacion`, modelo `Producto` y `VerificacionSeeder`), 03-cuentas-roles (middleware `panel` y `rol`, nombre de ruta reservado `productos.index` para el menú)
**Fecha:** 2026-11-08
**Dueña:** A
**Rama:** `spec-04-catalogo`
**Objetivo:** que el administrador mantenga el catálogo de tratamientos desde el panel (crear, editar, activar y desactivar) sin poder borrarlos.

**Requisitos que cumple:** L3 de `docs/especificacion.md` y R-P9 (sección 2.6, la casilla "Requiere evaluación" que solo marca el administrador). Usa la fila `productos` de la tabla de validaciones (sección 3.3) y el formato de montos y fechas de la sección 6 de la especificación (reglas comunes del panel, "Fechas en pantalla").

---

## 1. Alcance

**Incluye**
- `ProductoController` (index, create, store, edit, update y `cambiarEstado`), solo para el administrador. Sin `show` ni `destroy`.
- `ProductoRequest` con las reglas y mensajes de la sección 2.4.
- Vistas `resources/views/productos/` (`index`, `create`, `edit`, `form`).
- `app/Support/Formato.php` con `quetzales($monto)` y `fechaGT($fecha)`, usada en todas las vistas del panel que muestren montos o fechas (en este spec: la lista de tratamientos).
- `$fillable` en el modelo `Producto`.
- Pruebas `tests/Feature/ProductosTest.php` y `tests/Unit/FormatoTest.php`.
- Anotar la comprobación manual de R-P9 en `docs/verificacion.md`.

**No incluye**
- `GET /productos` de la API (spec 11, integrante B). Aquí solo se garantiza que `activo = 0` queda en la base; que la API no devuelva los desactivados lo cumple el spec 11.
- Impedir agregar un tratamiento desactivado a un plan (specs 11 y 12).
- La regla propia que lee `requiere_evaluacion` (spec 13) y marcar tratamientos en la semilla del sector (spec 17).
- Observers, eventos o cualquier código que toque `oportunidad_productos` al cambiar un precio o desactivar un tratamiento.
- Búsqueda, filtros o paginación en la lista.
- `lang/es/validation.php` y `APP_LOCALE=es` (spec 16). Los mensajes van en `messages()` de `ProductoRequest`.
- Cambiar las vistas de `cuentas/`: no muestran montos ni fechas, así que no usan `Formato` todavía.

---

## 2. Modelo de datos

**No hay migraciones nuevas.** Se usa la tabla `productos` del spec 01: `nombre VARCHAR(120) UNIQUE`, `precio DECIMAL(10,2)` con `chk_productos_precio (precio > 0)`, `activo BOOLEAN DEFAULT TRUE` y `requiere_evaluacion BOOLEAN DEFAULT FALSE`. El modelo `Producto` ya tiene los casts `precio => decimal:2`, `activo => boolean` y `requiere_evaluacion => boolean`.

Único cambio al modelo:

```php
// app/Models/Producto.php
protected $fillable = ['nombre', 'precio', 'activo', 'requiere_evaluacion'];
```

### 2.1 Rutas

Dentro del grupo `auth`, `panel`, `rol:administrador` de `routes/web.php` (el mismo de cuentas):

| Método y URL | Nombre | Acción |
|---|---|---|
| `GET /productos` | `productos.index` | Lista |
| `GET /productos/create` | `productos.create` | Formulario vacío |
| `POST /productos` | `productos.store` | Crea |
| `GET /productos/{producto}/edit` | `productos.edit` | Formulario con datos |
| `PUT/PATCH /productos/{producto}` | `productos.update` | Actualiza |
| `PATCH /productos/{producto}/estado` | `productos.estado` | Invierte `activo` |

```php
Route::resource('productos', ProductoController::class)->except(['show', 'destroy']);
Route::patch('productos/{producto}/estado', [ProductoController::class, 'cambiarEstado'])
    ->name('productos.estado');
```

No existe `DELETE /productos/{producto}`: Laravel responde 405 y nada se borra. El coordinador recibe 403 en todas (middleware `rol`). Como `productos.index` es el nombre reservado en el spec 03, la entrada "Tratamientos" aparece sola en el menú del administrador.

### 2.2 `app/Support/Formato.php`

Clase final con dos métodos estáticos. No depende de la configuración regional del servidor ni de la extensión `intl`.

| Método | Entrada | Salida |
|---|---|---|
| `quetzales($monto)` | `string`, `int`, `float` o `null` (el cast `decimal:2` entrega `string`) | `"Q"` + `number_format($monto, 2, '.', ',')`. Ejemplos: `'1200.00'` → `Q1,200.00`; `9250` → `Q9,250.00`; `'2512.88'` → `Q2,512.88`; `0` o `null` → `Q0.00`; `'99999999.99'` → `Q99,999,999.99` |
| `fechaGT($fecha)` | `DateTimeInterface`, cadena en UTC o `null` | La fecha en hora de Guatemala (UTC−6 fijo) con el formato de la especificación: `j M Y, H:i` con el mes abreviado en español de una tabla propia (`ene feb mar abr may jun jul ago sep oct nov dic`). Ejemplos: `2026-11-01 03:30:00` UTC → `31 oct 2026, 21:30`; `2026-10-05 16:00:00` UTC → `5 oct 2026, 10:00`. `null` → `—` |

La conversión se hace con `CarbonImmutable::parse($fecha, 'UTC')->setTimezone('-06:00')`, igual que la SPA (resta 6 horas fijas). En las vistas se importa con `@use('App\Support\Formato')` y se usa dentro de `{{ }}`.

### 2.3 `ProductoController`

- `index`: `Producto::orderBy('nombre')->get()`, activos y desactivados, sin paginación. Columnas: Nombre, Precio (`Formato::quetzales`), Requiere evaluación ("Sí" / "No"), Estado ("Activo" / "Desactivado"), y en cada fila el enlace "Editar" y un formulario `PATCH` a `productos.estado` con el botón "Desactivar" (si está activo) o "Activar" (si no). Sin confirmación. Botón "Nuevo tratamiento". Título de la página: "Tratamientos". Mensaje de éxito en `session('status')`.
- `create` / `edit`: la misma vista `productos/form.blade.php`. Al crear, "Activo" viene marcado y "Requiere evaluación" desmarcado. Al editar, el precio se muestra tal cual está en la base (`2750.50`), sin `Q` ni comas.
- `store`: `Producto::create($request->validated())`. Redirige a `productos.index` con "El tratamiento se creó correctamente.".
- `update`: `$producto->update($request->validated())`. Redirige con "El tratamiento se actualizó correctamente.". No toca `oportunidad_productos`.
- `cambiarEstado`: `$producto->update(['activo' => ! $producto->activo])`. Redirige a `productos.index` con "El tratamiento se desactivó." o "El tratamiento se activó.".
- **Repetido por carrera:** `store` y `update` capturan `QueryException` con código de error `1062` y vuelven con `withInput()` y el error "Ya existe un tratamiento con ese nombre." junto a `nombre`, nunca con una página de error (el índice único es el árbitro final).

### 2.4 `ProductoRequest`

`authorize()` devuelve `true` (la ruta ya pasa por `rol:administrador`). `$producto = $this->route('producto')` es `null` al crear. Cada regla lleva `bail` para mostrar un solo mensaje por campo.

| Campo | Reglas | Mensajes |
|---|---|---|
| `nombre` | `bail`, `required`, `string`, `max:120`, `Rule::unique('productos', 'nombre')->ignore($producto)` | "Escriba el nombre del tratamiento." / "El nombre no puede pasar de 120 caracteres." / "Ya existe un tratamiento con ese nombre." |
| `precio` | `bail`, `required`, `numeric`, `gt:0`, `decimal:0,2`, `max:99999999.99` | "Escriba el precio." / "Escriba el precio como un número, por ejemplo 150.00." / "El precio debe ser mayor que cero." / "El precio puede tener como máximo dos decimales." / "El precio no puede pasar de Q99,999,999.99." |
| `activo` | `required`, `boolean` | "Indique si el tratamiento está activo." |
| `requiere_evaluacion` | `required`, `boolean` | "Indique si el tratamiento requiere evaluación." |

**Casillas:** en el formulario, cada casilla va precedida de `<input type="hidden" name="..." value="0">` y la casilla envía `value="1"`. Así el campo siempre llega (`0` si está desmarcada) y `required|boolean` basta. Con `old()`, la casilla se marca si `old('activo', $producto->activo ?? true)` vale `'1'`/`true`.

### 2.5 Vistas

| Archivo | Contenido |
|---|---|
| `resources/views/productos/index.blade.php` | Tabla de la sección 2.3 |
| `resources/views/productos/create.blade.php`, `edit.blade.php` | Título ("Nuevo tratamiento" / "Editar tratamiento") e `@include('productos.form')` |
| `resources/views/productos/form.blade.php` | Nombre, Precio (con prefijo "Q" visual, `inputmode="decimal"`), casillas "Activo" y "Requiere evaluación" con texto de ayuda "Para poner un plan con este tratamiento en propuesta o más adelante, el paciente necesita una cita de valoración completada.", botones "Guardar" y "Cancelar". `old()` en cada campo y `@error` junto a cada uno |

Todo con `{{ }}`, nunca `{!! !!}`. Siguen el estilo de `cuentas/` (componentes de Breeze y Tailwind).

---

## 3. Plan de implementación

Cada paso deja el panel funcionando y `php artisan test` en verde.

1. **Formato.** `app/Support/Formato.php` y `tests/Unit/FormatoTest.php` con los ejemplos de la sección 2.2.
2. **Lista.** `$fillable` en `Producto`, rutas `index` (con `rol:administrador`), `ProductoController@index` y `productos/index.blade.php` con `Formato::quetzales`. Pruebas: el administrador ve los 5 tratamientos de la semilla ordenados por nombre, con "Licencia anterior" desactivado; el coordinador recibe 403; el menú del administrador muestra "Tratamientos".
3. **Alta.** `ProductoRequest`, `create`, `store`, `form.blade.php` y `create.blade.php`. Pruebas de CA1 a CA4.
4. **Edición.** `edit`, `update`, `edit.blade.php` y la captura del 1062. Pruebas de CA5, CA6 y CA9.
5. **Activar y desactivar.** Ruta `productos.estado`, `cambiarEstado` y botones de la tabla. Pruebas de CA7 y CA8.
6. **Cierre.** `php artisan route:list --path=productos` sin DELETE; `git grep -n "{!!" laravel/resources/views/productos` vacío; `git grep -n "Observer\|::updated\|::saved" laravel/app` sin nada nuevo sobre `Producto`; comprobar R-P9 a mano y anotarlo en `docs/verificacion.md`.

**Pruebas:** contra MySQL `crm_test` como en los specs 01 y 03, con `VerificacionSeeder` en `setUp()` y la sesión de Alicia o Sergio con `actingAs` (no hace falta la contraseña).

---

## 4. Criterios de aceptación

Salen de los Dado/Cuando/Entonces de L3 y de R-P9. Semilla: `php artisan migrate:fresh --seeder=VerificacionSeeder`.

- **CA1 · Alta.** Dado Alicia con sesión, cuando agrega "Sellante dental" a `150` con "Activo" marcado y "Requiere evaluación" desmarcado, entonces aparece en `/productos` como "Activo", "Requiere evaluación: No" y precio `Q150.00`, y en la base `precio = 150.00`, `activo = 1` y `requiere_evaluacion = 0`.
- **CA2 · Precio inválido.** Dado el formulario de tratamiento, cuando se envía con precio `0`, `-5`, `12.345`, `abc`, vacío o `100000000`, entonces vuelve con lo escrito, un solo mensaje en español junto al precio (sección 2.4), y la tabla `productos` no cambia.
- **CA3 · Nombre repetido.** Dado el formulario de alta, cuando se envía con el nombre "Plan básico" (ya existe), entonces vuelve con "Ya existe un tratamiento con ese nombre." junto al nombre y no se guarda nada.
- **CA4 · Nombre obligatorio y largo.** Dado el formulario, cuando el nombre va vacío o tiene 121 caracteres, entonces aparece el mensaje correspondiente junto al nombre.
- **CA5 · Editar sin cambiar el nombre.** Dado Alicia editando "Plan básico", cuando cambia solo el precio y guarda, entonces se guarda sin el error de nombre repetido.
- **CA6 · Cambiar un precio no cambia el pasado.** Dado Alicia, cuando cambia el precio de "Plan avanzado" a `3000.00`, entonces la lista muestra `Q3,000.00`, las líneas de "Renovación de planes" siguen siendo 2 × `2600.00` y 1 × `450.00` (total `5650.00` en SQL), y las cifras de control de la sección 11 no cambian (Valeria, octubre 2026: 2 ventas, Q9,250.00, pronóstico Q2,512.88; empresa: 4 ventas, Q22,450.00, pronóstico Q6,751.13).
- **CA7 · Desactivar.** Dado Alicia, cuando pulsa "Desactivar" en "Instalación", entonces la fila muestra "Desactivado" y el botón "Activar", en la base `activo = 0`, `SELECT ... FROM productos WHERE activo = 1` ya no la incluye (lo que usará `GET /productos` del spec 11), y el plan "Capacitación de personal" sigue con su línea de Instalación 2 × `450.00`. Al pulsar "Activar", vuelve a `activo = 1`.
- **CA8 · No se borran.** Dado el panel, no hay botón ni ruta para borrar tratamientos (`php artisan route:list --path=productos` no muestra DELETE) y un `DELETE /productos/{id}` enviado a mano responde 405 sin borrar nada.
- **CA9 · Requiere evaluación (R-P9).** Dado Alicia editando un tratamiento, cuando marca "Requiere evaluación" y guarda, entonces en la base queda `requiere_evaluacion = 1` y la lista muestra "Sí"; al desmarcarla vuelve a `0`.
- **CA10 · Solo el administrador.** Dado Sergio con sesión, cuando abre a mano `/productos`, `/productos/create` o `/productos/{id}/edit`, o envía `POST /productos`, `PUT /productos/{id}` o `PATCH /productos/{id}/estado`, entonces el panel responde **403**, la base no cambia y su menú no muestra "Tratamientos". (Es el 403 que el spec 03 dejó pendiente.)
- **CA11 · Formato.** `FormatoTest` pasa con los ejemplos de la sección 2.2 (`Q1,200.00`, `Q9,250.00`, `Q0.00`, `31 oct 2026, 21:30`, `5 oct 2026, 10:00`, `—`).
- **CA12 · XSS.** Dado un tratamiento creado con nombre `<b>x</b>`, cuando se abre `/productos`, entonces se ve el texto literal (`git grep -n "{!!" laravel/resources/views/productos` vacío).
- **CA13 · Pruebas.** `cd laravel && php artisan test` termina en verde, incluidas `ProductosTest` y `FormatoTest`.

---

## 5. Decisiones tomadas y descartadas

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| `fechaGT` con el formato `31 oct 2026, 21:30` y UTC−6 fijo | `d/m/Y H:i` en `America/Guatemala` (propuesta inicial de la integrante) | La especificación (sección 6, "Fechas en pantalla") pide ese formato, igual que la SPA. Elegido por la integrante para no cambiarla. Guatemala no tiene horario de verano, así que UTC−6 fijo da la misma hora |
| Meses de una tabla propia en `Formato` | `Carbon::translatedFormat` o `IntlDateFormatter` | No depende de `APP_LOCALE` (spec 16) ni de la extensión `intl`, y da exactamente las mismas abreviaturas que `util.js` |
| Input oculto con `0` antes de cada casilla | `prepareForValidation` con `$this->boolean()` | El campo siempre llega y `required|boolean` basta; un campo faltante no se convierte en `false` sin avisar (decidido por la integrante) |
| Casillas para `activo` y `requiere_evaluacion` | Selector Activo/Desactivado como en cuentas | Lo pidió la integrante; el input oculto resuelve el problema por el que cuentas usa selector |
| Botón "Activar"/"Desactivar" en cada fila, `PATCH /productos/{producto}/estado`, sin confirmar | Con `confirm()`; o solo en el formulario de edición | Es reversible con un clic y no borra nada (decidido por la integrante). `activo` también se puede cambiar desde el formulario |
| Sin `destroy` ni ruta `DELETE` | Borrado lógico con `SoftDeletes` | L3: no se borran, se desactivan; `activo` ya cumple esa función y la API lo filtra |
| Ningún observer ni evento sobre `Producto` | Recalcular las líneas al cambiar el precio | Las líneas guardan su `precio_unitario` (sección 3.6); cambiar el pasado rompería las cifras de control |
| `decimal:0,2` y `max:99999999.99` con `bail` | Solo `numeric|gt:0` y dejar que el CHECK o MySQL corten | Un `12.345` se redondearía en silencio y un precio enorme daría error de base. Con `bail` sale un solo mensaje por campo |
| Capturar el error 1062 y mostrarlo junto al nombre | Confiar solo en la regla `unique` | Regla técnica compartida: el índice único es el árbitro final y el panel muestra el error junto al campo |
| `unique` con la intercalación de la base (`utf8mb4_unicode_ci`) | Comparación exacta en PHP | La regla de Laravel consulta MySQL, así que coincide con el índice: "plan basico" cuenta como repetido de "Plan básico" |
| Sin paginación ni búsqueda | Paginar | Son pocos tratamientos (5 en la semilla de verificación) |
| `Formato` como clase estática con `@use` en Blade | Helpers globales en `composer.json` o un alias en `config/app.php` | `docs/plan.md` ya la ubica en `app/Support/`; `@use` no toca la configuración y se ve de dónde viene |

---

## 6. Riesgos

- **`GET /productos` depende del spec 11.** CA7 solo comprueba la base. Cuando B implemente el spec 11, hay que repetir la parte de CA7 con la API (`GET /productos` sin "Instalación").
- **Precio con coma decimal.** Si alguien escribe `1,200.00`, `numeric` lo rechaza con "Escriba el precio como un número, por ejemplo 150.00.". Es lo esperado; el ejemplo del mensaje orienta.
- **`Formato` y la SPA deben coincidir.** Si A cambia `quetzales` o `fechaGT` de `util.js` (spec 10), debe cambiar `Formato.php` igual. `FormatoTest` usa los mismos ejemplos de la especificación para que una diferencia se note.
- **Desactivar un tratamiento con planes abiertos.** Las líneas existentes no cambian (L3). Lo que el asesor pueda hacer con esa línea después (cambiar cantidad, quitarla) lo deciden los specs 11 y 12.
