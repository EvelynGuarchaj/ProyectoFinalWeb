# 07 · Asignación de pacientes

**Estado:** Aprobado
**Depende de:** 01-esquema-y-semilla (columna `clientes.vendedor_id`, `Oportunidad::ABIERTAS`, `VerificacionSeeder`), 03-cuentas-roles (middleware `panel` y `rol`; `User::esAdmin()`, `esSupervisor()`), 05-pacientes (`ClienteController`, `ClienteRequest`, `Cliente::visiblePara()`, vistas `clientes/` y `ClientesTest`)
**Fecha:** 2026-11-08
**Dueña:** A
**Rama:** `spec-07-asignacion`
**Objetivo:** que el administrador y el coordinador asignen, reasignen y liberen pacientes (también al crearlos) respetando la cartera máxima de 10 aunque el panel y la API operen a la vez.

**Requisitos que cumple:** L4 de `docs/especificacion.md` en su parte 2 (asignar, reasignar, liberar y asesor al crear), la parte de L5 "solo asigna a asesores activos de su equipo", la fila "máximo 10" y "vendedor activo" de la sección 3.10, las decisiones de la sección 5 sobre reasignar, liberar y asesor desactivado, la parte del panel de S5 (lo ajeno responde 404) y de S8 (cartera máxima con el mismo candado que la API).

---

## 1. Alcance

**Incluye**
- `app/Services/Cartera.php` con `asignar()`, `liberar()` y `crear()` (alta con asesor), todas con el candado de la sección 3.10.
- `app/Exceptions/CarteraException.php` (mensaje y campo opcional).
- `app/Http/Requests/AsignarRequest.php` (campo `vendedor_id`).
- Campo opcional `vendedor_id` en `ClienteRequest` **solo al crear**.
- `ClienteController`: acciones nuevas `asignar` y `liberar`; `store` pasa a usar `Cartera::crear()`; `index`, `show` y `create` reciben los asesores que la persona puede usar con su cartera.
- Rutas `PATCH clientes/{cliente}/asignar` y `PATCH clientes/{cliente}/liberar`.
- Vistas: selector de asesor con "x de 10" y botón "Liberar" en `clientes/index` y `clientes/show`; selector "Asesor" en `clientes/form` (solo alta).
- Constante `Cartera::MAXIMO = 10`.
- Pruebas `tests/Feature/AsignacionTest.php` y ajuste de CA11 en `tests/Feature/ClientesTest.php`.
- Anotar en `docs/verificacion.md` las filas de L4 (parte 2) y la prueba manual del candado.

**No incluye**
- La página "Mi equipo" con "x de 10" y ventas del mes (spec 09). Este spec solo muestra "x de 10" en el selector.
- El reporte de ventas y pronóstico (spec 14). Las cifras de reporte de los criterios se comprueban con SQL.
- `POST /clientes/:id/tomar` y `POST /clientes` de la API (spec 06, integrante B). Este spec no toca `api/`; fija el candado que las dos puertas comparten.
- Cambiar el asesor desde el formulario de **edición**: editar sigue sin tocar `vendedor_id` (L4, "Quién edita").
- `lang/es/validation.php` (spec 16). Los mensajes van en `messages()` y en `CarteraException`.
- Mover las actividades: siguen al paciente sin cambios (sección 5).

---

## 2. Modelo de datos

**No hay migraciones nuevas.** Se usan `clientes.vendedor_id` (nulo = disponible), `oportunidades.vendedor_id`, `oportunidades.etapa` y `users` (`rol`, `activo`, `supervisor_id`) del spec 01. `vendedor_id` sigue fuera de `$fillable` de `Cliente`: solo `Cartera` lo escribe, con `forceFill()`.

### 2.1 `app/Exceptions/CarteraException.php`

```php
final class CarteraException extends \RuntimeException
{
    public function __construct(string $mensaje, public readonly ?string $campo = null)
    {
        parent::__construct($mensaje);
    }
}
```

