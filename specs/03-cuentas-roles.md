# 03 · Cuentas y roles

**Estado:** Aprobado
**Depende de:** 01-esquema-y-semilla (columnas `rol`, `supervisor_id`, `activo`, `chk_users_supervisor`, modelo `User` y `VerificacionSeeder`)
**Fecha:** 2026-11-08
**Dueña:** A
**Rama:** `spec-03-cuentas-roles`
**Objetivo:** que el administrador cree y edite las cuentas desde el panel y que cada rol entre solo por su puerta, con el panel cerrado para asesores y cuentas desactivadas.

**Requisitos que cumple:** L2 de `docs/especificacion.md`, y de la sección 1.2 la parte "una cuenta desactivada no entra y pierde la sesión en su siguiente petición" (puerta del panel). Toma la regla de `supervisor_id` de la sección 3.10 y cierra, para el panel, la pregunta abierta del final de L7 sobre cambiar el rol o desactivar cuentas con asesores o pacientes (ver Riesgos: hay que anotarlo en la especificación).

---

## 1. Alcance

**Incluye**
- Quitar de Breeze el registro, "olvidé mi contraseña"/restablecer, verificación de correo, confirmar contraseña, y del perfil la edición de nombre y correo y la sección "eliminar cuenta" (rutas, controladores, vistas, `ProfileUpdateRequest` y pruebas de Breeze que ya no aplican), junto con los enlaces que llevan a ellas (bienvenida, login, perfil, menú).
- El perfil conserva solo el cambio de contraseña, con mensajes en español.
- `LoginRequest` con los mensajes de L2: credenciales incorrectas, cuenta de asesor y cuenta desactivada.
- Middleware `CuentaPanel` (alias `panel`) y `Rol` (alias `rol`), registrados en `bootstrap/app.php`.
- `CuentaController` (index, create, store, edit, update), `CuentaRequest` y vistas `resources/views/cuentas/`.
- Menú según rol con enlaces condicionados a que la ruta exista; `/dashboard` redirige a Pacientes cuando exista esa ruta.
- Pruebas de Feature para login, middleware, cuentas y menú.

**No incluye**
- `lang/es/validation.php`, `APP_LOCALE=es` y los nombres de campos en pantalla para todo el panel (spec 16-validacion). Aquí los mensajes van en `messages()` de cada FormRequest y en el `validateWithBag` del cambio de contraseña.
- Las páginas Tratamientos, Pacientes, Planes, Reporte y Mi equipo (specs 04, 05, 09, 12 y 14). Este spec solo fija sus nombres de ruta para el menú.
- La verificación del 403 de un coordinador en `/productos` (se comprueba en el spec 04, que crea esa ruta con `rol:administrador`).
- Traducir o rediseñar el resto de la página de bienvenida; solo se le quita el enlace "Register".
- Límite de intentos de inicio de sesión distinto del de Breeze (se conserva el de Breeze: 5 intentos por correo e IP).
- Reasignar pacientes (spec 07) o asesores en bloque: para mover a un asesor de equipo se edita su cuenta, uno por uno.

---

## 2. Modelo de datos

**No hay migraciones nuevas.** Se usan las columnas de `users` creadas en el spec 01 (`rol`, `supervisor_id`, `activo`) y la restricción `chk_users_supervisor`. El modelo `User` ya tiene `esAdmin()`, `esSupervisor()`, `esVendedor()`, `supervisor()`, `vendedores()` y `clientes()`.

### 2.1 Nombres de rol en pantalla

Un solo lugar para traducir el rol, usado por la tabla, el filtro y el formulario:

```php
// app/Models/User.php
public const ROLES = [
    'administrador' => 'Administrador',
    'supervisor'    => 'Coordinador',
    'vendedor'      => 'Asesor',
];

public function rolEnPantalla(): string
{
    return self::ROLES[$this->rol] ?? $this->rol;
}
```

### 2.2 Rutas

