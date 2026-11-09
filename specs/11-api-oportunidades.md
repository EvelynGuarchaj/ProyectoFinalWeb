# 11 · API de oportunidades y productos

**Estado:** Aprobado
**Depende de:** SPEC 01 (`01-esquema-y-semilla`: tablas `productos`, `oportunidades`, `oportunidad_productos` e `historial_etapas` con sus CHECK e índices únicos, y la semilla de verificación), SPEC 02 (`02-api-base-login`: `db.js` con `consulta()` y `transaccion()`, `util/errores.js`, `util/validar.js` y `middleware/auth.js`) y SPEC 06 (`06-api-clientes`: `util/formato.js`, el script `test` y la colección de Postman)
**Fecha:** 2026-11-08
**Dueña:** B
**Rama:** `spec-11-api-oportunidades`
**Objetivo:** que el asesor lleve sus planes de tratamiento por el embudo desde la API, con tratamientos a precio guardado y un historial de etapas que no se duplica aunque lleguen peticiones simultáneas.

**Requisitos que cumple:** A4 completo y las reglas R5 y R6 del enunciado (citadas como las dio la integrante; ver riesgos). La sección 5: transiciones del embudo y la etapa repetida, que da 422. La sección 3: `oportunidades`, `oportunidad_productos`, `historial_etapas` y la fila "Una oportunidad cerrada no cambia…" de 3.10. De la sección 9:
- S3 en el filtro `?etapa=`.
- S4 y S5 en todas las rutas de este spec.
- S8 en el cambio de etapa y en el tratamiento repetido. El script de simultáneas es del spec 23.

Del contrato:
- Los endpoints `GET /productos`, `GET /oportunidades?etapa=`, `POST /oportunidades`, `GET /oportunidades/:id`, `PATCH /oportunidades/:id`, `PATCH /oportunidades/:id/etapa`, `POST /oportunidades/:id/productos`, `PATCH /oportunidades/:id/productos/:lineaId` y `DELETE /oportunidades/:id/productos/:lineaId` de la sección 4.4.
- Las formas **Oportunidad, detalle** y **resumen** de la sección 4.3.

---

## 1. Alcance

**Incluye**
- `api/src/util/etapas.js`, con `ETAPAS`, `ABIERTAS`, `CERRADAS`, `PROBABILIDAD` y `transicionPermitida(actual, nueva)`.
- `api/pruebas/etapas.test.js`, con `node:test` y `node:assert/strict`. El script `test` del spec 06 ya lo incluye.
- En `api/src/util/formato.js`: `producto(fila)`, `oportunidadResumen(fila)` y `oportunidadDetalle(fila, lineas, historial)`.
- `api/src/rutas/productos.js`, que exporta `rutasProductos`, montado en `/api/productos`.
- `api/src/rutas/oportunidades.js`, que exporta `rutasOportunidades`, montado en `/api/oportunidades`. Tiene las 8 rutas de oportunidades y líneas.
- Los dos montajes en `app.js`, después de `exigirToken`.
- Dos comentarios que marcan dónde va la regla propia: en el cambio de etapa y al agregar un tratamiento.
- La carpeta "Planes" de `docs/api.postman_collection.json`. También el ajuste del script de la colección para que la prueba "La respuesta es JSON" no se aplique a un 204.

**No incluye**
- La regla propia: la revisión de `requiere_evaluacion` y de la cita completada, su 409 y la carpeta "Regla propia" de Postman. Todo eso es del spec 13. Este spec solo deja los comentarios.
- `GET /reportes/resumen` y el pronóstico (spec 15).
- `pruebas/simultaneas.js` y `docs/verificacion.md` (spec 23). Aquí se programa el mecanismo de S8 (`FOR UPDATE` y el 1062), pero no el script que lo prueba.
- Borrar oportunidades: el contrato no tiene esa ruta.
- Cambiar el precio de una línea, porque nunca viene del cuerpo.
- Las vistas `embudo.js` y `plan.js` de la SPA (specs 19 y 21).
- Paquetes nuevos.

---

## 2. Modelo de datos

Este spec no crea tablas ni columnas. La API nunca ejecuta `CREATE` ni `ALTER`. Usa las tablas del SPEC 01:

| Tabla | Columnas que usa | Para qué |
|---|---|---|
| `productos` | `id`, `nombre`, `precio`, `activo`, `requiere_evaluacion` | Catálogo. El precio se copia a la línea. `requiere_evaluacion` solo se devuelve; la regla es del spec 13 |
| `oportunidades` | `id`, `cliente_id`, `vendedor_id`, `titulo`, `etapa`, `motivo_perdida`, `fecha_cierre`, `created_at`, `updated_at` | El plan. Su dueño es `vendedor_id = req.usuario.id` |
| `oportunidad_productos` | `id`, `oportunidad_id`, `producto_id`, `cantidad`, `precio_unitario`, `created_at`, `updated_at` | Líneas. El total **no se guarda** |
| `historial_etapas` | `oportunidad_id`, `usuario_id`, `etapa_anterior`, `etapa_nueva`, `created_at` | Un registro al crear y uno por cambio de etapa |
| `clientes` | `id`, `nombre`, `vendedor_id` | El paciente del plan, y la revisión de cartera al crear |
| `users` | `id`, `name` | El nombre de `historial[].usuario`. **Nunca** se devuelve su id |

Las fechas que escribe este spec usan `UTC_TIMESTAMP()`: `created_at`, `updated_at`, `fecha_cierre` y la fecha del historial.