| Mensaje (textos exactos de L4) | `campo` | Dónde se muestra |
|---|---|---|
| "Elija un asesor activo de su equipo." | `vendedor_id` | Junto al selector |
| "Ese asesor ya tiene 10 pacientes, el máximo permitido." | `vendedor_id` | Junto al selector |
| "No se puede liberar: el paciente tiene planes de tratamiento abiertos. Ciérrelos o reasigne al paciente." | `null` | Mensaje de página (`session('error')`) |

### 2.2 `app/Services/Cartera.php`

Clase con métodos de instancia (se inyecta en el controlador). `public const MAXIMO = 10;`

**Candado común** (método privado `bloquearAsesor(int $vendedorId, User $actor): User`), siempre el primero de la transacción:
1. `User::whereKey($vendedorId)->lockForUpdate()->first()` → `SELECT … FROM users WHERE id = ? FOR UPDATE`, la misma fila que bloquea la API.
2. Si no existe, no es `vendedor`, no está `activo`, o el actor es coordinador y `supervisor_id !== $actor->id` → `CarteraException('Elija un asesor activo de su equipo.', 'vendedor_id')`. (Se repite aquí lo que ya valida el FormRequest porque la cuenta pudo desactivarse o cambiar de equipo entre la validación y el candado.)
3. Devuelve el asesor bloqueado.

**Visibilidad del paciente tras el candado** (método privado `bloquearPaciente(int $clienteId, User $actor): Cliente`):
1. `Cliente::whereKey($clienteId)->lockForUpdate()->firstOrFail()` (se bloquea por id, sin el subquery de `visiblePara`, para leer el `vendedor_id` más reciente).
2. Si el actor es coordinador y el paciente tiene asesor cuyo `supervisor_id` no es el actor → `ModelNotFoundException` (**404**, S5). Esto cubre el caso en que la API lo tomó para otro equipo mientras se esperaba el candado.

| Método | Pasos dentro de `DB::transaction` (orden `users` → `clientes` → `oportunidades`) |
|---|---|
| `asignar(Cliente $cliente, User $vendedor, User $actor): void` | 1) `bloquearAsesor($vendedor->id, $actor)`. 2) `bloquearPaciente($cliente->id, $actor)`. 3) Si el paciente ya es de ese asesor → no hace nada (sin contar ni tocar planes). 4) `Cliente::where('vendedor_id', $vendedor->id)->count() >= MAXIMO` → `CarteraException('Ese asesor ya tiene 10 pacientes, el máximo permitido.', 'vendedor_id')`. 5) `forceFill(['vendedor_id' => $vendedor->id])->save()`. 6) `Oportunidad::where('cliente_id', …)->whereIn('etapa', Oportunidad::ABIERTAS)->update(['vendedor_id' => $vendedor->id])`; las cerradas no se tocan. Sirve para asignar un disponible y para reasignar. |
| `liberar(Cliente $cliente, User $actor): void` | 1) `bloquearPaciente($cliente->id, $actor)` (no hay asesor de destino que bloquear). 2) Si ya está disponible → no hace nada. 3) Si `oportunidades()->whereIn('etapa', ABIERTAS)->lockForUpdate()->exists()` → `CarteraException` de liberar (sin campo). 4) `forceFill(['vendedor_id' => null])->save()`. Los planes cerrados conservan su asesor. |
| `crear(array $datos, ?User $vendedor, User $actor): Cliente` | Sin asesor: `Cliente::create($datos)` (igual que el spec 05). Con asesor: 1) `bloquearAsesor`. 2) Contar (≥ 10 → `CarteraException` de cartera llena). 3) `(new Cliente($datos))->forceFill(['vendedor_id' => …])->save()`. El `INSERT` en `clientes` va **después** del candado de `users`, para respetar el orden. |

Las tres lanzan `CarteraException` o `ModelNotFoundException`; un 1062 de NIT o teléfono en `crear()` sale como `QueryException` y lo atrapa `repetidoPorCarrera()` del spec 05. Una excepción revierte toda la transacción.

### 2.3 `AsignarRequest`

