# 05 · Pacientes

**Estado:** Implementado
**Depende de:** 01-esquema-y-semilla (tabla `clientes` con `chk_clientes_nit`, `chk_clientes_telefono` y los índices únicos `clientes_nit_unique` y `clientes_telefono_unique`; modelos `Cliente`, `User` y `Oportunidad`; `VerificacionSeeder`), 03-cuentas-roles (middleware `panel` y `rol`, nombre de ruta reservado `clientes.index` para el menú y la redirección de `/dashboard`), 04-catalogo (`App\Support\Formato` y el estilo de los formularios)
**Fecha:** 2026-11-08
**Dueña:** A
**Rama:** `spec-05-pacientes`
**Objetivo:** que el administrador y el coordinador busquen, creen y editen pacientes con NIT y teléfono normalizados, y que el administrador elimine los que no tienen planes.

**Requisitos que cumple:** L4 de `docs/especificacion.md` en su parte 1 (lista, búsqueda, filtros, alta, edición y eliminación), la parte de L5 y S5 que toca a los pacientes (lo ajeno responde 404), la parte de L7 que toca a `ClienteRequest` y la sección 10 (casos compartidos de NIT y teléfono). Usa la fila `clientes` de la sección 3.4 y R4 del enunciado (módulo 11).

---

## 1. Alcance

**Incluye**
- `app/Support/Nit.php` y `app/Support/Telefono.php` (normalizar y validar).
- Reglas `app/Rules/NitValido.php` y `app/Rules/TelefonoValido.php`.
- `ClienteRequest` (nombre, NIT, teléfono, correo) con los repetidos comparados ya normalizados.
- Scope `Cliente::visiblePara(User $u)` y `$fillable` en `Cliente`.
- `ClienteController` con `index`, `create`, `store`, `show`, `edit`, `update` y `destroy`.
- Vistas `resources/views/clientes/` (`index`, `create`, `edit`, `form`, `show`).
- `Oportunidad::ETAPAS_EN_PANTALLA` (vocabulario dental de la sección 1.3), que usa el detalle del paciente y reutilizará el spec 12.
- Pruebas `tests/Unit/NitTest.php`, `tests/Unit/TelefonoTest.php` y `tests/Feature/ClientesTest.php`.
- Anotar en `docs/verificacion.md` las filas de L4 (parte 1), S5 (pacientes) y sección 10 (panel).

**No incluye**
- **Asignar, reasignar y liberar**, ni el selector de asesor **al crear** con su candado de cartera (spec 07). En este spec todo paciente nuevo queda con `vendedor_id = NULL` (disponible), y el formulario no tiene campo de asesor.
- `AsignarRequest` y el mensaje "Elija un asesor activo de su equipo." (spec 07).
- La página "Mi equipo" (spec 09) y el detalle de un plan (spec 12): el detalle del paciente lista sus planes sin enlazarlos.
- `lang/es/validation.php` y `APP_LOCALE=es` (spec 16). Los mensajes van en `messages()` de `ClienteRequest` y en las reglas.
- `src/util/nit.js` y `src/util/telefono.js` de la API (spec 06, integrante B). Este spec fija los casos que las dos deben pasar (sección 10), no toca `api/`.
- Mostrar o editar actividades del paciente.

---

## 2. Modelo de datos

**No hay migraciones nuevas.** Se usa la tabla `clientes` del spec 01: `vendedor_id` nulo (FK a `users`), `nombre VARCHAR(150)`, `nit VARCHAR(12) NULL UNIQUE`, `telefono CHAR(8) UNIQUE`, `correo VARCHAR(150) NULL`, con `chk_clientes_telefono` y `chk_clientes_nit`.

Cambios a los modelos:

