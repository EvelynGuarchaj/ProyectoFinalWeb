# 06 · API de clientes y cartera

**Estado:** Implementado
**Depende de:** SPEC 01 (`01-esquema-y-semilla`: tabla `clientes` con sus índices únicos y CHECK, y la semilla de verificación) y SPEC 02 (`02-api-base-login`: `db.js`, `util/errores.js` y, de sus pasos 4 y 5, `util/validar.js` y `middleware/auth.js`)
**Fecha:** 2026-11-08
**Dueña:** B
**Rama:** `spec-06-api-clientes`
**Objetivo:** que el asesor maneje su cartera de pacientes por la API, con NIT y teléfono normalizados igual que en el panel, y con un máximo de 10 pacientes que se respeta aunque lleguen peticiones simultáneas.

**Requisitos que cumple:** A3 completo y las reglas R2, R3 y R4 del enunciado (R4: NIT con dígito verificador módulo 11). De la sección 9: S3 en la búsqueda de clientes, S5 en las rutas de clientes y S8 en tomar, cartera máxima y duplicados de NIT y teléfono. Contrato: los seis endpoints de clientes de la sección 4.4 y la forma **Cliente** de la sección 4.3. Casos: toda la sección 10.

---

## 1. Alcance

**Incluye**
- `api/src/util/nit.js`, `api/src/util/telefono.js` y `api/src/util/formato.js`.
- `api/pruebas/nit.test.js` y `api/pruebas/telefono.test.js`, con `node:test` y `node:assert/strict`.
- `api/package.json`: el script `test` pasa de `node --test pruebas/` a `node --test "pruebas/*.test.js"`.
- `api/src/rutas/clientes.js`, montado en `/api/clientes` después de `middleware/auth.js` (cambio en `app.js`).
- `GET /api/clientes?q=`, `POST /api/clientes`, `GET /api/clientes/disponibles`, `POST /api/clientes/:id/tomar`, `GET /api/clientes/:id` y `PATCH /api/clientes/:id`.
- La carpeta "Clientes" de `docs/api.postman_collection.json`.

**No incluye**
- `POST /clientes/:id/actividades`. Usa la ruta de clientes, pero es del spec 08 (A5).
- `pruebas/simultaneas.js` y el registro de resultados en `docs/verificacion.md` (spec 23). Aquí la concurrencia se comprueba a mano con un comando corto (sección 5).
- Borrar o liberar pacientes. La API no tiene esas rutas: lo hace el panel (specs 05 y 07).
- `app/Support/Nit.php` y `Telefono.php` del panel (spec 05, de A). Deben pasar los mismos casos de la sección 10.
- Escapar `%` y `_` en la búsqueda (ver decisiones).
- Paquetes nuevos. `node:test` viene con Node 24.

---

## 2. Modelo de datos

Este spec no crea tablas ni columnas. La API nunca ejecuta `CREATE` ni `ALTER`. Usa `clientes` (SPEC 01) y bloquea filas de `users`:

| Columna | Tipo | Lo que usa la API |
|---|---|---|
| `id` | BIGINT UNSIGNED | llave, `:id` de la ruta |
| `vendedor_id` | BIGINT UNSIGNED NULL | siempre `req.usuario.id`; NULL significa disponible |
| `nombre` | VARCHAR(150) | 1 a 150 caracteres, recortado |
| `nit` | VARCHAR(12) NULL UNIQUE (`clientes_nit_unique`) | compacto, o NULL para CF |
| `telefono` | CHAR(8) UNIQUE (`clientes_telefono_unique`) | 8 dígitos, el primero de 2 a 9 |
| `correo` | VARCHAR(150) NULL | recortado, o NULL |
| `created_at`, `updated_at` | TIMESTAMP NULL | la API los pone con `NOW()` (la sesión está en UTC, spec 02) |

### 2.1 Funciones exportadas

