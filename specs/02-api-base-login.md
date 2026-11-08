# 02 · Base de la API e inicio de sesión

**Estado:** aprobado
**Depende de:** SPEC 01 (`01-esquema-y-semilla`: tabla `users` con `rol`, `supervisor_id`, `activo` y la semilla de verificación)
**Fecha:** 2026-11-08
**Dueña:** B
**Rama:** `spec-02-api-base-login`
**Objetivo:** dejar la API de Express conectada a MySQL, con errores siempre en JSON, CORS de origen exacto, inicio de sesión con JWT y el middleware que protege las demás rutas.

**Requisitos que cumple:** A1 y A2 completos; la base de A7 (400 por JSON mal formado, 404 de ruta, 500 genérico, `:id` inválido → 404 con `idDeRuta`). De la sección 9: S1 en la parte de la API, el arranque de S2, S6 completo y S4 en las respuestas de login y `/yo`. Contrato: secciones 4.1, 4.2, `POST /auth/login` y `GET /yo` de la sección 4.

---

## 1. Alcance

**Incluye**
- `api/.env.example`: se cambia `CORS_ORIGEN` por `CORS_ORIGIN`, como en S2 y S6.
- 10 archivos nuevos en `api/src` (ES modules): `servidor.js`, `app.js`, `config.js`, `db.js`, `middleware/auth.js`, `middleware/errores.js`, `rutas/auth.js`, `rutas/yo.js`, `util/errores.js`, `util/validar.js`.
- `POST /api/auth/login` y `GET /api/yo`.
- `docs/api.postman_collection.json` (Postman v2.1) con las variables `base` y `token` y la carpeta "Sesión".

**No incluye**
- Rutas de clientes, actividades, oportunidades, productos y reportes (specs 06, 08, 11, 13, 15).
- `src/util/nit.js`, `telefono.js` y `fechas.js` (spec 06 y 08).
- Conversión del error 1062 a 409: la hace cada ruta, con su propio mensaje (spec 06 en adelante). Aquí solo se crea el atajo `conflicto()`.
- Revisión de todas las respuestas sin llaves internas, el resto de la colección de Postman y `pruebas/simultaneas.js` (spec 23).
- Pruebas automáticas con `node --test`: este spec se verifica con Postman, curl y SQL (decisión de la integrante).
- Límite de intentos fallidos de login (reto opcional de `docs/reparto.md`, sección 8).
- `README.md`.
- Ninguna dependencia nueva: solo `express`, `mysql2`, `jsonwebtoken`, `bcryptjs` y `cors`, que ya están en `package.json`.

---

## 2. Modelo de datos

Este spec no crea tablas ni columnas. La API nunca ejecuta `CREATE` ni `ALTER`. Solo lee `users` (SPEC 01):

```sql
-- Login
SELECT id, name, email, password, rol, activo FROM users WHERE email = ? LIMIT 1
-- Middleware, en cada petición
SELECT id, name, email, rol, activo FROM users WHERE id = ? LIMIT 1
```

### 2.1 Variables de entorno (`api/.env.example`)

| Variable | Ejemplo | Regla en `config.js` |
|---|---|---|
| `PORT` | `3000` | opcional, 3000 por omisión |
| `DB_HOST` | `127.0.0.1` | obligatoria |
| `DB_PORT` | `3306` | opcional, 3306 por omisión |
| `DB_USER` | `crm` | obligatoria |
| `DB_PASSWORD` | _(vacío)_ | puede ir vacía |
| `DB_NAME` | `crm_dental` | obligatoria |
| `JWT_SECRET` | _(vacío)_ | obligatoria, al menos 32 caracteres |
| `JWT_EXPIRA` | `8h` | obligatoria |
| `CORS_ORIGIN` | `http://127.0.0.1:5500` | obligatoria |
| `CLAVE_SEMILLA` | _(vacío)_ | no la lee `config.js`; es para `pruebas/simultaneas.js` (spec 23) |

Si falta una obligatoria, o si `JWT_SECRET` es corto, `config.js` muestra en la consola qué variable falla y termina con `process.exit(1)`. Nunca imprime el valor del secreto.