| Método y URL | Nombre | Middleware | Acción |
|---|---|---|---|
| `GET /login`, `POST /login` | `login` | `guest` | Breeze (sin cambios en el controlador) |
| `POST /logout` | `logout` | `auth`, `panel` | Breeze |
| `GET /dashboard` | `dashboard` | `auth`, `panel` | Redirige a `clientes.index` si la ruta existe; si no, muestra `dashboard.blade.php` con un saludo |
| `GET /profile` | `profile.edit` | `auth`, `panel` | Solo el formulario de contraseña |
| `PUT /password` | `password.update` | `auth`, `panel` | Breeze `PasswordController@update` con mensajes en español |
| `GET /cuentas` | `cuentas.index` | `auth`, `panel`, `rol:administrador` | Lista con filtro `?rol=` |
| `GET /cuentas/create` | `cuentas.create` | ídem | Formulario vacío |
| `POST /cuentas` | `cuentas.store` | ídem | Crea |
| `GET /cuentas/{cuenta}/edit` | `cuentas.edit` | ídem | Formulario con datos |
| `PUT/PATCH /cuentas/{cuenta}` | `cuentas.update` | ídem | Actualiza |

Se declaran con `Route::resource('cuentas', CuentaController::class)->except(['show', 'destroy'])->parameters(['cuentas' => 'cuenta'])`; `{cuenta}` se enlaza con `User`. No existe `DELETE /cuentas/{cuenta}`: Laravel responde 405 y nada se borra.

Desaparecen: `register`, `password.request`, `password.email`, `password.reset`, `password.store`, `verification.*`, `password.confirm`, `profile.update`, `profile.destroy`. El middleware `verified` deja de usarse.

**Nombres de ruta reservados para el menú** (los specs futuros deben usar estos nombres):

| Entrada del menú | Ruta | Roles | Spec que la crea |
|---|---|---|---|
| Cuentas | `cuentas.index` | administrador | 03 (este) |
| Tratamientos | `productos.index` | administrador | 04 |
| Mi equipo | `equipo.index` | supervisor | 09 |
| Pacientes | `clientes.index` | administrador, supervisor | 05 |
| Planes de tratamiento | `oportunidades.index` | administrador, supervisor | 12 |
| Reporte | `reportes.index` | administrador, supervisor | 14 |

Orden en el menú del administrador: Cuentas, Tratamientos, Pacientes, Planes de tratamiento, Reporte. Del coordinador: Mi equipo, Pacientes, Planes de tratamiento, Reporte.

### 2.3 Middleware

```php
// app/Http/Middleware/CuentaPanel.php  (alias 'panel')
// Va después de 'auth'. Vuelve a leer al usuario de la base en cada petición.
$usuario = $request->user()->fresh();
if (! $usuario || ! $usuario->activo || $usuario->esVendedor()) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('login')->withErrors([
        'email' => 'Su cuenta está desactivada. Comuníquese con el administrador.',
    ]);
}
return $next($request);

// app/Http/Middleware/Rol.php  (alias 'rol', uso rol:administrador o rol:administrador,supervisor)
public function handle(Request $request, Closure $next, string ...$roles)
{
    abort_unless(in_array($request->user()->rol, $roles, true), 403);
    return $next($request);
}
```

Registro en `bootstrap/app.php`:

```php
$middleware->alias([
    'panel' => \App\Http\Middleware\CuentaPanel::class,
    'rol'   => \App\Http\Middleware\Rol::class,
]);
```

### 2.4 `LoginRequest::authenticate()`

Orden de las comprobaciones:

1. `ensureIsNotRateLimited()` (Breeze). Mensaje: "Demasiados intentos. Intente de nuevo en :seconds segundos."
2. `Auth::attempt(...)`. Si falla → `RateLimiter::hit` y error en `email`: **"El correo o la contraseña no son correctos."** (el mismo para correo inexistente y contraseña incorrecta).
3. Con la contraseña correcta, se lee `Auth::user()`:
   - si `rol = 'vendedor'` → `Auth::guard('web')->logout()` y error en `email`: **"Esta cuenta es de asesor. Ingrese desde la aplicación de asesores."**
   - si `activo = 0` → `logout()` y error en `email`: **"Su cuenta está desactivada. Comuníquese con el administrador."**
   - (si un vendedor además está desactivado, gana el mensaje de asesor: es el primero que se comprueba.)
