# 16 · Validación en el servidor

**Estado:** Aprobado
**Depende de:** 03-cuentas-roles (`CuentaRequest`, `LoginRequest`, `PasswordController`), 04-catalogo (`ProductoRequest`), 05-pacientes (`ClienteRequest`, `NitValido`, `TelefonoValido`, `Nit`, `Telefono`), 07-asignacion (`AsignarRequest`, `Cartera`), 12-planes y 14-reporte (listas con paginación y filtros)
**Fecha:** 2026-11-08
**Dueña:** A
**Rama:** `spec-16-validacion`
**Objetivo:** que todo el panel valide en el servidor, con mensajes en español que tratan de usted junto a cada campo, sin que ningún texto en inglés llegue a la pantalla.

**Requisitos que cumple:** L7 de `docs/especificacion.md` (las cuatro viñetas y sus cinco criterios). Toca la sección 10 (casos compartidos de NIT y teléfono) solo para confirmar que el panel la usa en todos los lugares.

---

## 1. Alcance

**Incluye**
- `php artisan lang:publish` y la carpeta `laravel/lang/es/` con `auth.php`, `validation.php`, `passwords.php` y `pagination.php`, traducidos al español de usted, con todas las claves de su par en `lang/en/`.
- `laravel/lang/es.json` con las cadenas sueltas que el framework pasa por `__()` y que hoy salen en inglés: páginas de error (403, 404, 419, 429, 500, 503) y la paginación de Tailwind ("Showing … to … of … results", "Previous", "Next").
- `APP_LOCALE=es` y `APP_FALLBACK_LOCALE=es` como valores por defecto en `config/app.php`, en `.env.example` (hoy tiene `APP_LOCALE` repetido: `en` en la línea 7 y `es` en la 68, y phpdotenv se queda con el primero) y en `phpunit.xml`.
- `novalidate` en los formularios del panel (ver decisión D3), para que el mensaje lo dé el servidor y no el navegador.
- `CuentaController`: el correo repetido que se cuela entre la regla `unique` y el `INSERT`/`UPDATE` (1062 en `users_email_unique`) vuelve junto al correo, no como página de error.
- `bail` en las reglas de `CuentaRequest`, igual que en `ClienteRequest` y `ProductoRequest` (un solo mensaje por campo).
- Prueba `tests/Feature/ValidacionTest.php` y anotar las filas de L7 en `docs/verificacion.md`.

**No incluye**
- Cambiar reglas o mensajes ya aprobados en los specs 03, 04, 05 y 07. Sus `messages()` se quedan: `lang/es/validation.php` es la red para lo que no cubren.
- Los filtros de las listas (`q`, `asesor`, `etapa`, `rol`, `mes`): son de lectura y ya se decidió que un valor no válido se ignora sin aviso (specs 05, 12 y 14). No reciben datos para guardar.
- La API y la SPA (integrante B y specs de la SPA). La mitad API del criterio 4 (`POST /clientes` con `000-7654321-8` → 409) depende de `api/src/util/nit.js` del spec 06, que todavía no está en `main`.

---

## 2. Revisión del estado actual (hecha al escribir este spec)

### 2.1 `{!! !!}` en las vistas

`grep -rn '{!!' laravel/resources/views` → **ninguno**. Las páginas de error y la paginación que usan `{!! !!}` están en `vendor/` y solo imprimen textos de traducción fijos, no datos del usuario. El spec deja una prueba para que siga así (CA6).

### 2.2 Formularios: `@error` y `old()`