### 2.2 Objetos que viajan

```js
// Cuerpo del token (jwt.sign({}, JWT_SECRET, { algorithm: 'HS256', subject: String(id), expiresIn: JWT_EXPIRA }))
{ sub: '2', iat: 1762560000, exp: 1762588800 }

// req.usuario, lo deja el middleware; también es la respuesta de GET /api/yo
{ id: 2, nombre: 'Valeria López', correo: '<correo de la semilla>' }

// Respuesta 200 de POST /api/auth/login
{ token: '<jwt>', usuario: { id, nombre, correo } }

// ErrorHttp (util/errores.js)
class ErrorHttp extends Error { status; mensaje; errores }  // errores: { campo: 'mensaje' } o undefined
```

### 2.3 Funciones exportadas

| Archivo | Exporta | Comportamiento |
|---|---|---|
| `config.js` | objeto `config` | `{ puerto, db: { host, port, user, password, database }, jwt: { secreto, expira }, corsOrigin }` |
| `db.js` | `consulta(sql, parametros)` | `pool.execute(sql, parametros)` y devuelve las filas |
| `db.js` | `transaccion(async (conexion) => {...})` | `getConnection` → `beginTransaction` → la función → `commit` → devuelve su resultado. Si algo falla: `rollback` y vuelve a lanzar el error. Siempre `release` en `finally` |
| `db.js` | `probarConexion()` | `SELECT 1`. La usa `servidor.js` |
| `util/errores.js` | `ErrorHttp`, `noEncontrado()`, `conflicto(mensaje)`, `invalido(errores)`, `peticionMala(mensaje)` | 404 "No se encontró el recurso.", 409, 422 `{ errores }`, 400 |
| `util/validar.js` | `exigirCuerpo(req)` | si `req.body` no es un objeto (undefined, null o arreglo) lanza 400 "Falta el cuerpo de la petición." |
| `util/validar.js` | `idDeRuta(valor)` | devuelve el número si el texto es `^[1-9][0-9]*$` y es un entero seguro; si no, lanza `noEncontrado()` |
| `util/validar.js` | `texto(valor, min, max)` | si es string, lo recorta; devuelve el texto si su largo está entre `min` y `max`, si no `null` |

Pool de `db.js`: `mysql2/promise`, `decimalNumbers: true`, `timezone: 'Z'`, `connectionLimit: 10`. En el evento `connection` del pool se ejecuta `SET time_zone = '+00:00'`. Todo el SQL usa parámetros `?` y ningún texto SQL se arma con `${…}` ni con `+`.

---

## 3. Comportamiento

### 3.1 `app.js` (orden de los middleware)

1. `cors({ origin: [config.corsOrigin], methods: ['GET','POST','PATCH','DELETE'], allowedHeaders: ['Content-Type','Authorization'] })`. El origen va en un arreglo de un solo elemento (ver decisiones y riesgos). Nunca `*`, `true` ni una expresión regular.
2. `express.json()`.
3. Router `/api`:
   - `/auth` → `rutas/auth.js`, sin token.
   - `middleware/auth.js` para todo lo que sigue.
   - `/yo` → `rutas/yo.js`.
4. Ruta no encontrada → 404 `{ "error": "Ruta no encontrada." }`.
5. `middleware/errores.js`.

`app.js` exporta la app sin escuchar. `servidor.js` importa `config` (que ya validó el entorno), llama a `probarConexion()` y luego a `app.listen(config.puerto)`. Si MySQL no responde, muestra "No se pudo conectar a MySQL: <código>" y termina con `process.exit(1)`.

### 3.2 `middleware/errores.js`

| Error | Respuesta |
|---|---|
| `err.type === 'entity.parse.failed'` (JSON mal formado) | 400 `{ "error": "El cuerpo de la petición no es JSON válido." }` |
| `ErrorHttp` con `errores` | `status` y `{ errores }` |
| `ErrorHttp` sin `errores` | `status` y `{ error: mensaje }` |
| Cualquier otro | `console.error(err)` y 500 `{ "error": "Ocurrió un error inesperado. Intente de nuevo más tarde." }` |

