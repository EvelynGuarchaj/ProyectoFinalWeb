# 08 · API de actividades

**Estado:** Aprobado
**Depende de:** SPEC 01 (`01-esquema-y-semilla`: tabla `actividades` y las 4 actividades de la semilla de verificación), SPEC 02 (`02-api-base-login`: `db.js`, `util/errores.js`, `util/validar.js` y `middleware/auth.js`) y SPEC 06 (`06-api-clientes`: `util/formato.js` y el montaje de `/api/clientes`)
**Fecha:** 2026-11-08
**Dueña:** B
**Rama:** `spec-08-api-actividades`
**Objetivo:** que el asesor registre, liste y complete las actividades de los pacientes de su cartera por la API, con fechas ISO 8601 con zona que se guardan en UTC.

**Requisitos que cumple:** A5 completo y las reglas R2 y R8 del enunciado (citadas como las dio la integrante; ver riesgos). De la sección 9: S3 y S5 en las rutas de actividades. De A7: la forma **Actividad** sin llaves internas. Contrato: `POST /clientes/:id/actividades`, `GET /actividades?estado=` y `PATCH /actividades/:id` de la sección 4.4, y la forma **Actividad** de la sección 4.3. Decisión del enunciado de la sección 5: las actividades siguen al paciente.

---

## 1. Alcance

**Incluye**
- `api/src/util/fechas.js` con `esISOConZona(texto)`.
- `api/pruebas/fechas.test.js`, con `node:test` y `node:assert/strict`. El script `test` del spec 06 (`node --test "pruebas/*.test.js"`) ya lo incluye.
- `formato.actividad(fila, ahora)` en `api/src/util/formato.js`.
- `api/src/rutas/actividades.js`, que exporta dos routers:
  - `rutasActividades`, montado en `/api/actividades`: `GET /` y `PATCH /:id`.
  - `rutasActividadesDeCliente`, montado también en `/api/clientes`: `POST /:id/actividades`.
- Los dos montajes en `app.js`, después de `exigirToken`.
- La carpeta "Actividades" de `docs/api.postman_collection.json`.

**No incluye**
- Cambios en `rutas/clientes.js`. La ruta de actividades del cliente vive en `actividades.js`.
- Borrar o editar el tipo, la descripción o la fecha de una actividad. El contrato solo permite cambiar `completada`.
- La regla propia (spec 13). Allí se lee `actividades` para saber si hay una cita completada. Este spec solo la escribe.
- La vista `#/actividades` de la SPA ni la función `aISO` de `util.js` (spec 20, de A).
- `pruebas/simultaneas.js` y `docs/verificacion.md` (spec 23).
- Paquetes nuevos.

---

## 2. Modelo de datos

Este spec no crea tablas ni columnas. La API nunca ejecuta `CREATE` ni `ALTER`. Usa `actividades` (SPEC 01) y lee `clientes`:

| Columna | Tipo | Lo que usa la API |
|---|---|---|
| `id` | BIGINT UNSIGNED | llave, `:id` de la ruta |
| `cliente_id` | BIGINT UNSIGNED, FK `clientes` en cascada | el `:id` de `/clientes/:id/actividades`, solo si es de su cartera |
| `vendedor_id` | BIGINT UNSIGNED, FK `users` | quién la registró: siempre `req.usuario.id`. **No** decide quién la ve |
| `tipo` | ENUM('llamada','reunion','tarea') | uno de los tres, exacto |
| `descripcion` | VARCHAR(500) | 1 a 500 caracteres, recortada |
| `vence_en` | DATETIME NULL (UTC) | `Date` en UTC, o NULL |
| `completada` | TINYINT(1) DEFAULT 0 | 0 al crear; `true`/`false` en el PATCH |
| `created_at`, `updated_at` | TIMESTAMP NULL | `NOW()` (la sesión está en UTC, spec 02) |

**Dueño de una actividad.** Una actividad es del asesor si su **paciente** está en su cartera: `JOIN clientes c ON c.id = a.cliente_id WHERE c.vendedor_id = ?`. No importa quién la registró (`a.vendedor_id`). Así, cuando el panel reasigna un paciente, sus actividades se van con él (sección 5 de la especificación).

### 2.1 Funciones exportadas