Nombres de índices que usa el código, según la convención de Laravel. Si `SHOW INDEX` muestra otros, se cambian en las constantes:
- `oportunidad_productos_oportunidad_id_producto_id_unique`
- `historial_etapas_oportunidad_id_etapa_nueva_unique`

### 2.1 `util/etapas.js`

```js
export const ETAPAS = ['prospecto', 'calificado', 'propuesta', 'negociacion', 'ganada', 'perdida'];
export const ABIERTAS = ['prospecto', 'calificado', 'propuesta', 'negociacion'];
export const CERRADAS = ['ganada', 'perdida'];
// Porcentajes enteros, igual que Oportunidad::PROBABILIDAD del panel (spec 01).
export const PROBABILIDAD = { prospecto: 10, calificado: 25, propuesta: 50, negociacion: 75, ganada: 100, perdida: 0 };
export function transicionPermitida(actual, nueva) { /* true o false */ }
```

Los arreglos y el objeto se exportan congelados (`Object.freeze`).

**Reglas de `transicionPermitida`** (sección 5 de la especificación). Devuelve `true` solo en estos casos:
- `actual` es abierta y `nueva` es **la siguiente** abierta: prospecto → calificado → propuesta → negociacion.
- `actual` es `negociacion` y `nueva` es `ganada`.
- `actual` es cualquier abierta y `nueva` es `perdida`.

En cualquier otro caso devuelve `false`: saltos, retrocesos, la misma etapa, salir de una cerrada, valores que no son una de las seis etapas exactas (`'Calificado'`, `'volando'`) o que no son texto (`undefined`, `null`, `3`).

### 2.2 Casos de `pruebas/etapas.test.js`

| Caso | Resultado |
|---|---|
| prospecto → calificado, calificado → propuesta, propuesta → negociacion | `true` |
| negociacion → ganada | `true` |
| prospecto, calificado, propuesta y negociacion → perdida | `true` (4 casos) |
| prospecto → propuesta, calificado → negociacion (saltos) | `false` |
| calificado → prospecto, negociacion → propuesta (retrocesos) | `false` |
| negociacion → negociacion, prospecto → prospecto (misma etapa) | `false` |
| prospecto → ganada, propuesta → ganada | `false` |
| ganada → perdida, perdida → prospecto, ganada → ganada (desde cerrada) | `false` |
| prospecto → `'Calificado'`, `'volando'`, `''`, `undefined`, `null`, `3` | `false` |
| `PROBABILIDAD` | exactamente `{ prospecto: 10, calificado: 25, propuesta: 50, negociacion: 75, ganada: 100, perdida: 0 }` |
| `ETAPAS`, `ABIERTAS`, `CERRADAS` | `ETAPAS` tiene las 6 en el orden del ENUM. `ABIERTAS` + `CERRADAS` = `ETAPAS`, sin repetidos |

### 2.3 Funciones de `util/formato.js`

| Función | Devuelve, exactamente con estas claves y en este orden |
|---|---|
| `producto(fila)` | `{ id, nombre, precio, requiere_evaluacion }`. `requiere_evaluacion` es `Boolean(...)` |
| `oportunidadResumen(fila)` | `{ id, cliente: { id, nombre }, titulo, etapa, probabilidad, total, fecha_cierre, motivo_perdida }` |
| `oportunidadDetalle(fila, lineas, historial)` | El resumen, más `productos: [{ id, producto_id, nombre, cantidad, precio_unitario, subtotal }]` y `historial: [{ etapa_anterior, etapa_nueva, usuario, fecha }]` |

- `cliente` se arma con las columnas `cliente_id` y `cliente_nombre`.
- `probabilidad` es `PROBABILIDAD[fila.etapa]`.
- `fecha_cierre` y `historial[].fecha` salen con `toISOString()`, o `null`.
- Los montos (`total`, `precio`, `precio_unitario`, `subtotal`) se copian como llegan. El pool usa `decimalNumbers: true`, así que llegan como números.
- Ninguna función devuelve `vendedor_id` ni `usuario_id`.

### 2.4 SQL que ejecuta la API (todo con `?`, textos fijos)