- `authorize()` → `true`.
- `prepareForValidation()`: `Cliente::visiblePara($this->user())->findOrFail($this->route('cliente'))` (lo ajeno da 404 antes de validar, igual que `ClienteRequest`) y fija `$this->errorBag = 'asignar'.$id`, para que el error salga solo en la fila de ese paciente.
- `vendedor_id`: `bail`, `required`, `integer`, `Rule::exists('users', 'id')->where('rol', 'vendedor')->where('activo', 1)` y, si el usuario es coordinador, `->where('supervisor_id', $usuario->id)`. **Todos** los mensajes: "Elija un asesor activo de su equipo.".

### 2.4 Cambios en `ClienteRequest`

- Regla nueva solo cuando la ruta **no** trae `{cliente}` (alta): `vendedor_id` → `bail`, `nullable`, `integer`, el mismo `Rule::exists` de 2.3, mensaje "Elija un asesor activo de su equipo.". Vacío = disponible.
- Al editar no hay regla para `vendedor_id`, así que `validated()` no lo trae y `update` lo ignora.
- `datos()` no cambia (no incluye `vendedor_id`).

### 2.5 Rutas

Dentro del grupo `rol:administrador,supervisor` de `routes/web.php`:

| Método y URL | Nombre | Acción |
|---|---|---|
| `PATCH /clientes/{cliente}/asignar` | `clientes.asignar` | `ClienteController@asignar` (`AsignarRequest`) |
| `PATCH /clientes/{cliente}/liberar` | `clientes.liberar` | `ClienteController@liberar` |

### 2.6 `ClienteController`

- **Asesores que la persona puede usar** (método privado `asesoresAsignables(User $u)`): `User::where('rol','vendedor')->where('activo', 1)`, para el coordinador además `supervisor_id = $u->id`, `withCount('clientes')`, ordenados por `name`. Iván (desactivado) nunca aparece. Se pasa a `index`, `show` y `create`.
- **`asignar(AsignarRequest $request, Cartera $cartera, string $cliente)`**: busca con `visiblePara(...)->findOrFail()`, carga el asesor elegido y llama a `asignar()`. Éxito → `back()->with('status', 'El paciente se asignó correctamente.')`. `CarteraException` con campo → `back()->withErrors([$e->campo => $e->getMessage()], 'asignar'.$id)`.
- **`liberar(Request $request, Cartera $cartera, string $cliente)`**: busca con `visiblePara`, llama a `liberar()`. Éxito → `back()->with('status', 'El paciente quedó disponible.')`. `CarteraException` sin campo → `back()->with('error', $e->getMessage())`.
- **`store`**: `$cartera->crear($request->datos(), $vendedor, $request->user())`, donde `$vendedor` es `User::find($request->validated('vendedor_id'))` o `null`. `CarteraException` → `back()->withInput()->withErrors(['vendedor_id' => …])`. El 1062 sigue con `repetidoPorCarrera()`.

### 2.7 Vistas

| Archivo | Cambio |
|---|---|
| `clientes/index.blade.php` | Columna "Acciones" en cada fila: formulario `PATCH` a `clientes.asignar` con un `<select name="vendedor_id">` (cada opción "Nombre (x de 10)", el asesor actual preseleccionado; si no hay asesor, primera opción vacía "Elija un asesor") y botón "Asignar"; si el paciente tiene asesor, formulario `PATCH` a `clientes.liberar` con botón "Liberar". Debajo del selector, `$errors->getBag('asignar'.$cliente->id)->first('vendedor_id')`. Encima de la tabla, `session('status')` y `session('error')`. |
| `clientes/show.blade.php` | El mismo bloque de asignar y liberar junto al asesor del paciente. Ya muestra `status` y `error`. |
| `clientes/form.blade.php` | Solo en el alta (`! isset($cliente)`): selector "Asesor" con la opción "Disponible (sin asesor)" (valor vacío) y los asesores con "(x de 10)"; `old('vendedor_id')` y `@error('vendedor_id')`. |

Un paciente de un asesor desactivado (Hotel Los Pinos) muestra su asesor en la lista, pero el selector no ofrece a Iván: no hay opción preseleccionada y la persona debe elegir otro. Todo con `{{ }}`, nunca `{!! !!}`.