| Formulario | Validación | `@error` junto a cada campo | `old()` | Hallazgo |
|---|---|---|---|---|
| `auth/login` | `LoginRequest` | correo, contraseña | correo; contraseña no | `type="email"` y `required`: el navegador responde antes que el servidor (D3) |
| `profile/partials/update-password-form` | `validateWithBag('updatePassword')` | las tres | ninguno (todas son contraseñas) | Bien |
| `cuentas/form` | `CuentaRequest` | nombre, correo, contraseña, rol, coordinador, estado | todos menos contraseña y confirmación | La confirmación no tiene `@error`, pero su error (`confirmed`) sale en contraseña: correcto. Falta `bail`. `type="email"` y `required` (D3). Correo repetido por carrera → página de error |
| `productos/form` | `ProductoRequest` | nombre, precio, activo, requiere evaluación | todos | `required` (D3) |
| `clientes/form` | `ClienteRequest` | nombre, NIT, teléfono, correo, asesor | todos | `required` (D3) |
| `clientes/_asignar` | `AsignarRequest` (bolsa `asignar{id}`) | asesor, debajo del selector de ese paciente | el selector vuelve al asesor guardado | Bien |
| Liberar, eliminar paciente, activar/desactivar tratamiento, salir | sin datos de entrada | — | — | Mensaje de página con `session('error')`/`status` |

### 2.3 NIT, teléfono y cartera

- El único lugar del panel que crea o edita un paciente es `ClienteController@store` y `@update`; los dos reciben `ClienteRequest`, que aplica `NitValido`, `TelefonoValido` y los repetidos ya normalizados en `after()`. Lo guardado sale de `datos()` (normalizado). `Cartera::crear()` recibe esos datos y no hay otro `Cliente::create` en `app/`. Las semillas escriben datos fijos y ya válidos.
- La cartera máxima se aplica en los dos lugares que ponen asesor: alta con asesor (`Cartera::crear`) y asignar/reasignar (`Cartera::asignar`), los dos con transacción + `lockForUpdate()` sobre `users`, conteo y escritura. Liberar no bloquea `users` (solo resta).
- El 1062 de `clientes_nit_unique` y `clientes_telefono_unique` vuelve junto al campo (`repetidoPorCarrera`).
- **Conclusión:** no hay cambios de código para este punto. El spec lo deja comprobado con pruebas (CA4).

### 2.4 Texto en inglés que hoy llega a la pantalla

- Cualquier regla sin mensaje propio (por ejemplo `email.string` del login) cae en `validation.php` de Laravel: hoy en inglés, porque no existe `lang/` y `config/app.php` usa `en` por defecto.
- Las páginas 403, 404, 419 ("Page Expired" al vencer el token CSRF) y 429 dicen "Forbidden", "Not Found", "Page Expired", "Too Many Requests".
- La paginación de pacientes y planes dice "Showing 1 to 15 of 21 results".

---

## 3. Modelo de datos

**Sin migraciones.** Solo archivos de idioma y configuración.

### 3.1 `lang/es/validation.php`

- Las mismas claves que `lang/en/validation.php` (incluidos los arreglos `between`, `gt`, `lt`, `max`, `min`, `size`, `password`, etc.).
- **Tono:** imperativo de usted cuando se puede pedir algo ("Escriba…", "Elija…", "Indique…"); si no, frase impersonal. Nunca de tú. Ejemplos:

| Clave | Mensaje |
|---|---|
| `required` | `Complete el campo :attribute.` |
| `email` | `Escriba un :attribute válido.` |
| `min.string` | `El campo :attribute debe tener al menos :min caracteres.` |
| `max.string` | `El campo :attribute no puede pasar de :max caracteres.` |
| `unique` | `Ya existe un registro con ese :attribute.` |
| `exists` / `in` | `Elija un :attribute válido de la lista.` |
| `confirmed` | `La confirmación de :attribute no coincide.` |
| `numeric` | `Escriba :attribute como un número.` |
| `boolean` | `Indique un valor válido para :attribute.` |
| `current_password` | `La contraseña no es correcta.` |

- **`attributes`** (nombres en pantalla):

```php
'attributes' => [
    'name' => 'nombre',
    'nombre' => 'nombre',
    'email' => 'correo',
    'correo' => 'correo',
    'password' => 'contraseña',
    'password_confirmation' => 'confirmación de la contraseña',
    'current_password' => 'contraseña actual',
    'nit' => 'NIT',
    'telefono' => 'teléfono',
    'precio' => 'precio',
    'rol' => 'rol',
    'activo' => 'estado',
    'requiere_evaluacion' => 'requiere evaluación',
    'supervisor_id' => 'coordinador',
    'vendedor_id' => 'asesor',
],
```