```sql
-- GET /productos
SELECT id, nombre, precio, requiere_evaluacion FROM productos WHERE activo = 1 ORDER BY nombre, id

-- Resumen: dos textos fijos, sin filtro y con filtro (la etapa ya se comparó contra ETAPAS)
SELECT o.id, o.titulo, o.etapa, o.fecha_cierre, o.motivo_perdida,
       c.id AS cliente_id, c.nombre AS cliente_nombre,
       (SELECT COALESCE(SUM(l.cantidad * l.precio_unitario), 0)
          FROM oportunidad_productos l WHERE l.oportunidad_id = o.id) AS total
  FROM oportunidades o JOIN clientes c ON c.id = o.cliente_id
 WHERE o.vendedor_id = ? [AND o.etapa = ?]
 ORDER BY o.created_at DESC, o.id DESC

-- Cabecera del detalle: las mismas columnas
 WHERE o.id = ? AND o.vendedor_id = ? LIMIT 1
-- Líneas (JOIN a productos solo por el nombre, nunca por el precio)
SELECT l.id, l.producto_id, p.nombre, l.cantidad, l.precio_unitario,
       l.cantidad * l.precio_unitario AS subtotal
  FROM oportunidad_productos l JOIN productos p ON p.id = l.producto_id
 WHERE l.oportunidad_id = ? ORDER BY l.id
-- Historial
SELECT h.etapa_anterior, h.etapa_nueva, u.name AS usuario, h.created_at AS fecha
  FROM historial_etapas h JOIN users u ON u.id = h.usuario_id
 WHERE h.oportunidad_id = ? ORDER BY h.created_at, h.id

-- Candado de toda modificación (primer paso dentro de transaccion())
SELECT id, etapa FROM oportunidades WHERE id = ? AND vendedor_id = ? FOR UPDATE

-- POST /oportunidades
SELECT id FROM clientes WHERE id = ? AND vendedor_id = ? LIMIT 1
INSERT INTO oportunidades (cliente_id, vendedor_id, titulo, etapa, created_at, updated_at)
SELECT c.id, ?, ?, 'prospecto', UTC_TIMESTAMP(), UTC_TIMESTAMP()
  FROM clientes c WHERE c.id = ? AND c.vendedor_id = ?
INSERT INTO historial_etapas (oportunidad_id, usuario_id, etapa_anterior, etapa_nueva, created_at)
VALUES (?, ?, ?, ?, UTC_TIMESTAMP())          -- también la usa el cambio de etapa

-- PATCH /oportunidades/:id
UPDATE oportunidades SET titulo = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?

-- PATCH /oportunidades/:id/etapa: dos textos fijos
SELECT COUNT(*) AS lineas FROM oportunidad_productos WHERE oportunidad_id = ?
UPDATE oportunidades SET etapa = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?                     -- avanzar
UPDATE oportunidades SET etapa = ?, motivo_perdida = ?, fecha_cierre = UTC_TIMESTAMP(),
       updated_at = UTC_TIMESTAMP() WHERE id = ?                                                  -- cerrar

-- POST /oportunidades/:id/productos
SELECT id FROM productos WHERE id = ? AND activo = 1 LIMIT 1
SELECT id FROM oportunidad_productos WHERE oportunidad_id = ? AND producto_id = ? LIMIT 1
INSERT INTO oportunidad_productos (oportunidad_id, producto_id, cantidad, precio_unitario, created_at, updated_at)
SELECT ?, p.id, ?, p.precio, UTC_TIMESTAMP(), UTC_TIMESTAMP() FROM productos p WHERE p.id = ? AND p.activo = 1

-- PATCH y DELETE de líneas
SELECT id FROM oportunidad_productos WHERE id = ? AND oportunidad_id = ? LIMIT 1
UPDATE oportunidad_productos SET cantidad = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND oportunidad_id = ?
DELETE FROM oportunidad_productos WHERE id = ? AND oportunidad_id = ?
```

Ningún SQL se arma con `${}` ni con concatenación. Las consultas con y sin filtro, y las de avanzar y cerrar, son textos completos distintos.

---

## 3. Comportamiento

Reglas comunes:
- El dueño sale de `req.usuario.id`. Los campos que el contrato no lista se ignoran: `vendedor_id`, `etapa` al crear, `precio_unitario`, `fecha_cierre` y otros.
- Todo `:id` y `:lineaId` pasa por `idDeRuta()`: si no es un entero positivo, responde 404.
- Toda modificación corre dentro de `transaccion()`. Su primer paso es el **candado** de 2.4: si no hay fila, 404; si la etapa está en `CERRADAS`, 409 `"El plan ya está cerrado y no se puede modificar."`.
- La respuesta de una modificación es el detalle leído **dentro de la misma transacción**, con la conexión de la transacción.
- Lo ajeno responde 404, igual que lo inexistente.

`leerDetalle(ejecutar, id, vendedorId)` corre las tres consultas del detalle (cabecera, líneas e historial) y devuelve `oportunidadDetalle(...)`, o `null` si no hay cabecera. `ejecutar(sql, params)` devuelve filas. Fuera de una transacción es `consulta`; dentro, un envoltorio de `cx.execute`.

**Tipos del cuerpo:** `cliente_id`, `producto_id` y `cantidad` solo se aceptan como **enteros JSON**: `Number.isSafeInteger(valor)`. `"5"`, `5.5`, `null` o la clave ausente dan 422. La SPA convierte con `Number()` antes de enviar.

| Campo | Regla | Mensaje 422 |
|---|---|---|
| `cliente_id` | Entero JSON > 0 y paciente de su cartera | `"Elija un paciente de su cartera."` |
| `titulo` | `texto(valor, 1, 150)` (recortado) | `"Escriba el título (máximo 150 caracteres)."` |
| `producto_id` | Entero JSON > 0 y producto con `activo = 1` | `"Elija un tratamiento activo del catálogo."` |
| `cantidad` | Entero JSON de 1 a 999 | `"La cantidad debe ser un número entero de 1 a 999."` |
| `motivo_perdida` | `texto(valor, 1, 255)` (recortado), solo si `etapa` es `perdida` | `"Escriba el motivo (de 1 a 255 caracteres)."` |

### 3.1 `GET /api/productos`
200 con `filas.map(formato.producto)`: los activos, ordenados por nombre. "Licencia anterior" (inactivo) no aparece.

### 3.2 `GET /api/oportunidades?etapa=`
1. `req.query.etapa`:
   - Ausente: todas.
   - Exactamente una de `ETAPAS`: se filtra por esa etapa.
   - Cualquier otro valor, también `""`, un arreglo o `Ganada`: 400 `"El filtro etapa no es válido."`. Se revisa antes de consultar.