| Archivo | Exporta | Comportamiento |
|---|---|---|
| `util/nit.js` | `compactar(texto)` | quita espacios y guiones (`/[\s-]/g`) y pasa a mayúsculas. No quita ceros. La usa la búsqueda `q` |
| `util/nit.js` | `normalizar(texto)` | `compactar` y además quita los ceros a la izquierda |
| `util/nit.js` | `esConsumidorFinal(texto)` | `true` si el texto recortado es `CF` sin importar mayúsculas |
| `util/nit.js` | `esValido(compacto)` | `true` si tiene de 2 a 12 caracteres, el cuerpo (todo menos el último) son solo dígitos y no empieza con 0, el verificador (el último) es de 0 a 9 o `K`, y el verificador coincide con el módulo 11 |
| `util/telefono.js` | `normalizar(texto)` | quita espacios, guiones, puntos y paréntesis (`/[\s\-.()]/g`). Si empieza con `+502`, lo quita. Si empieza con `502` sin `+` y después quedan exactamente 8 caracteres, quita el `502` |
| `util/telefono.js` | `esValido(texto)` | `/^[2-9][0-9]{7}$/` |
| `util/formato.js` | `cliente(fila)` | `{ id, nombre, nit: fila.nit ?? 'CF', telefono, correo }`. No devuelve ninguna otra clave |

**Módulo 11 (R4).** El cuerpo de largo `n` se multiplica dígito por dígito, de izquierda a derecha, por los pesos `n+1, n, …, 2`, y se suman los productos. El verificador esperado es `(11 − suma mod 11) mod 11`, y 10 se escribe `K`. Ejemplos: `4521817` suma 133, 133 mod 11 = 1, así que el verificador es `K`. `1234567` suma 112, 112 mod 11 = 2, así que el verificador es `9`.

Un NIT con puntos o con cualquier letra que no sea la `K` final es inválido (422). `normalizar` no los quita, y `esValido` los rechaza.

### 2.2 Casos de las pruebas (sección 10, todos)

| Archivo | Entrada | Resultado esperado |
|---|---|---|
| `nit.test.js` | `452 1817-k` | `normalizar` → `4521817K`, válido |
| | `4521817-K` | `4521817K`, válido (repetido del anterior: el 409 lo da la ruta) |
| | `00012345679` | `12345679`, válido |
| | `cf`, `CF` | `esConsumidorFinal` → `true` |
| | `4521817-1` | `45218171`, inválido (verificador incorrecto) |
| | `4521.817-K`, `4521817-A` | inválido (decisión de la integrante) |
| `telefono.test.js` | `+502 5555-1234` | `55551234`, válido |
| | `(502) 7761.2345` | `77612345`, válido |
| | `50255551234` | `55551234`, válido |
| | `5021 2345` | `50212345`, válido (no se quita el 502) |
| | `1234-5678` | `12345678`, inválido (empieza con 1) |
| | `5555123` | `5555123`, inválido (7 dígitos) |

### 2.3 SQL que ejecuta la API (todo con `?`)

```sql
-- GET /clientes (sin q)
SELECT id, nombre, nit, telefono, correo FROM clientes WHERE vendedor_id = ? ORDER BY nombre
-- GET /clientes?q=  (parámetros: id, q recortado, q compactado)
SELECT id, nombre, nit, telefono, correo FROM clientes
 WHERE vendedor_id = ? AND (nombre LIKE CONCAT('%', ?, '%') OR nit LIKE CONCAT('%', ?, '%'))
 ORDER BY nombre
-- GET /clientes/disponibles
SELECT id, nombre FROM clientes WHERE vendedor_id IS NULL ORDER BY nombre
-- Lectura de uno (GET y PATCH)
SELECT id, nombre, nit, telefono, correo FROM clientes WHERE id = ? AND vendedor_id = ? LIMIT 1
-- Repetidos (en el PATCH se agrega AND id <> ?)
SELECT id FROM clientes WHERE nit = ? LIMIT 1
SELECT id FROM clientes WHERE telefono = ? LIMIT 1
-- Candado de cartera y conteo (dentro de la transacción)
SELECT id FROM users WHERE id = ? FOR UPDATE
SELECT COUNT(*) AS total FROM clientes WHERE vendedor_id = ?
```

La constante `MAXIMO_CARTERA = 10` vive en `rutas/clientes.js`. El spec 15 la podrá mover a un archivo común si la necesita.

---

## 3. Comportamiento

Todas las rutas toman al dueño de `req.usuario.id`. Si el cuerpo trae `vendedor_id`, `id` u otro campo que el contrato no lista, se ignora. `/disponibles` se declara antes de `/:id`. Todo `:id` pasa por `idDeRuta()`: si no es un entero positivo, responde 404.