4. `RateLimiter::clear` y sigue el flujo de Breeze (regenera la sesión y va a `dashboard`).

`messages()`: `email.required` → "Escriba su correo.", `email.email` → "Escriba un correo válido.", `password.required` → "Escriba su contraseña.".

### 2.5 `CuentaRequest`

`authorize()` devuelve `true` (la ruta ya pasa por `rol:administrador`). `$cuenta = $this->route('cuenta')` es `null` al crear.

| Campo | Reglas | Mensajes |
|---|---|---|
| `name` | `required`, `string`, `max:255` | "Escriba el nombre." / "El nombre no puede pasar de 255 caracteres." |
| `email` | `required`, `string`, `email`, `max:255`, `Rule::unique('users', 'email')->ignore($cuenta)` | "Escriba el correo." / "Escriba un correo válido." / "Ya existe una cuenta con ese correo." |
| `password` | al crear: `required`; al editar: `nullable`; siempre `string`, `min:8`, `confirmed` | "Escriba la contraseña." / "La contraseña debe tener al menos 8 caracteres." / "La confirmación de la contraseña no coincide." |
| `rol` | `required`, `Rule::in(array_keys(User::ROLES))` | "Elija el rol." / "Elija un rol de la lista." |
| `supervisor_id` | `exclude_unless:rol,vendedor`, `required`, `integer`, `Rule::exists('users', 'id')->where('rol', 'supervisor')->where('activo', 1)`; al editar, además `not_in:{$cuenta->id}` | "Elija el coordinador del asesor." / "Elija un coordinador activo de la lista." |
| `activo` | `required`, `boolean` (el formulario envía `1` o `0` desde un selector "Activa / Desactivada") | "Elija el estado." |

Reglas extra en `after()` (solo al editar; cada una agrega un error junto al campo y no se guarda nada):

| Condición | Campo | Mensaje |
|---|---|---|
| La cuenta es la del usuario con sesión y `activo = 0` | `activo` | "No puede desactivar su propia cuenta." |
| La cuenta es la del usuario con sesión y `rol` cambia | `rol` | "No puede cambiar su propio rol." |
| La cuenta es `supervisor`, tiene asesores (`vendedores()->exists()`, activos o no) y `activo = 0` | `activo` | "No puede desactivar a un coordinador que tiene asesores. Primero cámbielos de coordinador." |
| La cuenta es `supervisor`, tiene asesores y `rol` cambia | `rol` | "No puede cambiar el rol de un coordinador que tiene asesores. Primero cámbielos de coordinador." |
| La cuenta es `vendedor`, tiene pacientes (`clientes()->exists()`) y `rol` cambia | `rol` | "No puede cambiar el rol de un asesor que tiene pacientes. Primero reasígnelos o libérelos." |

Desactivar a un asesor con pacientes **sí** se permite: sus pacientes se quedan asignados (sección 5 de la especificación).

### 2.6 `CuentaController`

- `index`: `User::with('supervisor')`, filtro opcional `?rol=` (si no está en `User::ROLES` se ignora), orden por `name`, sin paginación. Columnas: Nombre, Correo, Rol (`rolEnPantalla()`), Coordinador (nombre o "—"), Estado ("Activa" / "Desactivada"), botón "Editar". Botón "Nueva cuenta". Mensaje de éxito en `session('status')`.
- `create` / `edit`: misma vista de formulario (`cuentas/form.blade.php` incluida por `create` y `edit`). El selector de coordinador ofrece `User::where('rol','supervisor')->where('activo',1)->orderBy('name')`, excluyendo la cuenta que se edita. El selector solo se muestra si el rol elegido es Asesor (un `<script>` pequeño lo oculta; si no hay JavaScript, se ve siempre y el servidor descarta el valor). La contraseña nunca se vuelve a llenar; al editar, el texto de ayuda dice "Déjela vacía para conservar la actual.".
- `store`: `User::create($datos)` con `supervisor_id = null` si `rol <> 'vendedor'`. La contraseña la convierte en hash el cast `hashed` del modelo (bcrypt, `$2y$`). Redirige a `cuentas.index` con "La cuenta se creó correctamente.".
- `update`: igual, pero si `password` viene vacía se quita de los datos y el hash se conserva. Redirige con "La cuenta se actualizó correctamente.".