2. 200 con `filas.map(formato.oportunidadResumen)`, de la más reciente a la más antigua (`created_at DESC`, empates por `id DESC`).

### 3.3 `POST /api/oportunidades`
1. `exigirCuerpo` da 400.
2. Validación, con todos los errores en un solo 422:
   - `titulo` según la tabla.
   - `cliente_id`: si no es un entero JSON > 0, error. Si lo es, `SELECT id FROM clientes WHERE id = ? AND vendedor_id = ?`; si no hay fila, error.
3. `transaccion()`:
   1. `INSERT … SELECT` en `oportunidades` (etapa `prospecto`). Si `affectedRows` es 0, porque el panel le quitó el paciente en ese instante, 422 en `cliente_id` y la transacción se revierte.
   2. Historial: `(insertId, req.usuario.id, NULL, 'prospecto')`.
   3. `leerDetalle` con la conexión de la transacción.
4. 201 con el detalle: `etapa: "prospecto"`, `probabilidad: 10`, `total: 0`, `productos: []` y un registro de historial con `etapa_anterior: null`.

### 3.4 `GET /api/oportunidades/:id`
`idDeRuta`. Después `leerDetalle(consulta, …)`: `null` da 404; si no, 200.

### 3.5 `PATCH /api/oportunidades/:id`
1. `idDeRuta` da 404 y `exigirCuerpo` da 400.
2. En la transacción:
   1. Candado: 404 o 409.
   2. `titulo` según la tabla: 422.
   3. `UPDATE`.
   4. Detalle.
3. 200.

Cualquier otro campo del cuerpo se ignora.

### 3.6 `PATCH /api/oportunidades/:id/etapa`
`idDeRuta` da 404 y `exigirCuerpo` da 400. Después, dentro de la transacción, en el orden de la sección 4.4; el primer fallo responde:

1. **404:** el candado no encuentra la fila.
2. **409:** la etapa actual está en `CERRADAS`.
3. **422 en `etapa`:** si `transicionPermitida(actual, etapa)` es `false`, el mensaje es `` `No se puede pasar de ${actual} a ${nueva}.` ``. `nueva` es el valor recibido si es texto, o el marcador fijo `(sin etapa)` si falta o no es texto. Por ejemplo: `"No se puede pasar de prospecto a (sin etapa)."`.
4. **422 en `etapa`:** si `etapa` es `ganada` y el `COUNT(*)` de líneas es 0, el mensaje es `"Agregue al menos un tratamiento antes de aceptar el plan."`.
5. **422 en `motivo_perdida`:** si `etapa` es `perdida` y `texto(motivo_perdida, 1, 255)` es `null`. En las demás etapas, `motivo_perdida` se ignora.
6. Aquí va solo este comentario: `// Regla propia (spec 13): si la etapa nueva es propuesta, negociacion o ganada, revisar la sección 2.3 de la especificación → 409.`
7. Se guarda el cambio:
   - `UPDATE` "avanzar" si `etapa` es abierta. Si es `ganada` o `perdida`, `UPDATE` "cerrar", con `motivo_perdida` recortado en `perdida` y `NULL` en `ganada`. `fecha_cierre` es `UTC_TIMESTAMP()`.
   - `INSERT` del historial `(id, req.usuario.id, actual, etapa)`.
8. Si algún paso lanza 1062 (`ER_DUP_ENTRY`), `transaccion()` hace rollback y la ruta responde **409** `"El plan cambió al mismo tiempo en otra petición. Recárguelo."`. No queda ni el cambio ni el historial.
9. 200 con el detalle.

El `FOR UPDATE` pone en fila las peticiones simultáneas. La segunda espera, lee la etapa ya cambiada y recibe 422 (sección 5: pedir la misma etapa es una transición no permitida). El 1062 es el respaldo.

### 3.7 `POST /api/oportunidades/:id/productos`
`idDeRuta` da 404 y `exigirCuerpo` da 400. Después, en la transacción:

1. Candado: 404 o 409.
2. Validación, con todos los errores en un solo 422:
   - `producto_id`: entero JSON > 0, y luego `SELECT … FROM productos WHERE id = ? AND activo = 1`.
   - `cantidad`: entero JSON de 1 a 999.
3. Repetido: si la consulta previa encuentra la línea, 409 `"Ese tratamiento ya está en el plan; cambie su cantidad."`.
4. Aquí va solo este comentario: `// Regla propia (spec 13): si el producto requiere evaluación y el plan está en propuesta o negociacion, revisar la sección 2.3 → 409.`
5. `INSERT … SELECT` desde `productos`: el precio sale de `p.precio`. `precio_unitario` del cuerpo nunca se lee.
   - Si `affectedRows` es 0, porque el producto se desactivó en ese instante, 422 en `producto_id`.
   - Si da 1062, que es el respaldo de la consulta previa, rollback y el mismo 409 del repetido.
6. 201 con el detalle.

### 3.8 `PATCH /api/oportunidades/:id/productos/:lineaId`
`idDeRuta` de los dos ids da 404 y `exigirCuerpo` da 400. Después, en la transacción:
1. Candado: 404 si el plan no existe o es ajeno.
2. La línea debe ser de ese plan (`WHERE id = ? AND oportunidad_id = ?`): si no, 404.
3. Plan cerrado: 409.
4. `cantidad`: 422.
5. `UPDATE` de la cantidad. `precio_unitario` no cambia.
6. 200 con el detalle.