### 3.1 Validación común (`POST` y `PATCH`)

| Campo | Regla | Si falla → 422 |
|---|---|---|
| `nombre` | `texto(valor, 1, 150)` | `"Escriba el nombre del paciente (máximo 150 caracteres)."` |
| `nit` | Debe ser texto. Si es CF, se guarda `null`. Si no, `normalizar` y luego `esValido`. `null`, un número o un campo ausente en el POST fallan | `"Escriba un NIT válido (con su dígito verificador) o CF."` |
| `telefono` | Debe ser texto. `normalizar` y luego `esValido`. Un número JSON falla | `"Escriba un teléfono de Guatemala de 8 dígitos."` |
| `correo` | Si es `null`, `""` o no viene, se guarda `null`. Si no, debe ser texto, se recorta, debe cumplir `/^[^\s@]+@[^\s@]+\.[^\s@]+$/` y tener ≤ 150 caracteres. Se guarda tal cual, sin pasarlo a minúsculas | `"Escriba un correo válido."` |

Se juntan todos los campos que fallan en un solo 422 `{ errores }`.

**Repetidos (409).** Se revisa primero el NIT, solo si no es CF, y después el teléfono. Responde solo el primero que choque: `"Ya existe un paciente con ese NIT."` o `"Ya existe un paciente con ese teléfono."`.

**1062.** Si el `INSERT` o el `UPDATE` da `ER_DUP_ENTRY`, se revisa `err.sqlMessage`. Si nombra `clientes_nit_unique`, responde el 409 del NIT. Si nombra `clientes_telefono_unique`, el del teléfono. Cualquier otro índice pasa al 500.

### 3.2 `GET /api/clientes?q=`

1. `q = texto(req.query.q, 1, 150)`. Si es `null` (ausente, vacío o solo espacios), se usa la consulta sin `q`.
2. Si hay `q`, la condición del nombre usa `q` recortado y la del NIT usa `nit.compactar(q)`.
3. 200 con `filas.map(formato.cliente)`, ordenadas por nombre.

### 3.3 `POST /api/clientes`

1. `exigirCuerpo(req)` → 400.
2. Validación (3.1) → 422.
3. Repetidos (3.1) → 409.
4. `transaccion(async (cx) => …)`:
   1. `SELECT id FROM users WHERE id = ? FOR UPDATE` (el asesor).
   2. Conteo. Si es ≥ 10 → 409 `"Su cartera ya tiene 10 pacientes, el máximo permitido."`.
   3. `INSERT INTO clientes (vendedor_id, nombre, nit, telefono, correo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())`.
5. Si sale un 1062, responde el 409 de 3.1 (la transacción ya hizo rollback).
6. 201 con `formato.cliente({ id: insertId, …valores guardados })`.

### 3.4 `GET /api/clientes/disponibles`

200 con `[{ id, nombre }]`. Sin NIT, teléfono ni correo.

### 3.5 `POST /api/clientes/:id/tomar`

No necesita cuerpo. `transaccion`:

1. `SELECT id FROM users WHERE id = ? FOR UPDATE` (el asesor).
2. `SELECT vendedor_id FROM clientes WHERE id = ? FOR UPDATE`. Así el orden de candados es `users` → `clientes`.
   - Si no hay fila → 404 `"No se encontró el recurso."`.
   - Si `vendedor_id` no es `NULL`, aunque sea el mismo asesor → 409 `"Este paciente ya no está disponible."`.
3. Conteo. Si es ≥ 10 → 409 `"Su cartera ya tiene 10 pacientes, el máximo permitido."`.
4. `UPDATE clientes SET vendedor_id = ?, updated_at = NOW() WHERE id = ? AND vendedor_id IS NULL`. Si `affectedRows` es 0, la respuesta es 409 "no disponible". Es un respaldo: con el `FOR UPDATE` del paso 2 no debería ocurrir.
5. Después del commit, se lee el cliente con la consulta "Lectura de uno" y se responde 200 con `formato.cliente`.

### 3.6 `GET /api/clientes/:id`

"Lectura de uno". Si no hay fila → 404. Si hay, 200 con `formato.cliente`.