```php
// app/Models/Cliente.php
protected $fillable = ['nombre', 'nit', 'telefono', 'correo'];   // vendedor_id NO: lo asigna el spec 07

/** Pacientes que la persona del panel puede ver (S5). */
public function scopeVisiblePara(Builder $query, User $u): Builder
{
    if ($u->esAdmin()) {
        return $query;
    }
    if ($u->esSupervisor()) {
        return $query->where(fn ($q) => $q
            ->whereNull('vendedor_id')
            ->orWhereIn('vendedor_id', User::select('id')
                ->where('rol', 'vendedor')
                ->where('supervisor_id', $u->id)));   // activos y desactivados
    }
    return $query->whereRaw('1 = 0');                  // expresión fija: nadie más ve pacientes
}

/** NIT para mostrar: "CF" cuando es NULL. */
public function nitEnPantalla(): string
{
    return $this->nit ?? 'CF';
}

// app/Models/Oportunidad.php
public const ETAPAS_EN_PANTALLA = [
    'prospecto' => 'Interesado', 'calificado' => 'Evaluado', 'propuesta' => 'Presupuesto enviado',
    'negociacion' => 'En negociación', 'ganada' => 'Aceptado', 'perdida' => 'Rechazado',
];
```

`vendedor_id` queda fuera de `$fillable` a propósito: aunque alguien envíe `vendedor_id` a mano en el formulario, `validated()` no lo trae y `create()` no lo usaría.

### 2.1 `app/Support/Nit.php`

Clase final con métodos estáticos, sin dependencias. Implementa R4 del enunciado.

| Método | Qué hace |
|---|---|
| `normalizar(string $texto): string` | Quita espacios (cualquier `\s`) y guiones, pasa a mayúsculas (`mb_strtoupper`) y quita los ceros a la izquierda (`ltrim($s, '0')`). `452 1817-k` → `4521817K`; `00012345679` → `12345679`; `cf` → `CF`; `000` → `''` |
| `esConsumidorFinal(string $texto): bool` | `self::normalizar($texto) === 'CF'` |
| `esValido(string $compacto): bool` | Recibe el NIT **ya normalizado**. Verdadero solo si: tiene entre 2 y 12 caracteres; el cuerpo (todo menos el último) son solo dígitos (`ctype_digit`); el verificador es `0`–`9` o `K`; y el verificador coincide con el módulo 11: cada dígito del cuerpo, de derecha a izquierda, por 2, 3, 4…; `(11 − (suma mod 11)) mod 11`; 10 → `K` |

Como `normalizar` quita los ceros a la izquierda, un NIT que pasa `esValido` también cumple `chk_clientes_nit` (`^[1-9][0-9]*[0-9K]$`).

### 2.2 `app/Support/Telefono.php`

| Método | Qué hace |
|---|---|
| `normalizar(string $texto): string` | 1) Quita espacios, guiones, puntos y paréntesis. 2) Si empieza con `+502`, lo quita. 3) Si no, si empieza con `502` **y** lo que queda son exactamente 8 caracteres, lo quita. `+502 5555-1234` → `55551234`; `(502) 7761.2345` → `77612345`; `50255551234` → `55551234`; `5021 2345` → `50212345` |
| `esValido(string $normalizado): bool` | `preg_match('/^[2-9][0-9]{7}$/', $normalizado) === 1` |

### 2.3 Reglas

| Clase | Pasa si | Mensaje |
|---|---|---|
| `App\Rules\NitValido` (`ValidationRule`) | El valor es texto y `Nit::esConsumidorFinal($v) || Nit::esValido(Nit::normalizar($v))` | "Escriba un NIT válido (con su dígito verificador) o CF." |
| `App\Rules\TelefonoValido` | El valor es texto y `Telefono::esValido(Telefono::normalizar($v))` | "Escriba un teléfono de Guatemala de 8 dígitos." |

### 2.4 `ClienteRequest`

`authorize()` devuelve `true` (la ruta ya pasa por `rol:administrador,supervisor` y el controlador busca con `visiblePara`). Cada campo lleva `bail`.

**Lo ajeno da 404 antes de validar** (ajuste aprobado durante la implementación): `prepareForValidation()` hace `Cliente::visiblePara($this->user())->findOrFail($this->route('cliente'))` cuando la ruta trae `{cliente}`. Sin esto, un `PUT` a un paciente de otro equipo con datos incompletos devolvería errores de validación (302) en vez de 404 (CA6), y el mensaje de repetido podría confirmar un NIT o teléfono ajeno.