| Archivo | Exporta | Comportamiento |
|---|---|---|
| `util/fechas.js` | `esISOConZona(texto)` | `true` solo si `texto` es un string con la forma `AAAA-MM-DDTHH:MM`, con `:SS` opcional, `.mmm` opcional (1 a 3 dígitos, solo si hay segundos) y una zona **obligatoria** (`Z` o `±HH:MM`). Primero revisa el texto con una expresión regular y los rangos de cada parte. Solo después hace `new Date(texto)` |
| `util/formato.js` | `actividad(fila, ahora = new Date())` | `{ id, cliente: { id, nombre }, tipo, descripcion, vence_en, completada, vencida }`. No devuelve ninguna otra clave |

**Reglas de `esISOConZona`.**
- Mes de 1 a 12. Día real para ese mes y año, con febrero de 29 días solo en años bisiestos.
- Hora de 0 a 23, minutos y segundos de 0 a 59.
- Zona `Z`, o `±HH:MM` con horas de 0 a 14 y minutos de 0 a 59.
- Ya convertida a UTC, el año debe estar entre 1000 y 9999, que es el rango de `DATETIME`.
- Si no es texto (`null`, número u objeto), devuelve `false`.

**Reglas de `formato.actividad`.**
- `cliente` se arma con las columnas `cliente_id` y `cliente_nombre` de la consulta.
- `vence_en` es `fila.vence_en.toISOString()` (`"2026-12-01T16:00:00.000Z"`), o `null`.
- `completada` es `Boolean(fila.completada)`, nunca `1` ni `0`.
- `vencida` es `!completada && vence_en !== null && fila.vence_en < ahora`. Siempre es booleano. Se calcula al responder, con el reloj del servidor.
- `ahora` es un parámetro para poder probarla sin depender del reloj.

### 2.2 Casos de `pruebas/fechas.test.js`

| Entrada | Resultado esperado |
|---|---|
| `2026-12-01T10:00` | `false` (sin zona) |
| `2026-12-01T10:00:00Z` | `true` |
| `2026-12-01T10:00:00-06:00` | `true`, y `new Date(...)` equivale a `2026-12-01T16:00:00.000Z` |
| `2026-02-30T10:00:00Z` | `false` (fecha que no existe) |
| `2026-12-01T10:00Z`, `2026-12-01T10:00:00.5Z`, `2026-12-01T10:00:00.123+05:30` | `true` (sin segundos, y con milisegundos) |
| `2024-02-29T10:00:00Z` | `true` (bisiesto) |
| `2026-13-01T10:00:00Z`, `2026-12-01T24:00:00Z`, `2026-12-01T10:00:00+15:00`, `2026-12-01 10:00:00Z` | `false` |
| `""`, `null`, `20261201` (número) | `false` |

Las cuatro primeras filas son las decididas por la integrante. Las demás cubren cada regla de 2.1.

### 2.3 SQL que ejecuta la API (todo con `?`)

```sql
-- Columnas de la forma Actividad (se repiten en cada consulta, texto fijo)
SELECT a.id, a.tipo, a.descripcion, a.vence_en, a.completada,
       c.id AS cliente_id, c.nombre AS cliente_nombre
  FROM actividades a JOIN clientes c ON c.id = a.cliente_id
-- GET /actividades: tres consultas fijas, sin filtro, con a.completada = 0 o con a.completada = 1
 WHERE c.vendedor_id = ? [AND a.completada = 0 | AND a.completada = 1]
 ORDER BY a.vence_en IS NULL, a.vence_en, a.id
-- Lectura de una (PATCH, y después del POST)
 WHERE a.id = ? AND c.vendedor_id = ? LIMIT 1
-- POST: el paciente debe ser de su cartera
SELECT id FROM clientes WHERE id = ? AND vendedor_id = ? LIMIT 1
-- POST: INSERT … SELECT, atómico (si el paciente ya no es suyo, inserta 0 filas)
INSERT INTO actividades (cliente_id, vendedor_id, tipo, descripcion, vence_en, completada, created_at, updated_at)
SELECT c.id, ?, ?, ?, ?, 0, NOW(), NOW() FROM clientes c WHERE c.id = ? AND c.vendedor_id = ?
-- PATCH
UPDATE actividades a JOIN clientes c ON c.id = a.cliente_id
   SET a.completada = ?, a.updated_at = NOW()
 WHERE a.id = ? AND c.vendedor_id = ?
```

Los tres `GET` son textos SQL completos en el código. No se arman con `${}` ni con concatenación de datos del usuario.

---

## 3. Comportamiento