### 3.9 `DELETE /api/oportunidades/:id/productos/:lineaId`
Igual que 3.8 en los pasos 1 a 3, sin cuerpo ni `exigirCuerpo`. Después, `DELETE … WHERE id = ? AND oportunidad_id = ?` y 204 sin cuerpo (`res.status(204).end()`).

---

## 4. Plan de implementación

Cada paso deja la API arrancable. Las tablas todavía no existen.
- Los pasos 1 y 2 no necesitan la base.
- Los pasos 3 a 8 se prueban sin la base solo hasta donde se pueda: 400 de filtro y de cuerpo, 401, 404 por id inválido y el 422 de `POST /oportunidades` cuando no llega a consultar. Se usa una app temporal con `req.usuario` falso, como en el spec 08.
- Al final de cada paso se anota qué falta probar con la semilla.

1. **`util/etapas.js` y `pruebas/etapas.test.js`.** Primero las pruebas con los casos de 2.2 y luego el módulo. Prueba: `npm test` en verde, junto con NIT, teléfono y fechas.
2. **Formato.** Las funciones `producto`, `oportunidadResumen` y `oportunidadDetalle` en `formato.js`. Prueba sin base: un comando corto de Node con filas de ejemplo debe dar exactamente las claves de 2.3, `requiere_evaluacion` booleano, `probabilidad` según la etapa, fechas en ISO o `null`, y ningún `vendedor_id` ni `usuario_id` aunque vengan en la fila.
3. **`GET /api/productos`.** `rutas/productos.js` y el montaje en `app.js`. Prueba sin base: sin token da 401.
4. **Lecturas de oportunidades.** `rutas/oportunidades.js` con `leerDetalle`, `GET /` y `GET /:id`, y el montaje. Prueba sin base: `?etapa=volando`, `?etapa=`, `?etapa=Ganada` y `?etapa=a&etapa=b` dan 400 con el mensaje; `/oportunidades/abc` da 404; sin token da 401.
5. **`POST /api/oportunidades`.** Sección 3.3. Prueba sin base: sin cuerpo da 400; `{ "cliente_id": "5", "titulo": "" }` da un 422 con `cliente_id` y `titulo`, porque no llega a consultar.
6. **`PATCH /:id` y `PATCH /:id/etapa`.** Secciones 3.5 y 3.6, con el comentario de la regla propia. Prueba sin base: `/oportunidades/abc` y `/oportunidades/abc/etapa` dan 404; sin cuerpo da 400.
7. **`POST /:id/productos`.** Sección 3.7, con el comentario de la regla propia. Prueba sin base: `/oportunidades/0/productos` da 404; sin cuerpo da 400.
8. **`PATCH` y `DELETE` de líneas.** Secciones 3.8 y 3.9. Prueba sin base: `/oportunidades/1/productos/abc` da 404 con los dos métodos, y el `PATCH` sin cuerpo da 400.
9. **Postman.**
   - Script de la colección: la prueba "La respuesta es JSON" se salta cuando el código es 204.
   - Carpeta "Planes", que supone una semilla recién cargada y se ejecuta en orden. Cada petición prueba su código. Un script de la carpeta confirma que ningún JSON trae `password`, `vendedor_id`, `user_id`, `supervisor_id` ni `usuario_id`.
   - Al terminar, el plan nuevo queda en `perdida`, así que la carpeta no cambia las ventas ni el pronóstico de Valeria.
   1. Login de Diego. `GET /oportunidades` guarda en `idPlanAjeno` el id de "Licencias de caja". `GET /clientes` guarda en `idClienteAjeno` el de Farmacia Los Altos.
   2. Login de Valeria. `GET /clientes` guarda en `idClienteValeria` el de Ferretería Xela.
   3. `GET /productos`:
      - Cada elemento tiene exactamente `id`, `nombre`, `precio` y `requiere_evaluacion`, y este último es booleano.
      - Vienen ordenados por nombre y "Licencia anterior" no está.
      - Guarda `idPlanAvanzado` y `idPlanBasico`.
   4. `GET /oportunidades`:
      - Cada elemento tiene exactamente las 8 claves del resumen.
      - Guarda `idRenovacion` ("Renovación de planes") e `idPlanFamiliar` ("Plan familiar").
      - "Licencias de caja" no aparece.
   5. `GET /oportunidades?etapa=ganada`: todas en `ganada`. `?etapa=volando` y `?etapa=` dan 400 con el mensaje.
   6. `GET /oportunidades/{{idRenovacion}}`:
      - `total: 5650`.
      - La línea de Plan avanzado con `precio_unitario: 2600`. Guarda su id en `idLineaRenovacion`.
      - 5 registros de historial; el primero con `etapa_anterior: null`.
      - Exactamente las 10 claves del detalle.
   7. `GET /oportunidades/{{idPlanFamiliar}}`: guarda en `idLicenciaAnterior` el `producto_id` de su línea "Licencia anterior".
   8. `GET /oportunidades/{{idPlanAjeno}}` da 404. `GET /oportunidades/abc` da 404.
   9. `POST /oportunidades` con `cliente_id: {{idClienteAjeno}}` da 422 en `cliente_id`. Con `{ "cliente_id": "5", "titulo": "" }` da 422 en `cliente_id` y en `titulo`.
   10. `POST /oportunidades` válido, con `cliente_id: {{idClienteValeria}}`, `titulo: "Plan de Postman"`, `"etapa": "ganada"` y `"vendedor_id": {{idDiego}}`. Da 201 con `etapa: "prospecto"`, `probabilidad: 10`, `total: 0`, `productos: []` y un registro de historial (`etapa_anterior: null`, `etapa_nueva: "prospecto"`, `usuario: "Valeria López"`). Guarda `idPlanNuevo`.
   11. `PATCH /oportunidades/{{idPlanNuevo}}` con `{ "titulo": "" }` da 422. Con `{ "titulo": "Plan de Postman editado" }` da 200 con el título nuevo.
   12. `POST /oportunidades/{{idPlanNuevo}}/productos` con `producto_id: {{idPlanAvanzado}}`, `cantidad: 1` y `"precio_unitario": 1`. Da 201 con la línea en `precio_unitario: 2750.5`, `subtotal: 2750.5` y `total: 2750.5`. Guarda `idLineaNueva`.
   13. El mismo POST otra vez da 409 con el mensaje del repetido.
   14. POST con `producto_id: {{idLicenciaAnterior}}` da 422 en `producto_id`. POST con `producto_id: {{idPlanBasico}}` y `cantidad: 0`, `1000` o `"2"` da 422 en `cantidad`.
   15. `PATCH /oportunidades/{{idPlanNuevo}}/productos/{{idLineaNueva}}`: con `cantidad: 3` da 200 con `subtotal: 8251.5` y `total: 8251.5`; con `cantidad: 1000` da 422.
   16. `PATCH /oportunidades/{{idPlanNuevo}}/productos/{{idLineaRenovacion}}` da 404, porque la línea es de otro plan. `DELETE` igual da 404.
   17. `PATCH /oportunidades/{{idPlanNuevo}}/etapa`:
       - `{ "etapa": "propuesta" }` da 422 con `"No se puede pasar de prospecto a propuesta."`.
       - `{}` da 422 con `"No se puede pasar de prospecto a (sin etapa)."`.
       - `GET` del plan: el historial sigue con 1 registro.
   18. `PATCH …/etapa` a `calificado`, luego a `propuesta` y luego a `negociacion`: cada uno da 200 y el historial termina con 4 registros. `negociacion` otra vez da 422.
   19. `DELETE /oportunidades/{{idPlanNuevo}}/productos/{{idLineaNueva}}` da 204 sin cuerpo.
   20. `PATCH …/etapa` con `{ "etapa": "ganada" }` da 422 con `"Agregue al menos un tratamiento antes de aceptar el plan."`.
   21. `PATCH …/etapa` con `{ "etapa": "perdida", "motivo_perdida": "   " }` da 422 en `motivo_perdida`.
   22. `PATCH …/etapa` con `{ "etapa": "perdida", "motivo_perdida": "  Prefiere otra clínica  " }` da 200 con `etapa: "perdida"`, `probabilidad: 0`, `motivo_perdida: "Prefiere otra clínica"`, `fecha_cierre` distinto de `null` y 5 registros de historial.
   23. Con `idPlanFamiliar` (perdida): `PATCH` del título, `PATCH …/etapa` a `prospecto` y `POST …/productos` con `idPlanBasico` dan 409 con `"El plan ya está cerrado y no se puede modificar."`.
   24. Con `idPlanAjeno`: `PATCH` del título, `PATCH …/etapa` y `POST …/productos` dan 404.