---

## 3. Plan de implementación

**Antes de empezar:** la rama `spec-07-asignacion` salió de `main` sin el spec 05 (aún no fusionado). Hay que fusionar el PR del spec 05 y traer `main` a esta rama antes de `/spec-impl`.

Cada paso deja `php artisan test` en verde.

1. **Excepción y servicio.** `CarteraException`, `Cartera` con `bloquearAsesor`, `bloquearPaciente` y `asignar`. Pruebas del servicio directo: CA1, CA4, CA5, CA7.
2. **Asignar desde el panel.** `AsignarRequest`, ruta y acción `asignar`, `asesoresAsignables`, bloque de asignar en `index` y `show`. Pruebas CA1 a CA3, CA6, CA8, CA9 por HTTP.
3. **Liberar.** `Cartera::liberar`, ruta, acción y botón. Pruebas CA10, CA11.
4. **Asesor al crear.** Regla en `ClienteRequest`, `Cartera::crear`, cambio de `store`, selector en `form`. Pruebas CA12 y CA13; cambiar en `ClientesTest` la parte de CA11 del spec 05 que esperaba `vendedor_id = NULL` con `vendedor_id` enviado a mano (ahora es un campo válido): pasa a probarse con un asesor de otro equipo (CA13).
5. **Cierre.** `php artisan route:list --path=clientes`; `git grep -n "{!!" laravel/resources/views/clientes` vacío; prueba manual del candado (CA14); anotar en `docs/verificacion.md`.

**Pruebas:** contra MySQL `crm_test`, con `VerificacionSeeder` en `setUp()` y `actingAs` de Alicia, Sergio o Sandra, como en los specs anteriores. Las cifras de reporte (CA4) se comprueban con la consulta de pronóstico de CLAUDE.md: `ROUND(SUM(total × probabilidad), 2)` de las abiertas por `vendedor_id`.

---

## 4. Criterios de aceptación

Salen de los Dado/Cuando/Entonces de L4, L5, 3.10, sección 5, S5 y S8. Semilla: `php artisan migrate:fresh --seeder=VerificacionSeeder`.