Todas las rutas toman al dueño de `req.usuario.id`. Los campos del cuerpo que el contrato no lista (`vendedor_id`, `cliente_id`, `completada` al crear, `id`…) se ignoran. Todo `:id` pasa por `idDeRuta()`: si no es un entero positivo, responde 404.

### 3.1 `POST /api/clientes/:id/actividades`

1. `idDeRuta(req.params.id)` → 404. `exigirCuerpo(req)` → 400.
2. `SELECT id FROM clientes WHERE id = ? AND vendedor_id = ?`. Si no hay fila → 404 `"No se encontró el recurso."`, sin validar nada más.
3. Validación. Se juntan todos los errores en un solo 422 `{ errores }`:

| Campo | Regla | Si falla → 422 |
|---|---|---|
| `tipo` | Texto exacto `llamada`, `reunion` o `tarea` (sin recortar ni pasar a minúsculas) | `"Elija el tipo: llamada, reunion o tarea."` |
| `descripcion` | `texto(valor, 1, 500)` | `"Escriba la descripción (máximo 500 caracteres)."` |
| `vence_en` | Ausente, `null` o `""` → `NULL`. Si no, `esISOConZona` y luego `new Date(valor)` | `"Escriba la fecha en ISO 8601 con zona horaria, por ejemplo 2026-12-01T10:00:00-06:00."` |

4. `INSERT … SELECT` de 2.3, con los parámetros `req.usuario.id, tipo, descripcion recortada, fecha (Date) o null, :id, req.usuario.id`. Si `affectedRows` es 0 (el panel le quitó el paciente entre el paso 2 y este) → 404.
5. Se lee la actividad con `insertId` y la consulta "Lectura de una". Responde 201 con `formato.actividad`.

Una fecha en el pasado se acepta: sale con `vencida: true`.

### 3.2 `GET /api/actividades?estado=`

1. `req.query.estado`:
   - Ausente (`undefined`) → todas.
   - Exactamente `pendiente` → `a.completada = 0`.
   - Exactamente `completada` → `a.completada = 1`.
   - Cualquier otro valor, también `""`, un arreglo (`?estado=a&estado=b`) o `Pendiente` → 400 `"El filtro estado debe ser pendiente o completada."`.
2. Se elige una de las tres consultas fijas de 2.3.
3. Orden: primero las que tienen fecha, por `vence_en` ascendente; después las sin fecha. En los empates se ordena por `id` ascendente.
4. 200 con `filas.map((f) => formato.actividad(f, ahora))`. `ahora` se toma una sola vez por petición.

### 3.3 `PATCH /api/actividades/:id`

1. `idDeRuta` → 404. `exigirCuerpo` → 400.
2. "Lectura de una". Si no hay fila → 404, sin validar nada más.
3. `completada` debe ser `true` o `false` (`typeof === 'boolean'`). `"si"`, `1`, `"true"`, `null` o la clave ausente → 422 `{ "errores": { "completada": "Indique true o false." } }`.
4. `UPDATE … JOIN` de 2.3. Si `affectedRows` es 0 → 404. mysql2 usa `FOUND_ROWS`, así que marcar `true` una actividad que ya estaba en `true` cuenta 1 y responde 200.
5. 200 con la actividad leída otra vez.

Se puede volver a `false`. El contrato acepta los dos valores.

---

## 4. Plan de implementación

Cada paso deja la API arrancable. Los pasos 1 y 2 no necesitan la base. Las tablas todavía no existen: los pasos 4 a 7 se programan y se prueban sin la base solo hasta donde se pueda (400, 401, 404 por id inválido y 422). Al final de cada paso se anota qué falta probar con la semilla.