### 3.7 `PATCH /api/clientes/:id`

1. `idDeRuta` → 404. `exigirCuerpo` → 400.
2. Si el cuerpo no tiene ninguna de las claves `nombre`, `nit`, `telefono` o `correo` (`Object.hasOwn`) → 400 `"No envió ningún campo para cambiar."`.
3. "Lectura de uno". Si no hay fila → 404, sin validar nada más.
4. Se validan solo las claves presentes, con las reglas de 3.1. Si alguna falla → 422.
5. Repetidos con `AND id <> ?` → 409.
6. `UPDATE clientes SET <columnas presentes> = ?, updated_at = NOW() WHERE id = ? AND vendedor_id = ?`. Los nombres de columna salen de una lista fija del código, nunca del cuerpo. Si da 1062 → 409. Si `affectedRows` es 0 (se lo quitaron mientras tanto) → 404.
7. 200 con el cliente leído otra vez.

---

## 4. Plan de implementación

Cada paso deja la API arrancable. Los pasos 1 y 2 no necesitan la base ni las tablas.

1. **`util/nit.js` y `pruebas/nit.test.js`.** Primero las pruebas con los casos de 2.2 y luego `compactar`, `normalizar`, `esConsumidorFinal` y `esValido` (módulo 11). Prueba: `npm test` en verde.
2. **`util/telefono.js`, `pruebas/telefono.test.js` y el script `test`.** Se cambia `package.json` a `node --test "pruebas/*.test.js"`. Prueba: `npm test` corre los dos archivos, todo en verde, y no intenta correr `simultaneas.js`.
3. **`util/formato.js`.** Solo `cliente(fila)`. Se prueba en el paso 4.
4. **Prerrequisito.** Los pasos 4 y 5 del spec 02 deben estar terminados (`util/validar.js` y `middleware/auth.js` montado). La integrante los termina antes de seguir. Las tablas de SPEC 01 deben existir (`php artisan migrate:fresh --seeder=VerificacionSeeder`).
5. **Lecturas.** `rutas/clientes.js` con `GET /`, `GET /disponibles` y `GET /:id`, montado en `app.js` después del middleware. Prueba: con el token de Valeria, `GET /api/clientes` da 3 pacientes y María Coyoy trae `"nit": "CF"`.
6. **`POST /`.** Validación, repetidos, transacción con el candado y el 1062. Prueba: `452 1817-k` → 409, y un paciente nuevo → 201.
7. **`POST /:id/tomar`.** Sección 3.5. Prueba: Valeria toma Agencia Cuatro Caminos → 200, y otra vez → 409.
8. **`PATCH /:id`.** Sección 3.7. Prueba: `{ "correo": "" }` → 200 con `correo: null`, y `{}` → 400.
9. **Postman.** Carpeta "Clientes" en `docs/api.postman_collection.json`. Supone una semilla recién cargada y se ejecuta en orden. Cada petición prueba el código y confirma que el JSON no trae `password`, `vendedor_id`, `user_id`, `supervisor_id` ni `usuario_id`.
   1. Login de Diego: guarda `usuario.id` en `idDiego`. `GET /clientes` guarda en `idAjeno` el id de Farmacia Los Altos.
   2. Login de Valeria. `GET /clientes` → 3, María Coyoy con `"CF"`, ordenados por nombre.
   3. `GET /clientes?q=4521-8` → Comercial El Quetzal.
   4. `GET /clientes?q=' OR '1'='1` → 200 `[]`.
   5. `GET /clientes/{{idAjeno}}` → 404. `PATCH /clientes/{{idAjeno}}` con `{ "nombre": "Cambiado" }` → 404.
   6. `GET /clientes/abc` → 404.
   7. `POST /clientes` con NIT `452 1817-k` → 409 NIT.
   8. `POST /clientes` con teléfono `1234-5678` → 422 con `errores.telefono`.
   9. `POST /clientes` con `nombre: ""`, NIT `4521817-1` y `correo: "x@"` → 422 con `errores.nombre`, `errores.nit` y `errores.correo`.
   10. `POST /clientes` válido con `"vendedor_id": {{idDiego}}` → 201 (el campo se ignora). Guarda `idNuevo`. `GET /clientes` → 4, incluido el nuevo.
   11. `PATCH /clientes/{{idNuevo}}` con `{}` → 400. Con `{ "correo": "" }` → 200 y `correo: null`. Con el teléfono de Comercial El Quetzal → 409 teléfono.
   12. `GET /clientes/disponibles` → cada elemento con exactamente `id` y `nombre`. Guarda `idDisponible`.
   13. `POST /clientes/{{idDisponible}}/tomar` → 200. Otra vez → 409 "no disponible". `POST /clientes/999999/tomar` → 404.
   14. Login de Carlos (9 pacientes). Toma un disponible → 200. Toma otro → 409 cartera llena.