### 2.7 Vistas

| Archivo | Cambio |
|---|---|
| `resources/views/cuentas/index.blade.php`, `create.blade.php`, `edit.blade.php`, `form.blade.php` | Nuevas. Todo con `{{ }}`, nunca `{!! !!}`; `old()` en cada campo menos la contraseña; `@error` junto a cada campo |
| `resources/views/layouts/navigation.blade.php` | Menú por rol de la sección 2.2 (escritorio y móvil), cada enlace dentro de `@if (Route::has(...))`; el logo apunta a `dashboard`; "Perfil" y "Cerrar sesión" en español |
| `resources/views/auth/login.blade.php` | Sin enlace "Forgot your password?"; textos en español (Correo, Contraseña, Recordarme, Iniciar sesión) |
| `resources/views/welcome.blade.php` | Sin enlace "Register"; "Log in" → "Iniciar sesión" |
| `resources/views/profile/edit.blade.php` | Solo incluye `update-password-form` |
| `resources/views/profile/partials/update-password-form.blade.php` | Textos en español |
| `resources/views/dashboard.blade.php` | Saludo en español (solo se ve mientras no exista `clientes.index`) |

### 2.8 Archivos que se borran

- Controladores `Auth/`: `RegisteredUserController`, `PasswordResetLinkController`, `NewPasswordController`, `EmailVerificationNotificationController`, `EmailVerificationPromptController`, `VerifyEmailController`, `ConfirmablePasswordController`.
- `app/Http/Requests/ProfileUpdateRequest.php`; métodos `update` y `destroy` de `ProfileController`.
- Vistas: `auth/register`, `auth/forgot-password`, `auth/reset-password`, `auth/verify-email`, `auth/confirm-password`, `profile/partials/delete-user-form`, `profile/partials/update-profile-information-form`.
- Pruebas de Breeze: `RegistrationTest`, `PasswordResetTest`, `EmailVerificationTest`, `PasswordConfirmationTest`; `ProfileTest` se reduce a "la página de perfil se muestra".

---

## 3. Plan de implementación

Cada paso deja el panel funcionando y `php artisan test` en verde.

1. **Limpiar Breeze.** Borrar lo de la sección 2.8, quitar sus rutas de `routes/auth.php` y `routes/web.php`, quitar `verified` de `/dashboard`, quitar los enlaces (bienvenida, login, perfil) y ajustar `ProfileTest` y `AuthenticationTest`.
2. **Login por puerta.** Mensajes y comprobaciones de rol y estado en `LoginRequest` (sección 2.4); mensajes en español del cambio de contraseña en `PasswordController`. Pruebas `tests/Feature/LoginPanelTest.php`.
3. **Middleware.** `CuentaPanel` y `Rol`, alias en `bootstrap/app.php`, y `panel` en todas las rutas con `auth` (perfil, contraseña, logout, dashboard). Pruebas de sesión que se pierde.
4. **Cuentas.** `User::ROLES` y `rolEnPantalla()`, `CuentaRequest`, `CuentaController`, rutas con `rol:administrador` y vistas `cuentas/`. Pruebas `tests/Feature/CuentasTest.php`.
5. **Menú y dashboard.** Menú por rol con `Route::has` y redirección condicional de `/dashboard`. Prueba de que el coordinador no ve "Cuentas".
6. **Comprobación final.** `php artisan route:list` sin rutas de registro, restablecer ni borrar cuenta; `git grep -n "register\|password.request\|profile.destroy" laravel/resources laravel/routes` vacío; recorrido manual de los criterios.

**Pruebas:** corren contra MySQL `crm_test` como en el spec 01, sembrando `VerificacionSeeder` en `setUp()`. Como la contraseña pública no puede escribirse fuera de los seeders, cada prueba que inicia sesión primero cambia el hash de la cuenta a una clave propia de la prueba (`Hash::make('clave-de-prueba-03')`).