1. **`util/fechas.js` y `pruebas/fechas.test.js`.** Primero las pruebas con los casos de 2.2 y luego `esISOConZona`. Prueba: `npm test` en verde, con las pruebas de NIT y teléfono incluidas.
2. **`formato.actividad(fila, ahora)`.** Prueba sin base: un comando corto de Node con dos filas de ejemplo (una con `vence_en` antes de `ahora` y `completada: 0`, otra con `vence_en: null` y `completada: 1`) da exactamente las 7 claves, booleanos en `completada` y `vencida`, y `vence_en` en ISO con `.000Z` o `null`.
3. **Prerrequisito.** Para probar con la base, las tablas de SPEC 01 deben existir (`php artisan migrate:fresh --seeder=VerificacionSeeder`). La programación sigue sin ellas.
4. **`GET /api/actividades`.** `rutas/actividades.js` con `rutasActividades` y el montaje en `app.js`. Prueba sin base: `?estado=hoy`, `?estado=` y `?estado=a&estado=b` → 400 (el filtro se revisa antes de consultar), y sin token → 401.
5. **`POST /api/clientes/:id/actividades`.** `rutasActividadesDeCliente` y su montaje en `/api/clientes`, después de `rutasClientes`. Prueba sin base: `/clientes/abc/actividades` → 404, y sin cuerpo → 400. La validación (422) se prueba con la base, porque va después del 404 del paciente.
6. **`PATCH /api/actividades/:id`.** Sección 3.3. Prueba sin base: `/actividades/abc` → 404, y sin cuerpo → 400.
7. **Postman.** Carpeta "Actividades" en `docs/api.postman_collection.json`. Supone una semilla recién cargada y se ejecuta en orden. Cada petición prueba el código. Un script de la carpeta confirma que ningún JSON trae `password`, `vendedor_id`, `user_id`, `supervisor_id` ni `usuario_id`.
   1. Login de Diego. `GET /actividades` de Diego guarda en `idActividadAjena` una actividad suya. `GET /clientes` de Diego guarda en `idClienteAjeno` el id de Farmacia Los Altos.
   2. Login de Valeria. `GET /clientes` guarda en `idClienteValeria` el id de Ferretería Xela.
   3. `GET /actividades?estado=pendiente`: "Enviar propuesta de capacitación" con `vencida: false` y "Presentar opciones de soporte" con `vencida: true`. Cada elemento tiene exactamente las 7 claves, `cliente` tiene exactamente `id` y `nombre`, y `completada` y `vencida` son booleanos.
   4. `GET /actividades?estado=hoy` → 400 con el mensaje. `?estado=` → 400.
   5. `GET /actividades` sin filtro → las que tienen fecha van en orden ascendente y antes que las que no tienen.
   6. `POST /clientes/{{idClienteValeria}}/actividades` con `vence_en: "2026-12-01T10:00"` → 422 con `errores.vence_en`.
   7. Mismo POST con `tipo: "visita"`, `descripcion: ""` y `vence_en: "2026-02-30T10:00:00Z"` → 422 con `errores.tipo`, `errores.descripcion` y `errores.vence_en`.
   8. POST válido con `tipo: "reunion"`, `vence_en: "2026-12-01T10:00:00-06:00"` y `"vendedor_id": 1` → 201 con `vence_en: "2026-12-01T16:00:00.000Z"`, `completada: false` y `cliente.nombre` "Ferretería Xela". Guarda `idActividadNueva`.
   9. `POST /clientes/{{idClienteAjeno}}/actividades` válido → 404. `POST /clientes/abc/actividades` → 404.
   10. `PATCH /actividades/{{idActividadNueva}}` con `{ "completada": "si" }` → 422. Con `{}` → 422. Con `{ "completada": true }` → 200, con `completada: true` y `vencida: false`.
   11. `GET /actividades?estado=completada` incluye `idActividadNueva`. `?estado=pendiente` no la incluye.
   12. `PATCH /actividades/{{idActividadAjena}}` con `{ "completada": true }` → 404.

---

## 5. Criterios de aceptación

Base: `php artisan migrate:fresh --seeder=VerificacionSeeder`, `npm run dev` con un `api/.env` válido y el token de cada asesor obtenido con `POST /api/auth/login`.

**Fechas (sin base)**
- **CA1.** `npm test` corre `pruebas/fechas.test.js` con todos los casos de 2.2 en verde, junto con las pruebas de NIT y teléfono, sin la API ni la base encendidas.
- **CA2.** En `fechas.test.js` hay un caso sin zona (`false`), uno con `Z` (`true`), uno con `-06:00` que compara contra `2026-12-01T16:00:00.000Z` y uno con `2026-02-30` (`false`).