Si las cabeceras ya se enviaron, pasa el error con `next(err)`. Nunca responde HTML.

### 3.3 `POST /api/auth/login` (`rutas/auth.js`)

Se revisa en este orden:

1. `exigirCuerpo(req)` → 400 si no hay cuerpo.
2. `correo = texto(body.correo, 1, 255)`. Si es `null` → `errores.correo = "Escriba su correo."`.
3. `clave`: debe ser string de largo 1 o más, sin recortar. Si no → `errores.clave = "Escriba su contraseña."`.
4. Si hay errores → 422 con los dos campos que fallen.
5. `SELECT … WHERE email = ?` con el correo recortado. La intercalación de MySQL ya ignora mayúsculas.
6. Si no existe el usuario: `bcryptjs.compare(clave, HASH_FALSO)` y luego 401 "Correo o contraseña incorrectos.". `HASH_FALSO` es una constante bcrypt `$2y$` con el mismo costo que los hashes de Laravel (12). Es el hash de un texto aleatorio que no es la contraseña pública.
7. Si `bcryptjs.compare(clave, password)` es falso → 401 con el mismo cuerpo.
8. Si `rol !== 'vendedor'` → 403 "Esta cuenta no es de asesor. Use el panel de la clínica.".
9. Si `activo` es 0 → 403 "Su cuenta está desactivada. Comuníquese con el administrador.".
10. 200 `{ token, usuario: { id, nombre: name, correo: email } }`.

Con 401 o 403 no se firma ningún token. La API nunca llama a `bcryptjs.hash`, `hashSync` ni `genSalt`.

### 3.4 `middleware/auth.js`

1. Sin cabecera `Authorization`, sin el prefijo `Bearer ` o con el token vacío → 401 "Falta el token de acceso.".
2. `jwt.verify(token, config.jwt.secreto, { algorithms: ['HS256'] })`. Si lanza, o si `sub` no es un entero positivo → 401 "El token no es válido o ya venció.".
3. `SELECT … WHERE id = ?` **en cada petición**. Si no existe, `activo = 0` o `rol !== 'vendedor'` → 401 "Su cuenta ya no tiene acceso. Ingrese de nuevo.".
4. `req.usuario = { id, nombre, correo }` y `next()`.

Un error de la base dentro del middleware llega a `errores.js` como 500 genérico, no como 401.

### 3.5 `GET /api/yo` (`rutas/yo.js`)

200 con `req.usuario`. No consulta la base otra vez: el middleware ya la leyó.

---

## 4. Plan de implementación

Cada paso deja la API arrancable.

1. **Entorno.** En `api/.env.example`, cambiar `CORS_ORIGEN` por `CORS_ORIGIN`. Crear `config.js` (sección 2.1). La integrante renombra la variable en su `api/.env` local; el agente no lee `.env`. Prueba: `node --env-file=.env src/config.js` con `JWT_SECRET=corto` termina con el mensaje.
2. **Errores y app.** `util/errores.js`, `middleware/errores.js` y `app.js` sin rutas (CORS, `express.json()`, 404, errores). `servidor.js` con `app.listen`. Prueba: `npm run dev` y `curl localhost:3000/api/nada` → 404 JSON.
3. **Base de datos.** `db.js` (pool, `SET time_zone`, `consulta`, `transaccion`, `probarConexion`). `servidor.js` llama a `probarConexion()` antes de escuchar. Prueba: con MySQL apagado no arranca; encendido, sí.
4. **Login.** `util/validar.js` y `rutas/auth.js` (sección 3.3), montado en `/api/auth`. Generar `HASH_FALSO` una sola vez, fuera del repositorio, con costo 12 (por ejemplo, `Hash::make` de un texto aleatorio en `php artisan tinker`), y pegarlo como constante. Prueba: login de Valeria → 200.
5. **Middleware y `/yo`.** `middleware/auth.js` y `rutas/yo.js`, montados después de `/auth`. Prueba: `/api/yo` sin token → 401, con token → 200.
6. **Postman.** `docs/api.postman_collection.json` v2.1. Variables `base = http://localhost:3000/api` y `token`. Carpeta "Sesión" con pruebas en cada petición:
   1. Login de Valeria → 200. Guarda `token` y confirma que la respuesta no trae `password`.
   2. Valeria con clave incorrecta → 401. Guarda el cuerpo en una variable.
   3. Correo inexistente → 401, con el cuerpo idéntico al de la petición 2.
   4. Sergio → 403 "no es de asesor".
   5. Iván → 403 "desactivada".
   6. `correo` y `clave` vacíos → 422 con `errores.correo` y `errores.clave`.
   7. JSON mal formado → 400.
   8. `GET /yo` con token → 200 `{ id, nombre, correo }`.
   9. `GET /yo` sin token → 401 "Falta el token de acceso.".
   10. `GET /yo` con un carácter del token cambiado → 401 "El token no es válido o ya venció.".
   11. Ruta inexistente con token → 404 "Ruta no encontrada.".

   Los correos salen de `VerificacionSeeder`. La contraseña pública puede ir en la colección (CLAUDE.md lo permite).