---

## 5. Criterios de aceptación

Base: `php artisan migrate:fresh --seeder=VerificacionSeeder`, `npm run dev` con un `api/.env` válido y el token de cada asesor obtenido con `POST /api/auth/login`.

**Normalización (R4 y sección 10)**
- **CA1.** `npm test` corre `pruebas/nit.test.js` y `pruebas/telefono.test.js` con todos los casos de 2.2 en verde, sin la API ni la base encendidas.
- **CA2.** `git grep -n "nit.test\|telefono.test" api/pruebas` encuentra los dos archivos, y cada fila de la tabla de la sección 10 aparece como un caso.

**A3 · Clientes y cartera**
- **CA3.** Dado Valeria, cuando pide `GET /api/clientes`, entonces recibe sus 3 pacientes ordenados por nombre y María Coyoy trae `"nit": "CF"`. Cada elemento tiene exactamente `id`, `nombre`, `nit`, `telefono` y `correo`.
- **CA4.** Dado Valeria y el id de Farmacia Los Altos (de Diego), cuando pide `GET /api/clientes/:id` o envía `PATCH` con `{ "nombre": "Cambiado" }`, entonces las dos responden 404 `{ "error": "No se encontró el recurso." }`. `SELECT nombre FROM clientes WHERE id = <id>` sigue dando "Farmacia Los Altos".
- **CA5.** Dado Valeria, cuando envía `POST /api/clientes` con NIT `452 1817-k`, entonces recibe 409 `"Ya existe un paciente con ese NIT."`.
- **CA6.** Dado Valeria, cuando envía `POST /api/clientes` con teléfono `1234-5678`, entonces recibe 422 con `errores.telefono = "Escriba un teléfono de Guatemala de 8 dígitos."`.
- **CA7.** Dado Valeria, cuando pide `GET /api/clientes/disponibles`, entonces cada elemento trae exactamente `id` y `nombre`, ordenados por nombre: Agencia Cuatro Caminos, Café Zunil y Clínica San Rafael.
- **CA8.** Dado Carlos con 9 pacientes, cuando toma un disponible y luego otro, entonces el primero responde 200 con el paciente y el segundo 409 `"Su cartera ya tiene 10 pacientes, el máximo permitido."`.
- **CA9.** Dado Valeria, cuando envía `POST /api/clientes` válido con `"vendedor_id": <id de Diego>`, entonces recibe 201. `SELECT vendedor_id FROM clientes WHERE id = <nuevo>` da el id de Valeria.
- **CA10.** Dado Valeria, cuando envía `POST /api/clientes` con `{ "nombre": "", "nit": "4521817-1", "telefono": "5555123", "correo": "x@" }`, entonces recibe un solo 422 con los cuatro mensajes de 3.1. Sin `nit` en el cuerpo, o con `"nit": null` o `"telefono": 55559876` (número), también 422 en ese campo.
- **CA11.** Dado Valeria, cuando envía `POST /api/clientes` con NIT `cf`, teléfono `+502 5555-0001` y correo ` Ana@Correo.test `, entonces recibe 201 con `"nit": "CF"`, `"telefono": "55550001"` y `"correo": "Ana@Correo.test"`. En la base, `nit` es `NULL`.
- **CA12.** Dado Valeria, cuando envía `PATCH /api/clientes/<suyo>` con `{}`, entonces 400 `"No envió ningún campo para cambiar."`. Con `{ "correo": "" }`, 200 con `correo: null`. Con su propio teléfono, 200 (no cuenta a sí mismo). Con el teléfono de otro paciente, 409 `"Ya existe un paciente con ese teléfono."`.
- **CA13.** Dado `POST /api/clientes/999999/tomar`, entonces 404. Dado `POST /api/clientes/<un paciente de Valeria>/tomar` con el token de Valeria, entonces 409 `"Este paciente ya no está disponible."`. Dado Carlos con 10 pacientes y `POST /api/clientes/999999/tomar`, entonces 404 y no 409.
- **CA14.** Dado un NIT y un teléfono los dos repetidos en el mismo `POST`, entonces el 409 es el del NIT.