---

## 5. Criterios de aceptación

Base: `php artisan migrate:fresh --seeder=VerificacionSeeder`, `npm run dev` con un `api/.env` válido y el token de cada asesor obtenido con `POST /api/auth/login`.

**Etapas (sin base)**
- **CA1.** `npm test` corre `pruebas/etapas.test.js` con todos los casos de 2.2 en verde, junto con NIT, teléfono y fechas, sin la API ni la base encendidas.

**A4 · Productos y lecturas**
- **CA2.** Dado Valeria, cuando pide `GET /api/productos`, entonces recibe los 4 activos ordenados por nombre, sin "Licencia anterior". Cada uno tiene exactamente `id`, `nombre`, `precio` (número) y `requiere_evaluacion: false` (booleano).
- **CA3.** Dado Valeria, cuando pide el detalle de "Renovación de planes", entonces recibe `total: 5650`, Plan avanzado con `precio_unitario: 2600` y 5 registros de historial; el primero tiene `etapa_anterior: null` y `usuario` es un nombre, no un id.
- **CA4.** Dado Valeria, cuando pide `GET /api/oportunidades`, entonces cada elemento tiene exactamente `id`, `cliente`, `titulo`, `etapa`, `probabilidad`, `total`, `fecha_cierre` y `motivo_perdida`, y vienen ordenados por `created_at` descendente. Lo comprueba `SELECT id FROM oportunidades WHERE vendedor_id = <Valeria> ORDER BY created_at DESC, id DESC`.
- **CA5.** Dado Valeria, cuando pide `?etapa=volando` o `?etapa=`, entonces 400 `{ "error": "El filtro etapa no es válido." }`. Con `?etapa=ganada`, solo planes en `ganada`.
- **CA6.** Dado Valeria y "Licencias de caja" (de Diego), cuando pide `GET /api/oportunidades/:id`, entonces 404 `{ "error": "No se encontró el recurso." }`. Lo mismo con `PATCH /:id`, `PATCH /:id/etapa` y `POST /:id/productos`, y en la base el plan no cambia.