---

## 4. Criterios de aceptación

Salen de los Dado/Cuando/Entonces de L2. Semilla: `php artisan migrate:fresh --seeder=VerificacionSeeder`.

- **CA1 · Sin registro.** Dado el panel encendido, cuando se abre `/register`, entonces responde **404** y la página de inicio de sesión no muestra enlace de registro. Lo mismo para `/forgot-password`, `/reset-password/x`, `/verify-email` y `/confirm-password`; la bienvenida no muestra "Register" y el login no muestra "olvidé mi contraseña".
- **CA2 · Perfil reducido.** Dado Alicia con sesión, cuando abre `/profile`, entonces solo ve el formulario de cambio de contraseña (sin nombre, correo ni "eliminar cuenta"), y un `DELETE /profile` o `PATCH /profile` enviado a mano responde 405 sin cambiar la base.
- **CA3 · Crear asesor.** Dado Alicia con sesión, cuando crea una cuenta de rol Asesor con coordinador Sergio, entonces aparece en la lista y en la base tiene `rol = 'vendedor'`, `supervisor_id` de Sergio, `activo = 1` y un `password` que empieza con `$2y$`.
- **CA4 · Coordinador obligatorio y válido.** Dado Alicia creando un Asesor, cuando lo envía sin coordinador, entonces el formulario vuelve con los datos escritos (sin la contraseña) y "Elija el coordinador del asesor." junto al campo; y cuando fuerza el id de Valeria como `supervisor_id`, entonces aparece "Elija un coordinador activo de la lista." y no se crea la cuenta.
- **CA5 · Coordinador vacío para otros roles.** Dado Alicia creando una cuenta de rol Coordinador, cuando envía a mano `supervisor_id` de Sergio, entonces la cuenta se crea con `supervisor_id = NULL`.
- **CA6 · Contraseña al crear.** Dado Alicia creando una cuenta con contraseña de 5 caracteres, o con una confirmación distinta, entonces el mensaje aparece junto a la contraseña, los demás campos conservan lo escrito y la contraseña queda vacía.
- **CA7 · Contraseña opcional al editar.** Dado Alicia editando a Diego, cuando guarda sin escribir contraseña, entonces el `password` de Diego en la base no cambia (y él sigue iniciando sesión en la API).
- **CA8 · Correo único.** Dado Alicia creando una cuenta con el correo de Valeria, entonces ve "Ya existe una cuenta con ese correo."; y editando a Valeria sin cambiar su correo, se guarda sin ese error.
- **CA9 · El administrador no se toca a sí mismo.** Dado Alicia editando su propia cuenta, cuando elige Desactivada, o rol Coordinador, entonces el formulario vuelve con "No puede desactivar su propia cuenta." o "No puede cambiar su propio rol." y la base no cambia.
- **CA10 · Coordinador con asesores.** Dado Alicia editando a Sergio (tiene a Valeria y Diego), cuando lo desactiva o le cambia el rol, entonces ve el mensaje correspondiente de la sección 2.5 y la base no cambia; y Sandra (tiene a Carlos e Iván, aunque Iván esté desactivado) tampoco puede desactivarse.
- **CA11 · Asesor con pacientes.** Dado Alicia editando a Valeria (3 pacientes), cuando le cambia el rol a Coordinador, entonces ve "No puede cambiar el rol de un asesor que tiene pacientes. Primero reasígnelos o libérelos." y la base no cambia; cuando solo la desactiva, se guarda y sus 3 pacientes siguen con ella.
- **CA12 · No se borran cuentas.** Dado el panel, no hay botón ni ruta para borrar cuentas (`php artisan route:list --path=cuentas` no muestra DELETE); un `DELETE /cuentas/{id}` enviado a mano no borra nada.
- **CA13 · Lista y filtro.** Dado Alicia, cuando abre `/cuentas`, entonces ve las 7 cuentas con su rol en pantalla (Administrador, Coordinador, Asesor), el coordinador de cada asesor y a Iván como "Desactivada"; con `?rol=vendedor` ve solo a Valeria, Diego, Carlos e Iván.
- **CA14 · Asesor no entra al panel.** Dado el correo de Valeria y la contraseña correcta, cuando intenta iniciar sesión, entonces ve "Esta cuenta es de asesor. Ingrese desde la aplicación de asesores." y sigue en el login, sin sesión (`assertGuest`).
- **CA15 · Cuenta desactivada no entra.** Dado el correo de Iván (desactivado) y la contraseña correcta, cuando intenta iniciar sesión, entonces ve "Su cuenta está desactivada. Comuníquese con el administrador." y no hay sesión.
- **CA16 · Mismo mensaje para credenciales malas.** Dado un correo inexistente, y por separado el de Sergio con una contraseña incorrecta (y el de Valeria con contraseña incorrecta), entonces los tres muestran "El correo o la contraseña no son correctos.".
- **CA17 · Sesión que se pierde.** Dado Sergio con sesión abierta, cuando Alicia lo desactiva (en la prueba: `UPDATE` directo, porque Sergio tiene asesores y el panel no lo permite; a mano: con Sandra tras mover a Carlos e Iván) y Sergio recarga cualquier página, entonces queda fuera del panel, en `/login`, con el mensaje de cuenta desactivada. Lo mismo si su rol pasa a `vendedor` en la base.
- **CA18 · Páginas por rol.** Dado Sergio con sesión, cuando abre a mano `/cuentas`, `/cuentas/create` o `/cuentas/{id}/edit`, o envía `POST /cuentas`, entonces el panel responde **403** y el menú no muestra "Cuentas" ni "Tratamientos". (El 403 de `/productos` se comprueba en el spec 04.)
- **CA19 · Menú y entrada.** Dado Alicia con sesión, el menú muestra "Cuentas" (y las demás entradas de la sección 2.2 cuyas rutas ya existan); dado Sergio, muestra "Mi equipo", "Pacientes", "Planes de tratamiento" y "Reporte" en cuanto existan sus rutas. Después de iniciar sesión, `/dashboard` lleva a `clientes.index` si existe, o muestra el saludo si todavía no.
- **CA20 · XSS.** Dado una cuenta creada con nombre `<script>alert(1)</script>`, cuando se abre `/cuentas`, entonces el nombre se ve como texto y no se ejecuta (`git grep -n "{!!" laravel/resources/views/cuentas` vacío).
- **CA21 · Pruebas.** `cd laravel && php artisan test` termina en verde, incluidas `LoginPanelTest` y `CuentasTest`.