**S3 · Búsqueda**
- **CA15.** Dado Valeria, cuando pide `GET /api/clientes?q=' OR '1'='1`, entonces 200 `[]`, sin 500.
- **CA16.** Dado Valeria, cuando pide `?q=4521-8` y luego `?q=quetzal`, entonces las dos respuestas traen Comercial El Quetzal. Con `?q=Farmacia`, `[]`.
- **CA17.** `git grep -nE '\$\{' api/src/rutas/clientes.js`: ningún resultado está dentro de un texto SQL. Las columnas del `UPDATE` del PATCH salen de una lista fija.

**S8 · Simultáneas** (se comprueban a mano con un comando corto de Node que lanza las peticiones con `Promise.all` de `fetch`. El comando no se sube al repositorio: el script definitivo es del spec 23)
- **CA18.** Dado Café Zunil disponible y los tokens de Valeria, Diego y Carlos, cuando se envían 10 `POST /api/clientes/:id/tomar` a la vez repartidos entre ellos, entonces exactamente uno responde 200 y nueve 409. `clientes.vendedor_id` de Café Zunil es el ganador.
- **CA19.** Dado Valeria con 3 pacientes, cuando se envían 8 `POST /api/clientes` a la vez con NIT `CF` y teléfonos distintos y válidos, entonces exactamente 7 responden 201 y 1 responde 409 por cartera. `SELECT COUNT(*) FROM clientes WHERE vendedor_id = <Valeria>` da 10.
- **CA20.** Dado Valeria y Diego, cuando envían a la vez `POST /api/clientes` con el teléfono `5555-9876`, entonces uno responde 201 y el otro 409 `"Ya existe un paciente con ese teléfono."` (por la consulta previa o por el 1062). En la base hay un solo paciente con `telefono = '55559876'`.

**Contrato y Postman**
- **CA21.** Ninguna respuesta de clientes trae `password`, `vendedor_id`, `user_id`, `supervisor_id` ni `usuario_id`. Todas traen `Content-Type: application/json`.
- **CA22.** Sin token, cualquier ruta de `/api/clientes` responde 401 `"Falta el token de acceso."`.
- **CA23.** La carpeta "Clientes" de la colección pasa todas sus peticiones en verde, ejecutada en orden sobre una semilla recién cargada.

---