### 3.2 `lang/es/auth.php`, `passwords.php`, `pagination.php`

- `auth.php`: `failed` = "El correo o la contraseña no son correctos." (el mismo texto de `LoginRequest`), `password` = "La contraseña no es correcta.", `throttle` = "Demasiados intentos. Intente de nuevo en :seconds segundos.".
- `passwords.php`: traducido aunque el panel no tiene restablecer contraseña (spec 03); así no queda inglés si algún día se usa.
- `pagination.php`: `previous` = "&laquo; Anterior", `next` = "Siguiente &raquo;".

### 3.3 `lang/es.json`

```json
{
    "Showing": "Mostrando",
    "to": "a",
    "of": "de",
    "results": "resultados",
    "Pagination Navigation": "Navegación de páginas",
    "Go to page :page": "Ir a la página :page",
    "Unauthorized": "No autorizado",
    "Forbidden": "Acceso denegado",
    "Not Found": "Página no encontrada",
    "Page Expired": "La página expiró. Vuelva a cargarla e intente de nuevo",
    "Too Many Requests": "Demasiadas solicitudes",
    "Server Error": "Error del servidor",
    "Service Unavailable": "Servicio no disponible",
    "Payment Required": "Pago requerido"
}
```

Las claves exactas se copian de `vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views/` y `vendor/laravel/framework/src/Illuminate/Pagination/resources/views/tailwind.blade.php` al implementar.

---

## 4. Plan de implementación

1. `cd laravel && php artisan lang:publish` (crea `lang/en/`).
2. Crear `lang/es/{auth,validation,passwords,pagination}.php` y `lang/es.json` según la sección 3.
3. `config/app.php`: `'locale' => env('APP_LOCALE', 'es')`, `'fallback_locale' => env('APP_FALLBACK_LOCALE', 'es')`. `.env.example`: líneas 7 y 8 con `es`, borrar el `APP_LOCALE=es` repetido de la línea 68. `phpunit.xml`: `<env name="APP_LOCALE" value="es"/>`.
4. `novalidate` en el `<form>` de `auth/login`, `cuentas/create`, `cuentas/edit`, `productos/create`, `productos/edit`, `clientes/create`, `clientes/edit` y `profile/partials/update-password-form`. Los atributos `required`, `maxlength` y `type` se quedan (ayudan al teclado del celular y a la accesibilidad).
5. `CuentaRequest`: `bail` al inicio de cada regla.
6. `CuentaController@store` y `@update`: atrapar `QueryException` con 1062 sobre `users_email_unique` → `back()->withInput()->withErrors(['email' => 'Ya existe una cuenta con ese correo.'])`; cualquier otro error se vuelve a lanzar (mismo patrón que `ProductoController::nombreRepetido`). `withInput()` no devuelve la contraseña (Laravel la excluye por `$dontFlash`).
7. `tests/Feature/ValidacionTest.php` (sección 5).
8. `docs/verificacion.md`: filas de L7.

---

## 5. Criterios de aceptación

Salen de los Dado/Cuando/Entonces de L7. Cada uno lleva su prueba en `ValidacionTest`.