---

## 5. Decisiones tomadas y descartadas

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| Mensaje de asesor: "Esta cuenta es de asesor. Ingrese desde la aplicación de asesores." | "Su cuenta es de asesor. Ingrese desde la aplicación del asesor." (propuesta inicial) | La especificación (L2) manda; elegido por la integrante para no cambiarla |
| Comprobar rol y estado en `LoginRequest` después de `Auth::attempt` y cerrar sesión | Agregar `rol` y `activo` a las credenciales de `attempt` | Con las credenciales extra el asesor y la cuenta desactivada verían "correo o contraseña incorrectos"; L2 pide mensajes distintos solo con la contraseña correcta, igual que la API |
| Middleware `CuentaPanel` que vuelve a leer al usuario en cada petición | Confiar en la sesión | La sesión guarda al usuario de cuando entró; sin releer, una cuenta desactivada seguiría dentro (sección 1.2). El nombre sigue `docs/plan.md` (en `docs/reparto.md` aparece como `SoloPanel`) |
| Mensaje de cuenta desactivada también cuando la cuenta pasó a `vendedor` | Un mensaje distinto para el cambio de rol | L2 lo dice así ("con el mensaje de cuenta desactivada") |
| `Rol` responde 403 | 404 | Es una sección que su rol no usa, no un dato ajeno (L2 lo distingue de S5) |
| Coordinador con asesores no se desactiva ni cambia de rol; asesor con pacientes no cambia de rol | Permitirlo | El CHECK obliga a vaciar `supervisor_id`, y quedarían pacientes con un `vendedor_id` que no es asesor o asesores sin coordinador que los vea. Cierra la pregunta abierta de L7 (decidido por la integrante) |
| Desactivar un asesor con pacientes sí se permite | Exigir liberarlos antes | Sección 5 de la especificación: se quedan asignados hasta que el coordinador los reasigne |
| `supervisor_id` con `exclude_unless:rol,vendedor` y `null` en el controlador | Validarlo siempre | Para otros roles se ignora lo que mande el formulario y se guarda vacío (L2, y `chk_users_supervisor` lo exige) |
| `not_in` con el id de la cuenta editada | Solo `exists` de supervisor activo | Sin esto, un coordinador sin asesores pasado a Asesor podría quedar como su propio coordinador: `exists` lo aceptaría porque en la base todavía es supervisor |
| Perfil solo con cambio de contraseña; se quitan verificación de correo y confirmar contraseña | Dejar edición de nombre y correo | Las cuentas solo las cambia el administrador; el correo es el usuario de la API y debe seguir siendo único (decidido por la integrante) |
| Menú con `Route::has` y `/dashboard` que redirige a `clientes.index` si existe | Páginas provisionales "En construcción" o agregar los enlaces en cada spec | Los specs futuros solo crean su ruta con el nombre reservado y el menú aparece solo (decidido por la integrante) |
| "Planes de tratamiento" en el menú | "Planes" | Criterio de la sección 1.3 de la especificación |
| Estado con selector Activa/Desactivada (`1`/`0`) | Casilla de verificación | Una casilla desmarcada no se envía y obliga a adivinar el valor; con el selector `activo` siempre llega y se valida con `required|boolean` |
| Mensajes en `messages()` de cada FormRequest | `lang/es/validation.php` | Ese archivo es del spec 16; aquí basta con que lo que ve este spec esté en español |
| Sin paginación en `/cuentas` | Paginar | Son pocas cuentas (7 en la semilla de verificación, 10 con la del sector) |
| Pruebas que cambian el hash antes de iniciar sesión | Usar la contraseña pública | La contraseña pública solo puede escribirse en seeders, README y Postman |