**A5 · Actividades**
- **CA3.** Dado Valeria, cuando pide `GET /api/actividades?estado=pendiente`, entonces "Enviar propuesta de capacitación" trae `vencida: false` y "Presentar opciones de soporte" trae `vencida: true`.
- **CA4.** Dado Valeria, cuando pide `?estado=hoy`, entonces 400 `{ "error": "El filtro estado debe ser pendiente o completada." }`. Con `?estado=` también 400.
- **CA5.** Dado Valeria, cuando crea una actividad con `vence_en: "2026-12-01T10:00"`, entonces 422 con `errores.vence_en` = el mensaje de 3.1. Con `"2026-12-01T10:00:00-06:00"`, 201 con `vence_en: "2026-12-01T16:00:00.000Z"`. En la base, `vence_en` es `2026-12-01 16:00:00`.
- **CA6.** Dado una actividad de un paciente de Diego, cuando Valeria envía `PATCH /api/actividades/:id` con `{ "completada": true }`, entonces 404 `{ "error": "No se encontró el recurso." }` y en la base `completada` no cambia. Dado una actividad propia, con `{ "completada": "si" }`, entonces 422 `{ "errores": { "completada": "Indique true o false." } }`.
- **CA7.** Dado cualquier actividad de cualquier respuesta, entonces `completada` y `vencida` son `true` o `false`, nunca `1` ni `0`.
- **CA8.** Cada actividad de cualquier respuesta tiene exactamente `id`, `cliente`, `tipo`, `descripcion`, `vence_en`, `completada` y `vencida`, y `cliente` tiene exactamente `id` y `nombre`. Ninguna respuesta trae `password`, `vendedor_id`, `user_id`, `supervisor_id` ni `usuario_id`. Todas traen `Content-Type: application/json`.
- **CA9.** Dado Valeria y el id de Farmacia Los Altos (de Diego), cuando envía `POST /api/clientes/:id/actividades` válido, entonces 404 y `SELECT COUNT(*) FROM actividades` no cambia. `POST /api/clientes/abc/actividades` → 404.
- **CA10.** Dado Valeria, cuando envía `{ "tipo": "visita", "descripcion": "", "vence_en": "2026-02-30T10:00:00Z" }` a un paciente suyo, entonces recibe un solo 422 con los tres mensajes de 3.1. Con una descripción de 501 caracteres, 422 en `descripcion`.
- **CA11.** Dado Valeria, cuando crea una actividad con `"vendedor_id": <id de Diego>` en el cuerpo, entonces 201. `SELECT vendedor_id FROM actividades WHERE id = <nueva>` da el id de Valeria.
- **CA12.** Dado Valeria, cuando pide `GET /api/actividades` sin filtro, entonces las actividades con `vence_en` aparecen primero, en orden ascendente, y las de `vence_en: null` al final.
- **CA13.** Dado una actividad pendiente de Valeria, cuando envía `{ "completada": true }`, entonces 200 con `completada: true` y `vencida: false`. Después aparece en `?estado=completada` y no en `?estado=pendiente`. Con `{ "completada": false }` vuelve a `pendiente`.
- **CA14.** Dado un paciente de Valeria con actividades, cuando en SQL se ejecuta `UPDATE clientes SET vendedor_id = <Diego> WHERE id = <ese paciente>`, entonces esas actividades ya no aparecen en `GET /api/actividades` de Valeria y sí en el de Diego.

**S3, S5 y sesión**
- **CA15.** Sin token, `GET /api/actividades`, `PATCH /api/actividades/1` y `POST /api/clientes/1/actividades` responden 401 `"Falta el token de acceso."`.
- **CA16.** `git grep -nE '\$\{' api/src/rutas/actividades.js`: ningún resultado está dentro de un texto SQL.
- **CA17.** Toda consulta de `rutas/actividades.js` que lee o cambia una actividad lleva `c.vendedor_id = ?` (o `vendedor_id = ?` en `clientes`).

**Postman**
- **CA18.** La carpeta "Actividades" de la colección pasa todas sus peticiones en verde, ejecutada en orden sobre una semilla recién cargada.

---