| Campo | Reglas | Mensajes |
|---|---|---|
| `nombre` | `bail`, `required`, `string`, `max:150` | "Escriba el nombre del paciente." / "El nombre no puede pasar de 150 caracteres." |
| `nit` | `bail`, `required`, `string`, `new NitValido` | "Escriba el NIT del paciente o CF." / (mensaje de la regla) |
| `telefono` | `bail`, `required`, `string`, `new TelefonoValido` | "Escriba el teléfono del paciente." / (mensaje de la regla) |
| `correo` | `bail`, `nullable`, `string`, `email`, `max:150` | "Escriba un correo válido." / "El correo no puede pasar de 150 caracteres." |

**Repetidos ya normalizados**, en `after()` del FormRequest (solo si el campo no tiene ya un error):
- NIT: si no es CF, `Cliente::where('nit', Nit::normalizar($nit))` excluyendo el paciente que se edita (`$this->route('cliente')`) → si existe, error "Ya existe un paciente con ese NIT." junto a `nit`.
- Teléfono: `Cliente::where('telefono', Telefono::normalizar($tel))` con la misma exclusión → "Ya existe un paciente con ese teléfono." junto a `telefono`.
- Se busca en **toda** la tabla, no solo en lo visible: el índice único es global.

**Datos a guardar:** método `datos(): array` que devuelve `nombre`, `nit` (`null` si es CF, si no `Nit::normalizar`), `telefono` (`Telefono::normalizar`) y `correo` (`null` si viene vacío). La normalización **no** se hace en `prepareForValidation`, para que `old()` devuelva exactamente lo que la persona escribió.

### 2.5 Rutas

Dentro del grupo `auth`, `panel` de `routes/web.php`:

```php
Route::middleware('rol:administrador,supervisor')->group(function () {
    Route::resource('clientes', ClienteController::class)->except(['destroy']);
});
Route::middleware('rol:administrador')->group(function () {
    // … cuentas y productos (ya existen)
    Route::delete('clientes/{cliente}', [ClienteController::class, 'destroy'])->name('clientes.destroy');
});
```

| Método y URL | Nombre | Quién |
|---|---|---|
| `GET /clientes` | `clientes.index` | administrador, coordinador |
| `GET /clientes/create`, `POST /clientes` | `clientes.create`, `clientes.store` | administrador, coordinador |
| `GET /clientes/{cliente}` | `clientes.show` | administrador, coordinador |
| `GET /clientes/{cliente}/edit`, `PUT/PATCH /clientes/{cliente}` | `clientes.edit`, `clientes.update` | administrador, coordinador |
| `DELETE /clientes/{cliente}` | `clientes.destroy` | solo administrador (el coordinador recibe **403**) |

Al existir `clientes.index`, el menú muestra "Pacientes" y `/dashboard` redirige ahí (spec 03).

### 2.6 `ClienteController`

**Sin enlace implícito de modelo:** cada acción recibe el id (`string $cliente`) y busca con `Cliente::visiblePara($request->user())->findOrFail($cliente)`. Lo de otro equipo (y un id inexistente) responde **404**, también al enviar el formulario directamente. El 403 del coordinador en `destroy` sale antes, del middleware `rol`.