**A4 · Crear y editar**
- **CA7.** Dado Valeria, cuando crea un plan con un `cliente_id` de Diego, entonces 422 en `cliente_id`. Con `{ "cliente_id": "5", "titulo": "" }`, un solo 422 con `cliente_id` y `titulo`.
- **CA8.** Dado Valeria, cuando crea un plan válido con `"etapa": "ganada"` y `"vendedor_id": <Diego>` en el cuerpo, entonces 201 con `etapa: "prospecto"` y un registro de historial con `etapa_anterior: null`. `SELECT vendedor_id FROM oportunidades WHERE id = <nuevo>` da el id de Valeria.

**A4 · Tratamientos del plan**
- **CA9.** Dado un plan nuevo, cuando Valeria le agrega "Plan avanzado" con `"precio_unitario": 1` en el cuerpo, entonces la línea guarda `precio_unitario: 2750.5`. Si después se ejecuta `UPDATE productos SET precio = 3000 WHERE nombre = 'Plan avanzado'`, el detalle sigue mostrando 2750.5.
- **CA10.** Dado un plan con "Plan avanzado", cuando se agrega otra vez, entonces 409 `"Ese tratamiento ya está en el plan; cambie su cantidad."`. "Licencia anterior" (inactivo) da 422 en `producto_id`. `cantidad` 0, 1000, `"2"` o `2.5` da 422 en `cantidad`.
- **CA11.** Dado una línea, cuando se cambia su cantidad a 3, entonces 200 con `subtotal` y `total` recalculados y el mismo `precio_unitario`. La línea de otro plan con el `:id` de este responde 404 en `PATCH` y en `DELETE`. `DELETE` de una línea propia responde 204 sin cuerpo.

**A4 · Embudo**
- **CA12.** Dado un plan en `prospecto`, cuando se pide `propuesta`, entonces 422 `{ "errores": { "etapa": "No se puede pasar de prospecto a propuesta." } }` y `SELECT COUNT(*) FROM historial_etapas WHERE oportunidad_id = <id>` no cambia. Con `{}`, el mensaje termina en `a (sin etapa).`.
- **CA13.** Dado un plan, cuando avanza `prospecto → calificado → propuesta → negociacion`, entonces cada paso da 200 y agrega exactamente un registro con su `etapa_anterior`. Pedir otra vez `negociacion` da 422.
- **CA14.** Dado un plan en `negociacion` sin tratamientos, cuando se pide `ganada`, entonces 422 `"Agregue al menos un tratamiento antes de aceptar el plan."`.
- **CA15.** Dado un plan abierto, cuando se pide `perdida` con `motivo_perdida: "   "`, entonces 422 en `motivo_perdida`. Con `"  Prefiere otra clínica  "`, 200 con el motivo recortado, `probabilidad: 0` y `fecha_cierre` en ISO UTC.
- **CA16.** Dado un plan en `negociacion` con un tratamiento, cuando se pide `ganada` (a mano, y después se recarga la semilla), entonces 200 con `probabilidad: 100`, `motivo_perdida: null` y `fecha_cierre` igual a la hora UTC del cambio. En la base, `fecha_cierre` no es `NULL` y `motivo_perdida` sí.
- **CA17.** Dado "Plan familiar" (perdida), cuando se pide cambiar su título, su etapa o sus productos, o cambiar o borrar una de sus líneas, entonces 409 `"El plan ya está cerrado y no se puede modificar."`.
- **CA18.** Dado un plan en `prospecto`, cuando llegan 5 `PATCH /:id/etapa` simultáneos a `calificado`, entonces exactamente uno responde 200, los demás 422 o 409, y el historial tiene un solo registro `prospecto → calificado`. Se prueba con un comando de Node con `Promise.all` que no se guarda; el script definitivo es del spec 23.

**S3, S4, S5 y sesión**
- **CA19.** Sin token, `GET /api/productos`, `GET /api/oportunidades` y `PATCH /api/oportunidades/1/etapa` responden 401 `"Falta el token de acceso."`.
- **CA20.** `git grep -nE '\$\{' api/src/rutas/oportunidades.js api/src/rutas/productos.js`: ningún resultado está dentro de un texto SQL. El único es el mensaje "No se puede pasar de…".
- **CA21.** Toda consulta de `rutas/oportunidades.js` que lee o bloquea una oportunidad lleva `vendedor_id = ?`. Toda consulta de una línea lleva `oportunidad_id = ?`, y siempre corre después del candado de esa oportunidad.
- **CA22.** Ninguna respuesta trae `password`, `vendedor_id`, `user_id`, `supervisor_id` ni `usuario_id`. Los montos son números (`5650`, no `"5650.00"`). Todas las respuestas con cuerpo traen `Content-Type: application/json`.
- **CA23.** `git grep -n "Regla propia (spec 13)" api/src/rutas/oportunidades.js` da exactamente 2 resultados: el cambio de etapa y el agregar un tratamiento.

**Postman**
- **CA24.** La carpeta "Planes" pasa todas sus peticiones en verde, ejecutada en orden sobre una semilla recién cargada. Las carpetas "Sesión", "Clientes" y "Actividades" siguen en verde con el script de la colección ajustado para el 204.

---