---

## 6. Riesgos

- **Hay que anotar la decisión en la especificación.** La regla "coordinador con asesores / asesor con pacientes" cierra la pregunta abierta del final de L7. Antes de `/spec-impl`, la integrante A debe pasarla a L2 (texto y criterios) y quitar la pregunta abierta, para que la especificación siga siendo la fuente de verdad.
- **Carrera entre la edición de la cuenta y una asignación.** `CuentaRequest` comprueba "asesor con pacientes" fuera de una transacción; si un coordinador le asigna un paciente al mismo tiempo que el administrador le cambia el rol, podría colarse. Probabilidad muy baja (solo hay un administrador). La asignación del spec 07 bloquea la fila del vendedor con `FOR UPDATE` y debe comprobar ahí que la cuenta sigue siendo un vendedor activo; con eso el caso queda cubierto del lado de la asignación.
- **"Recordarme" con una cuenta rechazada.** `Auth::attempt` con `remember` encola la cookie de recordar; `logout()` la olvida. Hay que verificar en la prueba de CA14 que tras el rechazo no queda sesión ni cookie útil.
- **Rutas de Breeze que otros archivos usan.** Si queda un `route('register')` o `route('password.request')` en alguna vista, la página falla con error 500. El paso 6 lo busca con `git grep`.
- **Pruebas de Breeze rotas.** `AuthenticationTest` usa la fábrica (por omisión administrador activo, así que sigue sirviendo), pero las pruebas de registro, restablecer, verificar y confirmar deben borrarse junto con su función.
- **Rutas de specs futuros con otro nombre.** Si el spec 04, 05, 09, 12 o 14 nombra su ruta distinto de la tabla de la sección 2.2, el enlace no aparece en el menú. Esos specs deben usar los nombres reservados aquí y aplicar `auth`, `panel` y el `rol:` que corresponda.