- **`index`**: `Cliente::visiblePara($u)->with('vendedor:id,name,activo')`, ordenado por `nombre`, `paginate(15)->withQueryString()`.
  - **Búsqueda `q`** (se recorta; vacía = sin búsqueda): `nombre LIKE %q%` **o** `nit LIKE %Nit::normalizar(q)%` (esta segunda condición solo si el compactado no queda vacío). Los comodines `%`, `_` y `\` de `q` se escapan. El nombre se compara con la intercalación de la base (`utf8mb4_unicode_ci`): `quetzal` encuentra "Comercial El Quetzal".
  - **Filtro `asesor`**: `sin` → `whereNull('vendedor_id')`; un número → `where('vendedor_id', $id)` (sobre el alcance visible, así que un id ajeno da lista vacía, no datos de otro equipo); otro valor se ignora. Se combina con `q`.
  - **Opciones del filtro**: administrador → todas las cuentas `vendedor` (activas y desactivadas); coordinador → los `vendedor` con `supervisor_id` = él. Ordenadas por nombre, con " (desactivado)" si aplica. Más la opción "Sin asignar".
  - **Columnas**: Nombre (enlace al detalle), NIT (`nitEnPantalla()`), Teléfono, Correo (vacío si no hay), Asesor (nombre, o "Disponible"). Encima de la tabla: "N pacientes" con el total del paginador. Botón "Nuevo paciente". Título: "Pacientes". Sin resultados: "No hay pacientes que coincidan con la búsqueda."
- **`create` / `edit`**: la misma vista `clientes/form.blade.php`. Al editar, el NIT se muestra como `nitEnPantalla()`.
- **`store`**: `Cliente::create($request->datos())` (queda disponible). Redirige a `clientes.show` con "El paciente se creó correctamente.".
- **`update`**: busca con `visiblePara`, `->update($request->datos())`. Redirige a `clientes.show` con "El paciente se actualizó correctamente.". No toca `vendedor_id`.
- **Repetido por carrera (1062)**: `store` y `update` capturan `QueryException` con `errorInfo[1] === 1062`. Si el mensaje contiene `clientes_nit_unique` → `back()->withInput()->withErrors(['nit' => 'Ya existe un paciente con ese NIT.'])`; si contiene `clientes_telefono_unique` → lo mismo con `telefono`. Cualquier otra excepción se relanza.
- **`show`**: datos del paciente (nombre, NIT o "CF", teléfono, correo, asesor o "Disponible") y sus planes (`oportunidades` con `vendedor:id,name`, ordenados por `created_at`): título, etapa con `ETAPAS_EN_PANTALLA` y asesor del plan. Sin enlaces a los planes (spec 12) ni montos. Botones "Editar" y, **solo para el administrador**, "Eliminar" (formulario `DELETE` con `onsubmit="return confirm('¿Eliminar este paciente? Se borrarán también sus citas, llamadas y tareas.')"`).
- **`destroy`** (solo administrador): busca con `visiblePara`; si `$cliente->oportunidades()->exists()`, vuelve a `clientes.show` con el error de página "No se puede eliminar: el paciente tiene planes de tratamiento." y no borra. Si no, `delete()` (sus actividades se borran en cascada, sección 3.9) y redirige a `clientes.index` con "El paciente se eliminó.". Si entre la comprobación y el `DELETE` alguien le crea un plan, MySQL responde 1451 (FK sin acción): se captura y se muestra el mismo mensaje.

### 2.7 Vistas

| Archivo | Contenido |
|---|---|
| `clientes/index.blade.php` | Formulario `GET` con "Buscar por nombre o NIT" (`q`), selector "Asesor" (Todos / Sin asignar / asesores) y botón "Buscar"; la tabla de 2.6 y `{{ $clientes->links() }}` |
| `clientes/create.blade.php`, `edit.blade.php` | Título ("Nuevo paciente" / "Editar paciente") e `@include('clientes.form')` |
| `clientes/form.blade.php` | Nombre, NIT (ayuda: "Con su dígito verificador, por ejemplo 4521817-K, o CF."), Teléfono (ayuda: "8 dígitos; puede incluir +502."), Correo (opcional); "Guardar" y "Cancelar". `old()` en cada campo y `@error` junto a cada uno |
| `clientes/show.blade.php` | Datos, planes, botones y mensajes de `session('status')` y del error de eliminar |

Todo con `{{ }}`, nunca `{!! !!}`. Montos y fechas, si aparecen, con `Formato` (en este spec no se muestran).

---

## 3. Plan de implementación

Cada paso deja `php artisan test` en verde.

1. **NIT y teléfono.** `Nit.php`, `Telefono.php`, `NitTest` y `TelefonoTest` con todos los casos de la sección 10 más los bordes de la sección 4 (CA1, CA2). Son pruebas unitarias puras (`PHPUnit\Framework\TestCase`), sin base.
2. **Reglas.** `NitValido` y `TelefonoValido`, con sus casos en las mismas pruebas unitarias (vía `Validator::make` en una prueba de `Tests\TestCase`).
3. **Visibilidad y lista.** `$fillable`, `visiblePara`, `nitEnPantalla`, rutas `index`, `ClienteController@index` y la vista. Pruebas de CA3 a CA6.
4. **Alta.** `ClienteRequest`, `create`, `store`, `form` y `create`. Pruebas de CA7 a CA11.
5. **Detalle y edición.** `ETAPAS_EN_PANTALLA`, `show`, `edit`, `update` y la captura del 1062. Pruebas de CA12 a CA15.
6. **Eliminar.** Ruta `clientes.destroy` con `rol:administrador`, `destroy` y su botón. Pruebas de CA16 y CA17.
7. **Cierre.** `php artisan route:list --path=clientes`; `git grep -n "{!!" laravel/resources/views/clientes` vacío; anotar en `docs/verificacion.md`.

**Pruebas de función:** contra MySQL `crm_test` como en los specs anteriores, con `VerificacionSeeder` en `setUp()` y la sesión de Alicia, Sergio o Sandra con `actingAs`. La carrera del 1062 (CA15) se prueba con un `Cliente::creating` temporal en la prueba que inserta el repetido justo antes del `INSERT`, o con un mock del `after()`; la que elija la implementación, documentada en la prueba.

---

## 4. Criterios de aceptación

Salen de los Dado/Cuando/Entonces de L4, L7, S5 y la sección 10. Semilla: `php artisan migrate:fresh --seeder=VerificacionSeeder` (Comercial El Quetzal tiene NIT `4521817K` y teléfono `55551234`).

- **CA1 · NIT (unitaria).** `NitTest` pasa: `452 1817-k` → `4521817K` válido; `4521817-K` → `4521817K` válido; `00012345679` → `12345679` válido; `cf` y `CF` son consumidor final; `4521817-1` inválido (verificador). Bordes: `310` válido (verificador 0); `23K` y `23k` válidos (verificador K); `1` inválido (1 carácter); `0` y `000` inválidos (quedan vacíos); 13 caracteres inválido; `12A45679` inválido (letra en el cuerpo); `C F` es consumidor final; `CFX` inválido.
- **CA2 · Teléfono (unitaria).** `TelefonoTest` pasa: `+502 5555-1234` → `55551234` válido; `(502) 7761.2345` → `77612345` válido; `50255551234` → `55551234` válido; `5021 2345` → `50212345` válido (no se quita el 502); `1234-5678` inválido (empieza con 1); `5555123` inválido (7 dígitos). Bordes: `502 5555 123` → `5025555123` inválido; `+502 1234 5678` inválido; `5555-12a4` inválido.
- **CA3 · Lista del administrador.** Dado Alicia, cuando abre `/clientes`, entonces ve "21 pacientes" (15 en la primera página y 6 en la segunda); con `asesor=sin` ve 6; con el filtro "Iván Toj" ve solo Hotel Los Pinos. María Coyoy aparece con NIT "CF" y los disponibles con "Disponible".
- **CA4 · Lista del coordinador.** Dado Sergio, cuando abre `/clientes`, entonces ve 11 pacientes (3 de Valeria, 2 de Diego y 6 disponibles), no ve Abarrotes San Juan, y su filtro de asesor solo ofrece "Sin asignar", Valeria y Diego. Con `?asesor=<id de Carlos>` escrito a mano ve una lista vacía.
- **CA5 · Búsqueda.** Dado Alicia, cuando busca `4521817-k` y después `quetzal`, entonces las dos muestran Comercial El Quetzal; con `q=quetzal&asesor=sin` no aparece (combinación). Buscar `%` no devuelve los 21.
- **CA6 · Lo ajeno da 404 (S5).** Dado Sergio y el id de Abarrotes San Juan (de Carlos), cuando abre `/clientes/{id}`, `/clientes/{id}/edit` o envía `PUT /clientes/{id}` con `nombre=Cambiado`, entonces las tres responden **404** y el nombre en la base no cambia. Un id inexistente también da 404.
- **CA7 · NIT y teléfono repetidos.** Dado Alicia creando un paciente, cuando envía NIT `4521817-k`, entonces vuelve con lo escrito (`old('nit') === '4521817-k'`) y "Ya existe un paciente con ese NIT." junto al NIT; y con teléfono `+502 5555-1234`, "Ya existe un paciente con ese teléfono." junto al teléfono. No se guarda nada.
- **CA8 · NIT y teléfono inválidos.** Dado Alicia creando un paciente, cuando envía NIT `4521817-1` y teléfono `1234-5678`, entonces aparece "Escriba un NIT válido (con su dígito verificador) o CF." junto al NIT y "Escriba un teléfono de Guatemala de 8 dígitos." junto al teléfono, y lo escrito se conserva.
- **CA9 · CF y prefijo.** Dado Alicia creando "Paciente Prueba" con NIT `cf` y teléfono `(502) 7761.9999`, cuando lo guarda, entonces en la base queda `nit = NULL`, `telefono = '77619999'` y `vendedor_id = NULL`, y la lista lo muestra con NIT "CF" y "Disponible". Un segundo paciente con NIT `CF` y otro teléfono también se guarda (CF no se compara).
- **CA10 · Sección 10 en el formulario.** Dado cada entrada de la sección 10, cuando se escribe en el alta del panel (con el otro campo válido y sin repetir), entonces se guarda con el valor de la tabla o se rechaza con el mensaje junto al campo; `4521817-K` choca con Comercial El Quetzal y `50255551234` con su teléfono.
- **CA11 · Obligatorios, correo y `vendedor_id`.** Dado el alta enviada vacía, entonces aparecen "Escriba el nombre del paciente.", "Escriba el NIT del paciente o CF." y "Escriba el teléfono del paciente." junto a cada campo y ningún texto en inglés. Con correo `no-es-correo` y el resto válido, vuelve con nombre, NIT y teléfono escritos y "Escriba un correo válido." junto al correo. Con un nombre de 151 caracteres, el mensaje de largo. Con `vendedor_id=<Valeria>` añadido a mano, el paciente queda con `vendedor_id = NULL`.
- **CA12 · Coordinador crea y edita.** Dado Sergio, cuando crea un paciente, entonces queda disponible y lo ve en su lista; cuando edita Café Zunil (disponible) o Farmacia Los Altos (de Diego), entonces se guarda.
- **CA13 · Editar sin chocar consigo mismo.** Dado Alicia editando Comercial El Quetzal, cuando guarda con NIT `4521817-k` y teléfono `5555 1234` (los suyos, escritos de otra forma), entonces se guarda sin error de repetido; cuando cambia el teléfono a `77612345` (de Ferretería Xela), entonces ve "Ya existe un paciente con ese teléfono.".
- **CA14 · Detalle.** Dado Alicia, cuando abre el detalle de Comercial El Quetzal, entonces ve sus datos y sus 3 planes: "Renovación de planes" (Aceptado), "Ampliación a bodega" (En negociación) y "Seguimiento anual" (Interesado), con asesor Valeria López, y el botón "Eliminar". Sergio, en el mismo detalle, no ve "Eliminar".
- **CA15 · Carrera (1062).** Dado un repetido que se cuela entre la validación y el `INSERT`, cuando se guarda, entonces el formulario vuelve con lo escrito y el mensaje de repetido junto al campo `nit` o `telefono`, nunca una página de error.
- **CA16 · Eliminar.** Dado Alicia, cuando elimina Café Zunil, entonces desaparece de la lista (20 pacientes) y de `SELECT … FROM clientes WHERE vendedor_id IS NULL`; cuando intenta eliminar Ferretería Xela, entonces ve "No se puede eliminar: el paciente tiene planes de tratamiento." y el paciente sigue.
- **CA17 · El coordinador no elimina.** Dado Sergio, cuando envía a mano `DELETE /clientes/{id}` de Café Zunil, entonces el panel responde **403** y el paciente sigue; `php artisan route:list --path=clientes` muestra `DELETE` solo con `rol:administrador`.
- **CA18 · Menú.** Dado Alicia o Sergio, el menú muestra "Pacientes" y `/dashboard` redirige a `/clientes`.
- **CA19 · XSS.** Dado un paciente creado con nombre `<img src=x onerror=alert(1)>`, NIT `CF` y teléfono válido, cuando se abren la lista y el detalle, entonces se ve el texto literal; `git grep -n "{!!" laravel/resources/views/clientes` está vacío.
- **CA20 · Pruebas.** `cd laravel && php artisan test` termina en verde, incluidas `NitTest`, `TelefonoTest` y `ClientesTest`.

---

## 5. Decisiones tomadas y descartadas

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| El coordinador **edita** los pacientes de su equipo y los **disponibles** (`visiblePara` en `edit`/`update`) | Que solo vea y asigne los disponibles | Decidido por la integrante el 2026-11-08. Cierra la pregunta abierta de L4/L7 y S9 de la especificación; **hay que anotarlo en `docs/especificacion.md` en un PR de docs** (no lo hace este spec) |
| Página de detalle (`show`) de solo lectura con datos y planes | Sin detalle; "Eliminar" en la fila | Decidido por la integrante. Es donde se ven los planes antes de eliminar; no agrega reglas nuevas |
| Alta siempre sin asesor en este spec | Selector de asesor al crear ya en el spec 05 | El reparto separa L4 en dos specs; el asesor al crear necesita el candado de cartera, que es del spec 07. El spec 07 debe agregar ese campo |
| `findOrFail` con `visiblePara` también en `prepareForValidation()` de `ClienteRequest` | Middleware de ruta propio; o aceptar el 302 y probar CA6 con datos completos | El FormRequest valida antes del controlador; así lo ajeno da 404 sin importar lo que se envíe (decidido por la integrante durante la implementación) |
| `visiblePara` + `findOrFail` con el id, sin enlace implícito de modelo | Políticas (`authorize`) o enlace implícito | S5: lo ajeno responde 404, no 403; con enlace implícito el modelo se cargaría sin alcance |
| Normalizar en `after()` y en `datos()`, no en `prepareForValidation` | Reemplazar `nit`/`telefono` antes de validar | `old()` debe devolver lo que la persona escribió (L4 y L7: "vuelve con lo escrito") |
| Repetidos con una consulta propia sobre el valor normalizado | `Rule::unique` sobre el campo crudo | `Rule::unique` compararía `4521817-k` sin normalizar y no lo vería repetido |
| Capturar 1062 y decidir el campo por el nombre del índice | Página de error, o un mensaje general | Regla técnica compartida: el índice único es el árbitro final y el error va junto al campo |
| Capturar 1451 al eliminar | Bloquear con `FOR UPDATE` antes de borrar | Basta para que una carrera dé el mensaje y no una página de error; la FK sin acción ya impide el borrado |
| Búsqueda con `LIKE` escapado y NIT compactado con `Nit::normalizar` | Comparar `q` crudo contra el NIT | L4 y `GET /clientes?q=` piden compactar `q` igual que un NIT |
| `vendedor_id` fuera de `$fillable` | Dejarlo y filtrarlo en el controlador | Un campo enviado a mano no puede asignar un paciente desde este formulario |
| Filtro por asesor aplicado sobre el alcance visible | Validar que el id esté en las opciones y responder 404 | Más simple y no filtra datos: un id ajeno da lista vacía |
| `Oportunidad::ETAPAS_EN_PANTALLA` en el modelo | Textos en la vista | El spec 12 lo reutiliza en la lista y el detalle de planes |

---

## 6. Riesgos

- **Panel y API deben normalizar igual (L7).** Si B implementa `nit.js` o `telefono.js` distinto, las dos puertas darán resultados distintos. Las pruebas unitarias usan los casos de la sección 10; conviene que B use los mismos bordes de CA1 y CA2 en el spec 06.
- **`email` de Laravel vs la validación de la API.** La regla `email` (RFC) puede aceptar algo que la API rechace o al revés. Si en el spec 06 aparece una diferencia, se ajusta con `email:filter` o se acuerda el patrón.
- **Pregunta abierta en la especificación.** Hasta que se anote la decisión del coordinador sobre los disponibles, la especificación y este spec dicen cosas distintas en S9.
- **El mensaje de repetido revela que el NIT o teléfono existe en otro equipo.** Es el mismo comportamiento que la API (409) y lo exige el índice único global; no se muestra de quién es.
- **Paginación y CA3.** Los criterios cuentan 21 pacientes; con 15 por página, las pruebas comprueban el total mostrado ("21 pacientes") y no las filas de una sola página.
- **Spec 07 cambia el formulario.** Al agregar el asesor al crear, `store` pasará a usar una transacción con candado; este spec deja `store` simple para que ese cambio sea local.