## 6. Decisiones tomadas y descartadas

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| Primero `nit.js`, `telefono.js` y sus pruebas, después las rutas | Empezar por las rutas | Las tablas todavía no existen. La normalización se prueba sin base y es la pieza que el panel debe igualar |
| En el NIT solo se quitan espacios y guiones. Puntos o letras que no sean la `K` final → 422 | Quitar cualquier carácter que no sea dígito ni K | Decisión de la integrante: un NIT con otros caracteres probablemente está mal escrito |
| Tomar: candado de `users`, luego `SELECT … FROM clientes WHERE id = ? FOR UPDATE`, y el conteo después | Contar primero y distinguir 404/409 por `affectedRows` | Con el conteo primero, un id inexistente con la cartera llena daría 409 y no el 404 del contrato. El `UPDATE … AND vendedor_id IS NULL` se conserva como respaldo |
| Tomar un paciente que ya es mío → 409 "no disponible" | 200 idempotente | El contrato dice que cualquier paciente que ya tiene vendedor da 409 |
| Con NIT y teléfono repetidos a la vez, gana el 409 del NIT | El del teléfono | Sigue el orden del contrato y de los campos |
| Revisar repetidos fuera de la transacción y el 1062 como árbitro | Revisarlos dentro del candado | El candado es de la cartera del asesor, no de todos los NIT. Dos asesores distintos no se esperan entre sí. El índice único decide (regla del proyecto) |
| El 1062 se distingue por el nombre del índice en `sqlMessage` | Volver a consultar cuál campo chocó | Una consulta extra después del rollback podría dar otro resultado. El nombre del índice es fijo (convención de Laravel) |
| Tipos estrictos: `nit` y `telefono` deben ser texto. Sin `nit`, o con `nit: null`, → 422 | Convertir números a texto y tomar el NIT ausente como CF | El contrato lista `nit` como obligatorio. "CF" debe ser explícito. Un teléfono numérico pierde ceros o se trunca sin aviso |
| Correo con `/^[^\s@]+@[^\s@]+\.[^\s@]+$/`, ≤ 150, recortado, sin pasarlo a minúsculas | Validación completa del RFC, o guardarlo en minúsculas | Basta para el formato del contrato y no rechaza correos reales. El correo no es único, así que las minúsculas no hacen falta |
| `q` del NIT con `compactar` (sin quitar ceros). `%` y `_` no se escapan | `normalizar` completo, o escapar los comodines | `q` es un trozo de NIT y quitarle ceros cambia lo que se busca. Los comodines solo amplían la búsqueda dentro de la propia cartera, nunca fuera (S3) |
| `compactar` vive en `nit.js` y `normalizar` la reutiliza | Una función suelta en la ruta | Un solo lugar para "quitar espacios y guiones y pasar a mayúsculas" |
| `/disponibles` ordenado por nombre | Por id | Igual que `GET /clientes`. El contrato no fija el orden |
| PATCH: 404 antes de validar | Validar primero (422) y luego buscar | No le dice al asesor nada sobre datos de un paciente ajeno |
| Columnas del `UPDATE` del PATCH desde una lista fija | Recorrer las claves del cuerpo | Ningún texto del cuerpo llega al SQL (S3) |
| `npm test` con `node --test "pruebas/*.test.js"` | `node --test pruebas/` | Evita que el ejecutor de pruebas intente correr `simultaneas.js`, que necesita la API encendida. Las comillas hacen que funcione igual en Windows |
| Concurrencia verificada a mano aquí. El script queda para el spec 23 | Adelantar `simultaneas.js` | El spec 02 ya asignó `simultaneas.js` al spec 23. Aquí basta comprobar que los candados funcionan |
| `NOW()` para `created_at` y `updated_at` | Dejarlos en NULL | El panel los muestra y los ordena. La sesión ya está en UTC (spec 02) |

---

## 7. Riesgos

| Riesgo | Mitigación |
|---|---|
| Los índices únicos no se llaman `clientes_nit_unique` y `clientes_telefono_unique` (SPEC 01 no los nombra explícitamente) | En el paso 6, comprobar con `SHOW INDEX FROM clientes`. Si el nombre es otro, se usa ese en 3.1. Las migraciones que ya están en `main` no se editan |
| `Nit.php` y `Telefono.php` del panel dan otro resultado que la API | Las dos piezas prueban la misma tabla de la sección 10. Si A agrega un caso, se agrega aquí también |
| Un `COUNT(*)` sin candado deja pasar el paciente 11 | El conteo va siempre después de `SELECT … FROM users … FOR UPDATE` y dentro de la misma transacción (CA19) |
| Bloqueo mutuo con el panel | El orden es siempre `users` → `clientes`, igual que en el panel (sección 3.10) |
| `node --test` con un patrón entre comillas no lo expande una versión vieja de Node | El proyecto usa Node 24, que acepta patrones glob en `--test` |
| `docs/enunciado.txt` no está en `docs/`. Las reglas R2, R3 y R4 se citan como las dio la integrante | Confirmar contra el enunciado cuando el archivo esté en el repositorio |
| La carpeta "Clientes" cambia datos (crea y toma pacientes) y falla si se ejecuta dos veces | Correrla después de `migrate:fresh --seeder=VerificacionSeeder`, en orden. Así lo dice la descripción de la carpeta |

---

## Lo que **no** entra en este spec

- `POST /clientes/:id/actividades` (spec 08).
- `pruebas/simultaneas.js` y `docs/verificacion.md` (spec 23).
- Borrar, liberar o reasignar pacientes desde la API.
- `Nit.php` y `Telefono.php` del panel (spec 05).
- Escapar `%` y `_` en la búsqueda.