- **CA1 · Asignar hasta 10.** Dado Sandra, cuando asigna Agencia Cuatro Caminos a Carlos (9 de 10), entonces Carlos queda con 10 y el paciente con `vendedor_id` de Carlos; cuando después intenta asignarle Escuela Futuro, entonces ve "Ese asesor ya tiene 10 pacientes, el máximo permitido." junto al selector de esa fila y Escuela Futuro sigue disponible. (Con la API del spec 06: Agencia Cuatro Caminos aparece en `GET /clientes` con el token de Carlos.)
- **CA2 · Asesor ajeno o desactivado.** Dado Sergio, cuando envía a mano `PATCH /clientes/{Escuela Futuro}/asignar` con `vendedor_id` de Carlos (equipo de Sandra) o de Iván (desactivado), o sin `vendedor_id`, entonces ve "Elija un asesor activo de su equipo." junto al selector y nada cambia en la base.
- **CA3 · Paciente ajeno da 404.** Dado Sergio, cuando envía `PATCH /clientes/{Abarrotes San Juan}/asignar` con `vendedor_id` de Valeria, o `PATCH …/liberar` del mismo paciente, entonces el panel responde **404** y el paciente sigue con Carlos. Un id inexistente también da 404.
- **CA4 · Reasignar.** Dado Sergio, cuando reasigna Comercial El Quetzal de Valeria a Diego, entonces "Ampliación a bodega" y "Seguimiento anual" quedan con `vendedor_id` de Diego, "Renovación de planes" (ganada) sigue con Valeria, la actividad "Confirmar fecha de instalación" sigue en el paciente con su `vendedor_id` original, Diego queda con 3 pacientes, y por SQL: Valeria conserva 2 ventas y Q9,250.00 en octubre de 2026 con pronóstico Q450.00, y Diego tiene pronóstico Q2,175.38.
- **CA5 · Paciente de asesor desactivado.** Dado Sandra, cuando reasigna Hotel Los Pinos de Iván (desactivado) a Carlos, entonces el cambio se guarda.
- **CA6 · Selector.** Dado Sergio en `/clientes`, entonces el selector ofrece solo "Diego Castillo (2 de 10)" y "Valeria López (3 de 10)". Dado Alicia, ofrece a Carlos (9 de 10), Diego y Valeria, y no a Iván. En la fila de Valeria su nombre aparece preseleccionado.
- **CA7 · Mismo asesor.** Dado Alicia, cuando asigna Comercial El Quetzal a Valeria (ya es suyo), entonces ve "El paciente se asignó correctamente.", Valeria sigue con 3 y sus planes no cambian. Lo mismo con un asesor que ya tiene 10 y se reasigna a sí mismo: no da error de cartera.
- **CA8 · Administrador sin límite de equipo.** Dado Alicia, cuando asigna Café Zunil a Carlos (equipo de Sandra) y Gimnasio Fuerza a Valeria (equipo de Sergio), entonces las dos se guardan.
- **CA9 · Error en su fila.** Dado el error de CA1, cuando se vuelve a la lista, entonces el mensaje aparece solo debajo del selector de Escuela Futuro, no en las demás filas.
- **CA10 · Liberar con plan abierto.** Dado Sergio, cuando intenta liberar Ferretería Xela ("Capacitación de personal" en `propuesta`), entonces ve "No se puede liberar: el paciente tiene planes de tratamiento abiertos. Ciérrelos o reasigne al paciente." y el paciente sigue con Valeria.
- **CA11 · Liberar con planes cerrados.** Dado Sergio, cuando libera María Coyoy, entonces queda disponible ("Disponible" en la lista, `vendedor_id = NULL`), Valeria queda con 2, y "Soporte del hogar" sigue con `vendedor_id` de Valeria (por SQL: Valeria tiene 1 venta y Q3,600.00 en noviembre de 2026). El botón "Liberar" no aparece en los disponibles.
- **CA12 · Crear con asesor.** Dado Alicia, cuando crea un paciente válido con asesor Valeria, entonces queda con `vendedor_id` de Valeria y Valeria con 4; sin asesor, queda disponible. Dado Carlos con 10 (después de CA1), cuando Sandra crea un paciente con asesor Carlos, entonces vuelve al formulario con lo escrito y "Ese asesor ya tiene 10 pacientes, el máximo permitido." junto al asesor, y no se crea ningún paciente.
- **CA13 · Crear con asesor ajeno.** Dado Sergio creando un paciente, cuando envía a mano `vendedor_id` de Carlos o de Iván, entonces ve "Elija un asesor activo de su equipo." junto al asesor y no se crea nada. Editar un paciente con `vendedor_id` añadido a mano no cambia su asesor.
- **CA14 · Mismo candado que la API (manual).** Dado una sesión `mysql` con `START TRANSACTION; SELECT id FROM users WHERE id = <Carlos> FOR UPDATE;` sin confirmar, cuando Alicia asigna un paciente a Carlos desde el panel, entonces la página espera hasta el `COMMIT` de esa sesión y solo entonces responde. Con la API del spec 06: dado Carlos con 9, cuando el panel le asigna uno y la API le hace tomar otro al mismo tiempo, entonces solo uno lo consigue y Carlos queda con 10 (S8 y sección 3.10). Se anota en `docs/verificacion.md`.
- **CA15 · XSS.** Dado un paciente con nombre `<img src=x onerror=alert(1)>` asignado a Valeria, cuando se abren la lista y el detalle, entonces se ve el texto literal; `git grep -n "{!!" laravel/resources/views/clientes` está vacío.
- **CA16 · Pruebas.** `cd laravel && php artisan test` termina en verde, incluidas `AsignacionTest` y `ClientesTest` ajustada.

---