---

## 5. Criterios de aceptación

Base: `php artisan migrate:fresh --seeder=VerificacionSeeder` y `npm run dev` con un `api/.env` válido.

**A1 · Inicio de sesión**
- **CA1.** Dado Valeria, cuando envía `POST /api/auth/login` con su correo y la contraseña correcta, entonces recibe 200 con `{ token, usuario: { id, nombre, correo } }`. Al decodificar el token (jwt.io o `jwt.decode`), `alg` es `HS256`, `sub` es su id en texto y `exp − iat` corresponde a `JWT_EXPIRA`.
- **CA2.** Dado Sergio (supervisor), o Alicia (administradora), con la contraseña correcta, entonces recibe 403 "Esta cuenta no es de asesor. Use el panel de la clínica." y la respuesta no trae `token`.
- **CA3.** Dado Iván (desactivado) con la contraseña correcta, entonces recibe 403 "Su cuenta está desactivada. Comuníquese con el administrador." y no trae `token`.
- **CA4.** Dado un correo que no existe, y aparte el de Valeria con una clave incorrecta, entonces las dos respuestas son 401 `{ "error": "Correo o contraseña incorrectos." }` byte por byte. `rutas/auth.js` llama a `bcryptjs.compare` también cuando el correo no existe.
- **CA5.** Dado `{ "correo": "", "clave": "" }`, entonces 422 con `errores.correo = "Escriba su correo."` y `errores.clave = "Escriba su contraseña."`. Dado `{ "clave": "x" }`, entonces 422 solo con `errores.correo`.
- **CA6.** Dado `POST /api/auth/login` sin cuerpo, entonces 400 "Falta el cuerpo de la petición.".

**A2 · Middleware**
- **CA7.** Dado `GET /api/yo` sin `Authorization`, entonces 401 "Falta el token de acceso.".
- **CA8.** Dado el token de Valeria con un carácter cambiado, entonces 401 "El token no es válido o ya venció.". Lo mismo con un token vencido: se pone `JWT_EXPIRA=10s` en `.env`, se reinicia, se inicia sesión y se espera 11 s. Lo mismo con un token HS512 firmado con el mismo secreto: `node --env-file=.env -e "import('jsonwebtoken').then(j=>console.log(j.default.sign({},process.env.JWT_SECRET,{algorithm:'HS512',subject:'<id>'})))"`.
- **CA9.** Dado Valeria con token válido, cuando se ejecuta `UPDATE users SET activo = 0 WHERE id = <Valeria>` (o el administrador la desactiva en el panel, si ya existe el spec 03) y pide `GET /api/yo`, entonces 401 "Su cuenta ya no tiene acceso. Ingrese de nuevo.". Al volver a `activo = 1`, el mismo token responde 200. Con `rol` cambiado a `supervisor` también da 401.
- **CA10.** Dado un token válido, `GET /api/yo` responde 200 con exactamente las claves `id`, `nombre` y `correo`.