## 6. Decisiones tomadas y descartadas

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| Primero `fechas.js` y sus pruebas, después las rutas | Empezar por las rutas | Las tablas todavía no existen. La validación de fechas se prueba sin base |
| Revisar el texto con una expresión regular y los rangos antes de `new Date` | Confiar en `new Date(texto)` | `new Date` acepta textos sin zona (los toma como hora local) y "corrige" fechas imposibles como el 30 de febrero. Las dos cosas deben dar 422 |
| Zona obligatoria: `Z` o `±HH:MM` | Aceptar sin zona y suponer Guatemala | El contrato dice que sin zona → 422. La SPA siempre manda `-06:00` (`aISO`) |
| Segundos y milisegundos opcionales; milisegundos de 1 a 3 dígitos | Exigir `HH:MM:SS` | Decisión de la integrante. `2026-12-01T10:00-06:00` es ISO 8601 válido |
| Año en UTC entre 1000 y 9999 | No limitarlo | Es el rango de `DATETIME`. Fuera de él, MySQL daría error y saldría un 500 |
| La actividad es del asesor si su paciente está en su cartera (`JOIN clientes … c.vendedor_id = ?`) | Filtrar por `a.vendedor_id` | La especificación (sección 5) decide que las actividades siguen al paciente. `a.vendedor_id` solo dice quién la registró |
| `POST /clientes/:id/actividades` vive en `rutas/actividades.js` (router propio montado en `/api/clientes`) | Agregarla a `rutas/clientes.js` | No se toca el archivo del spec 06. Todo el código de actividades está en un solo lugar |
| `INSERT … SELECT … WHERE c.vendedor_id = ?` (`affectedRows` 0 → 404) | Transacción con `FOR UPDATE`, o `SELECT` y luego `INSERT` sin candado | Es una sola sentencia atómica. Si el panel reasigna al paciente en ese instante, no se inserta nada. No hace falta transacción |
| POST y PATCH: 404 antes de validar | Validar primero (422) | Igual que el PATCH de clientes (spec 06): no le dice nada al asesor sobre lo ajeno |
| `?estado=` vacío, repetido o con otra forma de escribirlo → 400 | Vacío = sin filtro | Lectura literal del contrato: solo la ausencia del parámetro da todas |
| Empates de orden por `id` ascendente | Dejarlo al azar | Una respuesta estable facilita las pruebas y la SPA |
| `vence_en` ausente, `null` o `""` → `NULL` | `""` → 422 | Un campo de fecha vacío en un formulario significa "sin fecha", igual que el correo en el spec 06 |
| `tipo` exacto, sin recortar ni pasar a minúsculas | Normalizarlo | El contrato lista tres valores exactos. Son los que manda la SPA |
| `completada` solo `true`/`false` de JSON | Aceptar `1`, `"true"` o `"si"` | El contrato pide un booleano. `"si"` debe dar 422 (A5) |
| `vencida` se calcula en JS con un solo `ahora` por petición | Calcularla en SQL con `UTC_TIMESTAMP()` | La regla queda en `formato.js`, junto a la forma de la respuesta, y se puede probar sin base pasando `ahora` |
| Se puede marcar `completada: false` | Solo permitir `true` | El contrato acepta los dos valores |
| Fechas en el pasado se aceptan | 422 si ya pasó | La semilla tiene actividades vencidas y el contrato no lo prohíbe. Salen con `vencida: true` |
| Sin pruebas automáticas de `formato.actividad` | Un `formato.test.js` | La integrante pidió pruebas solo de fechas. `formato` se revisa con un comando corto en el paso 2 |

---

## 7. Riesgos

| Riesgo | Mitigación |
|---|---|
| `docs/enunciado.txt` no está en `docs/`. Las reglas R2 y R8 se citan como las dio la integrante | Confirmar contra el enunciado cuando el archivo esté en el repositorio |
| Las tablas no existen: las rutas se programan sin probarlas contra la base | Cada paso anota qué falta probar. La carpeta de Postman y los CA3–CA14 se corren cuando esté la semilla |
| Los nombres o fechas de las 4 actividades de la semilla no coinciden con CA3 ("Enviar propuesta de capacitación" en 2030 y "Presentar opciones de soporte" ya vencida) | Comprobarlo con `SELECT descripcion, vence_en, completada FROM actividades` en cuanto exista la semilla. Si cambian, se ajustan el CA3 y la petición 3 de Postman, no el código |
| mysql2 devuelve `DATETIME` como texto en lugar de `Date` | `db.js` no usa `dateStrings`, así que devuelve `Date` en UTC (`timezone: 'Z'`). Si cambia, `formato.actividad` falla en el paso 2 y se nota de inmediato |
| Dos routers montados en `/api/clientes` | `rutasClientes` no tiene `POST /:id/actividades`, así que Express pasa al siguiente router. Se comprueba en el paso 5 listando las rutas |
| La carpeta "Actividades" crea datos y algunas pruebas dependen del orden | Correrla después de `migrate:fresh --seeder=VerificacionSeeder`, en orden. Así lo dice la descripción de la carpeta |

---

## Lo que **no** entra en este spec

- Cambios en `rutas/clientes.js`.
- Borrar actividades, o editar su tipo, descripción o fecha.
- La regla propia que lee las citas completadas (spec 13).
- La vista `#/actividades` y `aISO` de la SPA (spec 20).
- `pruebas/simultaneas.js` y `docs/verificacion.md` (spec 23).