## 5. Decisiones tomadas y descartadas

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| Lógica en `app/Services/Cartera.php`, errores con `CarteraException` | Todo en el controlador | Decidido por la integrante; `plan.md` ya lo prevé. Un solo lugar para el candado, reutilizable por el spec 09 o un reto |
| Mensajes exactos de L4 | "La cartera de <nombre> ya tiene 10…" y liberar sin la segunda frase | Decidido por la integrante el 2026-11-08: la especificación manda y los textos deben coincidir con los de la API |
| `CarteraException` con campo opcional: cartera llena y asesor no válido junto al selector; liberar como mensaje de página | Todo como mensaje de página | L4 pide "error junto al campo" para la cartera llena y el asesor no válido |
| Asesor ajeno o desactivado → error junto al campo; paciente ajeno → 404 | 404 también para el asesor ajeno | Decidido por la integrante: es el criterio de L4. S5 aplica al recurso de la URL (el paciente), no al valor elegido en el selector |
| Validar el asesor en el FormRequest **y** otra vez tras el candado | Solo en el FormRequest | La cuenta puede desactivarse o cambiar de equipo entre la validación y el candado |
| Asesor opcional al crear, en este spec, con `Cartera::crear()` | Dejarlo para otro spec | Decidido por la integrante: L4 lo pide y el spec 05 lo dejó para el 07. Se agrega `crear()` a los dos métodos pedidos porque el `INSERT` debe ir después del candado de `users` (orden de candados) |
| Asignar al asesor que ya lo tiene: no hace nada y responde éxito | Quitar al asesor actual del selector | Decidido por la integrante. No cuenta la cartera, así un asesor con 10 no ve un error falso |
| Bloquear el paciente por id y revisar la visibilidad en PHP | `visiblePara()->lockForUpdate()` | El subquery de `visiblePara` no bloquea `users` y complica la lectura; por id se lee el `vendedor_id` más reciente y se decide el 404 sin ambigüedad |
| `liberar` no bloquea `users` | Bloquear al asesor actual | Liberar solo resta de la cartera; no puede pasar del máximo. Empieza en `clientes`, que respeta el orden |
| Bolsa de errores `asignar{id}` por paciente | Una sola bolsa | En la lista hay un selector por fila; el error debe salir solo en la fila del paciente |
| Mensajes de éxito "El paciente se asignó correctamente." y "El paciente quedó disponible." | — | Mismo estilo que los del spec 05; L4 no fija textos de éxito |
| Liberar sin confirmación | `confirm()` como al eliminar | Se puede deshacer asignando de nuevo; no borra nada |

---

## 6. Riesgos

- **Base de la rama.** `spec-07-asignacion` salió de `main` antes de fusionar el spec 05; sin traer ese trabajo no existen `ClienteController` ni `visiblePara`.
- **El candado solo sirve si la API lo usa igual.** Si B, en el spec 06, cuenta la cartera sin `SELECT id FROM users WHERE id = ? FOR UPDATE`, el panel y la API podrían dejar a un asesor con 11. Conviene revisarlo en el PR del spec 06 y repetir CA14 con la API.
- **Liberar frente a un plan nuevo de la API.** Si la API crea un plan (spec 11) sin bloquear la fila del paciente, entre la comprobación y el `UPDATE` de `liberar` podría quedar un plan abierto en un paciente disponible. `liberar` bloquea la fila del paciente; hay que pedir a B que `POST /oportunidades` también lea el paciente con `FOR UPDATE` (orden `clientes` → `oportunidades`).
- **La última palabra es del panel.** Si un asesor toma un disponible por la API mientras el coordinador lo asigna a otro, el panel espera el candado y, al seguir, reasigna (si el paciente sigue siendo visible). No se pierde nada (los planes abiertos pasan al nuevo asesor), pero el asesor de la API lo pierde sin aviso. Si se quiere evitar, haría falta enviar el asesor esperado en el formulario, con un mensaje que hoy no está en la especificación.
- **Spec 05 cambia.** Su CA11 ("`vendedor_id` a mano queda NULL") deja de valer para el alta; la prueba se ajusta en el paso 4.
- **Cifras de reporte sin reporte.** CA4 y CA11 se comprueban con SQL hasta que exista el spec 14.