## 6. Decisiones tomadas y descartadas

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| Primero `etapas.js` y sus pruebas, después las rutas | Empezar por las rutas | Las tablas todavía no existen. Las transiciones se prueban sin base |
| `transicionPermitida` devuelve `false` para todo lo que no sea una de las tres reglas | Lanzar un error con valores inválidos | Una sola pregunta, una sola respuesta. La ruta arma el 422 |
| `PROBABILIDAD` con porcentajes enteros (10 … 100) | Decimales (0.10 …) | Así lo pide la forma del contrato (`"probabilidad": 100`) y coincide con `Oportunidad::PROBABILIDAD` del panel. El pronóstico en SQL (spec 15) usa sus propios decimales |
| Toda modificación empieza con `SELECT … FOR UPDATE` de la oportunidad con `vendedor_id = ?` | Revisar sin candado | Pone en fila las peticiones al mismo plan. La segunda ve el estado ya cambiado (S8) |
| La respuesta se lee dentro de la transacción | Leerla después del COMMIT | Devuelve exactamente lo escrito, aunque el panel reasigne el plan justo después |
| Dos textos SQL fijos para el resumen (con y sin `etapa`) | `AND (? IS NULL OR o.etapa = ?)` | El mismo criterio de textos completos que el spec 08. Se lee y se revisa fácil |
| `total` con una subconsulta `COALESCE(SUM(...), 0)` | `LEFT JOIN … GROUP BY` | Un plan sin líneas da 0 y no `null`, y no hay que agrupar las demás columnas |
| `JOIN productos` en las líneas solo por el nombre | Leer también `productos.precio` | El precio que vale es el guardado (error 2 de la sección 11.4) |
| Etapa ausente o que no es texto: `"No se puede pasar de <actual> a (sin etapa)."` | `String(valor)` ("a undefined"), o un mensaje nuevo | Decisión de la integrante. Cumple el contrato sin cambiarlo y se lee bien en la SPA |
| `cliente_id`, `producto_id` y `cantidad` solo como enteros JSON | Aceptar también `"5"` | Decisión de la integrante. El contrato pide números; igual que el spec 08 con `completada`. La SPA convierte con `Number()` |
| `POST /oportunidades`: `SELECT` previo para juntar los 422, e `INSERT … SELECT` como respaldo atómico | Solo el `INSERT … SELECT` | El asesor recibe todos los errores juntos. Si el panel le quita el paciente en ese instante, no se inserta nada |
| En las líneas, primero los 404 (plan y luego línea), después el 409 de plan cerrado y al final el 422 | 409 antes que el 404 de la línea | Lo inexistente o ajeno responde igual en cualquier estado del plan |
| El 1062 del historial y el de la línea repetida se atrapan fuera de `transaccion()`, que ya hizo rollback | Atraparlos dentro | `transaccion()` revierte y vuelve a lanzar el error, así no queda nada a medias |
| `PATCH /oportunidades/:id` (título) entra en este spec | Dejarlo para después | Es parte del contrato de A4, y CA17 (Plan familiar → 409) lo necesita |
| La carpeta "Planes" cierra el plan nuevo como `perdida` | Ganarlo en Postman | Una venta nueva cambiaría las cifras de noviembre de Valeria (A6). `ganada` se prueba a mano (CA16) y luego se recarga la semilla |
| El script de la colección salta "La respuesta es JSON" en un 204 | Probar el `DELETE` fuera de la colección | El contrato dice "204 sin cuerpo", y sin cuerpo no hay `Content-Type` |
| La regla propia solo como comentario | Programarla ya | Es el spec 13. Así este spec se aprueba y se prueba con la semilla de verificación, donde la regla nunca se activa |

---

## 7. Riesgos

| Riesgo | Mitigación |
|---|---|
| `docs/enunciado.txt` no está en `docs/`. R5 y R6 se citan como las dio la integrante | Confirmarlas contra el enunciado cuando el archivo esté en el repositorio |
| Las tablas no existen y las rutas se programan sin base | Cada paso anota qué falta probar. CA2 a CA18 y la carpeta de Postman se corren con la semilla |
| Los dueños de la semilla no son los que se suponen: "Renovación de planes" y "Plan familiar" de Valeria, "Licencias de caja" de Diego | Comprobarlo con `SELECT titulo, vendedor_id FROM oportunidades` en cuanto exista la semilla. Si cambian, se ajustan los CA y las peticiones de Postman, no el código |
| Los nombres de los índices únicos no son los de la convención de Laravel | Se leen de constantes. Se confirman con `SHOW INDEX FROM oportunidad_productos` y `SHOW INDEX FROM historial_etapas` |
| `SUM` o `cantidad * precio_unitario` llegan como texto | El pool usa `decimalNumbers: true`. CA22 y las pruebas de Postman (`total: 5650`) lo detectan |
| Un mensaje "No se puede pasar de X a <texto>" repite texto del usuario | Va dentro de JSON y la SPA lo pasa por `escapar()` (S7). No llega al SQL |
| El panel reasigna un plan abierto mientras la API lo modifica | El panel bloquea `users → clientes → oportunidades` y la API bloquea solo la oportunidad. Uno espera al otro sin bloqueo mutuo |
| La carpeta "Planes" crea datos y depende del orden | Se corre después de `migrate:fresh --seeder=VerificacionSeeder`, en orden, como dice la descripción de la carpeta. Deja el plan nuevo en `perdida` |

---

## Lo que **no** entra en este spec

- La regla propia y su carpeta de Postman (spec 13).
- `GET /reportes/resumen` y el pronóstico (spec 15).
- `pruebas/simultaneas.js` y `docs/verificacion.md` (spec 23).
- Borrar oportunidades y cambiar el precio de una línea.
- Las vistas del embudo y del plan en la SPA (specs 19 y 21).