**A7 (base) · Errores**
- **CA11.** Dado `POST /api/auth/login` con `Content-Type: application/json` y cuerpo `{"correo":`, entonces 400 "El cuerpo de la petición no es JSON válido." con `Content-Type: application/json`.
- **CA12.** Dado `GET /api/nada` con token, entonces 404 `{ "error": "Ruta no encontrada." }`. Dado `GET /nada`, también 404 JSON.
- **CA13.** Dado MySQL detenido después de arrancar la API, cuando se pide `GET /api/yo` con token, entonces 500 `{ "error": "Ocurrió un error inesperado. Intente de nuevo más tarde." }`, sin texto de SQL. El detalle aparece en la consola.
- **CA14.** Ninguna respuesta de este spec es HTML: todas traen `Content-Type: application/json` (comprobado en CA5 a CA13).

**Arranque (S2)**
- **CA15.** Dado `JWT_SECRET=corto`, o sin `CORS_ORIGIN`, `JWT_EXPIRA`, `DB_HOST`, `DB_USER` o `DB_NAME`, cuando se ejecuta `npm run dev`, entonces la API no escucha y la consola dice qué variable falla.
- **CA16.** Dado MySQL detenido, cuando se ejecuta `npm run dev`, entonces la API no escucha y muestra "No se pudo conectar a MySQL".
- **CA17.** `api/.env.example` tiene `CORS_ORIGIN=http://127.0.0.1:5500` y ya no tiene `CORS_ORIGEN`. `JWT_SECRET` y `DB_PASSWORD` van vacíos.

**CORS (S6)**
- **CA18.** `curl -i -H "Origin: http://127.0.0.1:5500" http://localhost:3000/api/yo` trae `Access-Control-Allow-Origin: http://127.0.0.1:5500`. Con `-H "Origin: http://otro.test"` la respuesta no trae esa cabecera.
- **CA19.** Un preflight `curl -i -X OPTIONS -H "Origin: http://127.0.0.1:5500" -H "Access-Control-Request-Method: PATCH" -H "Access-Control-Request-Headers: authorization,content-type" http://localhost:3000/api/yo` responde 204 con `Access-Control-Allow-Methods` que incluye PATCH y `Access-Control-Allow-Headers` que incluye Authorization.
- **CA20.** `git grep -nE "origin: *('\*'|true)" api/src` no da resultados.

**Seguridad del código**
- **CA21.** `git grep -niE "\b(create|alter) +(table|index|database)" api/src` no da resultados.
- **CA22.** `git grep -n "bcrypt.hash\|hashSync\|genSalt" api/` no da resultados, y `api/src` importa `bcryptjs`, no `bcrypt`.
- **CA23.** `git grep -nE '\$\{' api/src`: ningún resultado está dentro de un texto SQL.
- **CA24.** La contraseña pública de las semillas no aparece en `api/` (`git grep`). Solo puede estar en la colección de Postman.
- **CA25.** Las respuestas de login y `/yo` no traen `password`, `remember_token`, `rol`, `activo`, `vendedor_id`, `user_id`, `supervisor_id` ni `usuario_id`.
- **CA26.** La colección importa en Postman y la carpeta "Sesión" pasa sus 11 peticiones con todas sus pruebas en verde.

---