1. **Vacío, en español y de usted.** Dado cualquier formulario del panel (login, cambiar contraseña, cuenta, tratamiento, paciente, asignar), cuando se envía vacío, entonces cada campo obligatorio tiene un mensaje en español que trata de usted (por ejemplo, "Escriba el nombre del paciente.") y ningún mensaje coincide con un texto de `lang/en/validation.php`. *Prueba:* un caso por formulario; además `app()->getLocale() === 'es'` y las claves de cada archivo de `lang/es/` son las mismas que las de `lang/en/`.
2. **Correo no válido en el alta de paciente.** Dado el alta con correo `no-es-correo` y el resto válido, cuando se envía, entonces vuelve con nombre, NIT y teléfono escritos (`assertSessionHasInput`) y "Escriba un correo válido." en `correo`.
3. **Contraseña corta en el alta de cuenta.** Dado el alta con contraseña de 5 caracteres, cuando se envía, entonces el error está en `password`, los demás campos conservan lo escrito y `password` no está en la entrada devuelta (`old('password')` vacío).
4. **Las dos puertas.** Dado un paciente con teléfono `55559876` guardado como lo guarda la API, cuando Alicia crea uno con `+502 5555 9876`, entonces ve "Ya existe un paciente con ese teléfono."; y un NIT `76543218` guardado en el panel queda en la base como `76543218`, que es lo que la API compara al recibir `000-7654321-8`. *La respuesta 409 de la API se comprueba con Postman cuando el spec 06 esté en `main`.*
5. **Todo dato de entrada pasa por validación.** `git grep -n "validate\|Request" laravel/app/Http/Controllers` muestra un FormRequest o `validate` en cada acción que guarda (`store`, `update`, `asignar`, `password.update`, `login`). Las acciones sin datos (`liberar`, `destroy`, `cambiarEstado`, `logout`) y los filtros de lectura quedan anotados en `docs/verificacion.md`.
6. **Sin `{!! !!}` en las vistas.** Una prueba recorre `resources/views` y falla si algún archivo contiene `{!!`.
7. **Páginas de error y paginación en español.** `GET /clientes/999999` como Alicia responde 404 con "Página no encontrada" y sin "Not Found"; la lista de pacientes muestra "Mostrando 1 a 15 de 21 resultados".
8. **Correo repetido por carrera.** Revisión de código: el 1062 de `users_email_unique` vuelve como error junto al correo (no se puede reproducir con una prueba sin dos conexiones; se deja comentado en el controlador).

---

## 6. Decisiones tomadas y descartadas

- **D1. Se quedan los `messages()` de cada FormRequest.** Ya están aprobados y son más precisos ("Escriba el nombre del paciente.") que un mensaje genérico. `lang/es/validation.php` cubre todo lo demás. *Descartado:* mover todos los mensajes a `validation.php` con `custom` (más cambios, mismo resultado).
- **D2. `fallback_locale` también en `es`.** Si faltara una clave en español, se vería la clave (`validation.algo`) en vez de un texto en inglés, y la prueba de claves de CA1 lo detecta antes. *Descartado:* dejar `en` como respaldo.
- **D3. `novalidate` en los formularios.** Con `required` y `type="email"`, el navegador detiene el envío y muestra su propio globo, en el idioma del navegador (a menudo inglés) y sin pasar por el servidor. Con `novalidate` el formulario siempre llega al servidor y los mensajes salen de L7, junto al campo. Es lo mismo que ya se decidió para el correo del paciente (`type="text"`) en el spec 05. *Descartado:* quitar `required` y `type` (se pierden el teclado de correo en el celular y la indicación de obligatorio para lectores de pantalla).
- **D4. Se conserva `lang/en/`.** La deja `lang:publish` y sirve para la prueba que compara claves. *Descartado:* borrarla.
- **D5. Los filtros de las listas no usan `validate`.** Ya se decidió que un filtro no válido se ignora sin aviso; un `validate` devolvería al usuario con un error en una pantalla de solo lectura.

## 7. Riesgos

- **Mitad API del CA4 pendiente.** Si `api/src/util/nit.js` o `telefono.js` (integrante B) no normalizan igual que `App\Support\Nit` y `App\Support\Telefono`, las dos puertas no darían el mismo resultado. Mitigación: los casos de la sección 10 son la referencia de las dos; revisar el PR del spec 06 con `/revisa`.
- **Claves del framework que cambian.** Si una actualización de Laravel agrega claves a `validation.php`, la prueba de CA1 falla hasta copiarlas a `lang/es/`. Es el comportamiento buscado.
- **`.env` de cada integrante.** Quien ya tiene `.env` con `APP_LOCALE=en` lo verá en inglés hasta corregirlo a mano. Se avisa en el PR.