## 6. Decisiones tomadas y descartadas

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| `CORS_ORIGIN` | `CORS_ORIGEN` (estaba en `.env.example`) | La especificación (S2, S6) manda sobre el código |
| `origin: [CORS_ORIGIN]`, un arreglo con el texto exacto | `origin: CORS_ORIGIN` como texto, que es lo que escribe S6 | Con un texto, el paquete `cors` siempre devuelve esa cabecera, venga de donde venga la petición, y así falla la segunda prueba de S6. Con un arreglo compara el origen y omite la cabecera si no coincide. Sigue siendo un valor exacto, sin `*`, `true` ni expresión regular |
| `servidor.js` prueba MySQL con `SELECT 1` antes de escuchar | Solo `app.listen` | Falla rápido y con un mensaje claro, igual que con el secreto corto. Así `servidor.js` hace un poco más que `app.listen` |
| `config.js` exige todas las variables de la base y del JWT | Valores por omisión | Un `.env` incompleto se detecta al arrancar y no en la primera petición. Solo `PORT` y `DB_PORT` tienen valor por omisión, y `DB_PASSWORD` puede ir vacía |
| `app.js` separado de `servidor.js` | Un solo archivo | Permite importar la app sin abrir un puerto (`pruebas/simultaneas.js` en el spec 23) |
| Comparar contra `HASH_FALSO` cuando el correo no existe | Responder 401 de inmediato | Con un 401 inmediato, el tiempo de respuesta revela qué correos existen. `HASH_FALSO` va como constante porque la API no puede llamar a `bcryptjs.hash` (S1) |
| El token solo lleva `sub` | Meter `rol`, `nombre` o `activo` en el token | El middleware vuelve a leer al usuario en cada petición. Copiarlos al token los dejaría desactualizados y no se usarían |
| `algorithms: ['HS256']` en `jwt.verify` | Dejar que `jsonwebtoken` acepte el algoritmo de la cabecera | Rechaza los tokens con `none` o con otro algoritmo (A2) |
| La clave no se recorta; el correo sí | Recortar los dos | Una contraseña puede tener espacios a propósito. Una clave de solo espacios se compara y da 401, no 422 |
| Ruta inexistente bajo `/api` sin token → 401 | 404 antes del middleware | El contrato dice que todas las rutas salvo el login exigen token, y así no se revela qué rutas existen. La prueba del 404 usa token |
| `rol` va antes que `activo` en el login | Revisar `activo` primero | Un administrador desactivado recibe "no es de asesor", que es lo que le sirve: lo manda al panel |
| Error de la base dentro del middleware → 500 | Responder 401 | Un 401 cerraría la sesión de la SPA por una falla del servidor, no de la cuenta |
| Sin pruebas `node --test` en este spec | Pruebas de integración contra `crm_dental` | Decisión de la integrante: se verifica con Postman, curl y SQL |
| Sin paquetes nuevos (ni `helmet`, `morgan`, `dotenv`, `express-rate-limit`) | Agregarlos | Regla del proyecto. `node --env-file` reemplaza a `dotenv` |
| El 1062 → 409 queda en cada ruta | Convertirlo en `errores.js` | Cada índice único tiene su propio mensaje en el contrato (NIT, teléfono, producto, historial) |

---

## 7. Riesgos

| Riesgo | Mitigación |
|---|---|
| S6 escribe `cors({ origin: process.env.CORS_ORIGIN })`, distinto de lo que hace este spec | Avisar a A (dueña de la sección 9) para que cambie esa línea a `origin: [process.env.CORS_ORIGIN]` o "un arreglo con el origen exacto". El criterio de S6 no cambia |
| `bcryptjs` no acepta el prefijo `$2y$` de Laravel | Comprobarlo en el paso 4 con el hash de Valeria. Si falla, reemplazar `$2y$` por `$2b$` antes de comparar: son el mismo algoritmo |
| `HASH_FALSO` con un costo distinto del de Laravel (`BCRYPT_ROUNDS`, 12 por omisión) | Copiar el costo del hash real de Valeria: los dos números después de `$2y$` deben coincidir |
| `mysql2` con `execute` y `LIMIT ?` falla con algunos tipos de parámetro | Usar `LIMIT 1` fijo. Ningún dato del usuario va en el `LIMIT` |
| El `api/.env` local todavía dice `CORS_ORIGEN` | `config.js` termina con "Falta CORS_ORIGIN". La integrante lo renombra, porque el agente no lee `.env` |
| `SET time_zone` no se aplica a una conexión del pool | Se ejecuta en el evento `connection`, que ocurre una vez por conexión física. Se comprueba con `SELECT @@session.time_zone` desde `consulta` (debe dar `+00:00`) |
| El usuario se lee en cada petición: una consulta más por petición | Es una consulta por llave primaria. Es el precio de que una cuenta desactivada pierda el acceso de inmediato (A2) |
