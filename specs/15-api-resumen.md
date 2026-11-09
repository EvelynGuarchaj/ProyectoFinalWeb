# 15 · API del resumen del mes

**Estado:** Implementado
**Depende de:** SPEC 01 (`01-esquema-y-semilla`: tablas `clientes`, `oportunidades` y `oportunidad_productos`, y la semilla de verificación), SPEC 02 (`02-api-base-login`: `db.js` con `decimalNumbers: true` y `timezone: 'Z'`, token y errores) y SPEC 06 (`06-api-clientes`: la constante `MAXIMO_CARTERA` de `rutas/clientes.js`). La carpeta de Postman sigue el formato de las carpetas de los specs 06, 08, 11 y 13.
**Fecha:** 2026-11-08
**Dueña:** B
**Rama:** `spec-15-api-resumen`
**Objetivo:** que `GET /api/reportes/resumen?mes=AAAA-MM` devuelva al asesor sus ventas del mes, el total vendido, su pronóstico y su cartera, calculados en SQL y entregados como números.

**Requisitos que cumple:**
- A6 de `docs/especificacion.md` (sección 7): los cuatro Dado/Cuando/Entonces.
- El endpoint `GET /reportes/resumen?mes=AAAA-MM` de la sección 4.4 y la forma "Resumen del mes" de la sección 4.3.
- R7 del enunciado, en su parte de API: las filas de Valeria, Diego y Carlos de la sección 11.1 y los tres primeros criterios de 11.5.
- Las prevenciones 1 a 5 de la sección 11.4: mes en hora de Guatemala, precio guardado, DECIMAL como número, pronóstico sin filtro de mes y probabilidades en SQL.
- La parte "resumen" de A7 (ninguna llave interna en la respuesta) y de A8 (petición en Postman).

---

## 1. Alcance

**Incluye**
- `api/src/util/cartera.js`, con `MAXIMO_CARTERA = 10`. `rutas/clientes.js` deja de declarar la suya y la importa de ahí.
- `api/src/util/mes.js`, con `FORMATO_MES`, `esMesValido(texto)` y `mesActualGuatemala(ahora)`.
- `api/pruebas/mes.test.js`, con `node:test` y sin base. El script `test` ya incluye todo `pruebas/*.test.js`.
- `api/src/rutas/reportes.js`, con `GET /resumen`, montado en `app.js` como `/api/reportes` (detrás de `exigirToken`).
- La carpeta "Resumen" de `docs/api.postman_collection.json`, con las cifras de control. No necesita variables nuevas: `correo_valeria`, `correo_diego`, `correo_carlos`, los `token_*` y `clave` ya existen.

**No incluye**
- El reporte del panel por asesor, equipo, empresa y tratamiento (A, spec 14). Las cifras de equipo y de empresa de 11.1 y la tabla 11.2 no salen de la API.
- La vista `#/resumen` y el contador "x de 10" de la SPA (A, specs 22 y 18).
- Resumen de otro asesor o de un equipo: la API solo responde por el asesor del token.
- Rangos de fechas, varios meses o meses en otra zona horaria.
- Tablas, columnas o índices nuevos. La API nunca ejecuta `CREATE` ni `ALTER`.
- `docs/verificacion.md` (spec 23).
- Paquetes nuevos.

---

## 2. Modelo de datos

Este spec no crea tablas ni columnas. Lee, siempre filtrando por `vendedor_id = req.usuario.id`:

| Tabla | Columnas | Para qué |
|---|---|---|
| `oportunidades` | `id`, `vendedor_id`, `etapa`, `fecha_cierre` | Ventas del mes y planes abiertos |
| `oportunidad_productos` | `oportunidad_id`, `cantidad`, `precio_unitario` | Total y pronóstico, con el precio **guardado** en la línea |
| `clientes` | `vendedor_id` | Cartera |

Nunca se une a `productos`: el precio del catálogo no entra en ninguna cifra (sección 11.4, error 2).

### 2.1 `util/cartera.js`

```js
// Cartera máxima de un asesor (regla del proyecto). La usan rutas/clientes.js y rutas/reportes.js.
export const MAXIMO_CARTERA = 10;
```

`rutas/clientes.js` borra su `const MAXIMO_CARTERA = 10;` y su comentario, y agrega `import { MAXIMO_CARTERA } from '../util/cartera.js';`. Sus mensajes y su `>=` no cambian.

### 2.2 `util/mes.js`

```js
export const FORMATO_MES = /^\d{4}-(0[1-9]|1[0-2])$/;
export function esMesValido(texto) { /* true solo si es string y cumple FORMATO_MES */ }
export function mesActualGuatemala(ahora = new Date()) {
  /* new Date(ahora.getTime() - 6 * 3600 * 1000).toISOString().slice(0, 7) */
}
```

- `ahora` se recibe para probar sin depender del reloj, como `formato.actividad`.
- Guatemala es UTC−6 fijo, sin horario de verano (CLAUDE.md, fechas).

### 2.3 Respuesta

```json
{ "mes": "2026-10", "ventas": 2, "total": 9250, "pronostico": 2512.88,
  "cartera": { "clientes": 3, "maximo": 10 } }
```

- `mes`: el mes consultado (el que llegó o el actual de Guatemala).
- `ventas`, `total`, `pronostico` y `cartera.clientes` salen tal cual de las filas de MySQL. `decimalNumbers: true` (spec 02) los entrega como números. JavaScript no suma, no multiplica ni redondea nada.
- `cartera.maximo` es `MAXIMO_CARTERA`.
- Sin `id`, `vendedor_id` ni ninguna otra llave.

### 2.4 SQL

Cuatro textos fijos en `rutas/reportes.js`, con parámetros `?`. Los literales de etapa y las probabilidades son fijos, nunca datos de la petición.

```sql
-- SQL_VENTAS · parámetros [vendedorId, mes]
SELECT COUNT(*) AS ventas
  FROM oportunidades o
 WHERE o.vendedor_id = ? AND o.etapa = 'ganada'
   AND DATE_FORMAT(o.fecha_cierre - INTERVAL 6 HOUR, '%Y-%m') = ?

-- SQL_TOTAL · parámetros [vendedorId, mes]
SELECT COALESCE(SUM(l.cantidad * l.precio_unitario), 0) AS total
  FROM oportunidades o
  JOIN oportunidad_productos l ON l.oportunidad_id = o.id
 WHERE o.vendedor_id = ? AND o.etapa = 'ganada'
   AND DATE_FORMAT(o.fecha_cierre - INTERVAL 6 HOUR, '%Y-%m') = ?

-- SQL_PRONOSTICO · parámetros [vendedorId] · sin filtro de mes
SELECT ROUND(COALESCE(SUM(l.cantidad * l.precio_unitario * CASE o.etapa
         WHEN 'prospecto' THEN 0.10 WHEN 'calificado' THEN 0.25
         WHEN 'propuesta' THEN 0.50 WHEN 'negociacion' THEN 0.75 END), 0), 2) AS pronostico
  FROM oportunidades o
  JOIN oportunidad_productos l ON l.oportunidad_id = o.id
 WHERE o.vendedor_id = ?
   AND o.etapa IN ('prospecto', 'calificado', 'propuesta', 'negociacion')

-- SQL_CARTERA · parámetros [vendedorId]
SELECT COUNT(*) AS clientes FROM clientes WHERE vendedor_id = ?
```

- `ventas` se cuenta sin unir las líneas: así un plan con varias líneas cuenta una sola vez.
- El pronóstico redondea la suma, no cada plan. Valeria: 2,750.50 × 0.75 + 900.00 × 0.50 = 2,512.875 → **2512.88**.
- Un plan abierto sin líneas no aporta al pronóstico (el `JOIN` no lo trae). Es lo mismo que sumar 0.

---

## 3. Comportamiento

### `GET /api/reportes/resumen`

1. **401:** sin token o con token inválido (lo hace `exigirToken`, spec 02).
2. Lee `req.query.mes`:
   - Si es `undefined`, usa `mesActualGuatemala()`.
   - Si no, exige `esMesValido(req.query.mes)`. Si falla → **400** `peticionMala('Escriba el mes con el formato AAAA-MM.')`.
   - Cuenta como inválido: `?mes=` vacío, `?mes=2026-13`, `?mes=2026-00`, `?mes=2026-1`, `?mes=%202026-10` (con espacio) y `?mes=2026-10&mes=2026-11` (Express lo entrega como arreglo).
3. Ejecuta las cuatro consultas de 2.4 con `consulta()` y `Promise.all`. No hace falta transacción: solo se lee.
4. **200** con la forma de 2.3.

El 400 se revisa antes de tocar la base: un mes inválido nunca ejecuta SQL.

---

## 4. Plan de implementación

Cada paso deja la API arrancable. La base todavía no existe: los pasos 1 a 3 se prueban sin ella hasta donde se puede. Las cifras se comprueban cuando exista, con la carpeta del paso 4.

1. **`util/cartera.js`.** Se crea (sección 2.1) y `rutas/clientes.js` la importa.
   Prueba sin base: `npm test` en verde. `git grep -n "MAXIMO_CARTERA = 10" api/src` da 1 resultado, en `util/cartera.js`. `node --check api/src/rutas/clientes.js` no da error.
2. **`util/mes.js` y `pruebas/mes.test.js`.** Primero las pruebas. Casos:
   - `esMesValido` da `true` con `'2026-10'`, `'2026-01'` y `'2026-12'`.
   - `esMesValido` da `false` con `'2026-13'`, `'2026-00'`, `'2026-1'`, `'26-10'`, `''`, `' 2026-10'`, `'2026-10 '`, `'2026/10'`, `undefined`, `null`, `202610` (número) y `['2026-10']` (arreglo).
   - `mesActualGuatemala(new Date('2026-11-01T03:30:00Z'))` da `'2026-10'`: es la venta "Equipo para sucursal" (sección 11.4, error 1).
   - `mesActualGuatemala(new Date('2026-11-01T06:00:00Z'))` da `'2026-11'`.
   - `mesActualGuatemala(new Date('2027-01-01T05:59:59Z'))` da `'2026-12'`: el cambio de año también va en hora de Guatemala.

   Prueba: `npm test` en verde, junto con NIT, teléfono, fechas, etapas y regla propia.
3. **`rutas/reportes.js` y `app.js`.** La ruta de la sección 3 con el SQL de 2.4, y en `app.js` `import { rutasReportes } from './rutas/reportes.js';` y `api.use('/reportes', rutasReportes);` después de `/oportunidades`.
   Prueba sin base: `node --check` de los dos archivos. `git grep -nE '\$\{' api/src/rutas/reportes.js` no da resultados. `git grep -n "JOIN productos" api/src/rutas/reportes.js` no da resultados.
4. **Postman: carpeta "Resumen"**, la última de la colección.
   - Descripción: "A6 y cifras de control de la sección 11.1 (spec 15). Ejecútela sola, justo después de `php artisan migrate:fresh --seeder=VerificacionSeeder`: las carpetas Clientes, Planes y Regla propia cambian la cartera y las ventas. Solo lee, así que se puede repetir sin recargar la semilla. Necesita las variables correo_valeria, correo_diego, correo_carlos y clave."
   - El mismo script de carpeta que las demás: ninguna respuesta trae `password`, `vendedor_id`, `user_id`, `supervisor_id` ni `usuario_id`.
   - Cada petición prueba su código. Las de 200 comprueban el cuerpo completo con `pm.expect(json).to.eql({...})`, que falla si un monto llega como texto (`"9250.00"`).

   Peticiones:
   1. Login de Valeria → 200, guarda `token_valeria`.
   2. Login de Diego → 200, guarda `token_diego`.
   3. Login de Carlos → 200, guarda `token_carlos`.
   4. Valeria `?mes=2026-10` → 200 `{ "mes": "2026-10", "ventas": 2, "total": 9250, "pronostico": 2512.88, "cartera": { "clientes": 3, "maximo": 10 } }`.
   5. Valeria `?mes=2026-11` → 200 `{ "mes": "2026-11", "ventas": 1, "total": 3600, "pronostico": 2512.88, "cartera": { "clientes": 3, "maximo": 10 } }`.
   6. Diego `?mes=2026-10` → 200 `{ "mes": "2026-10", "ventas": 1, "total": 6000, "pronostico": 112.5, "cartera": { "clientes": 2, "maximo": 10 } }`.
   7. Carlos `?mes=2026-10` → 200 `{ "mes": "2026-10", "ventas": 1, "total": 7200, "pronostico": 4125.75, "cartera": { "clientes": 9, "maximo": 10 } }`.
   8. Valeria `?mes=2020-01` (mes sin ventas) → 200 con `ventas: 0`, `total: 0` (número, no `null`), `pronostico: 2512.88` y cartera 3 de 10.
   9. Valeria sin `mes` → 200. El script calcula el mes actual de Guatemala con la misma expresión de 2.2 y comprueba que `mes` es igual. `pronostico` es 2512.88 y la cartera 3 de 10.
   10. Valeria `?mes=2026-13` → 400 `{ "error": "Escriba el mes con el formato AAAA-MM." }`.
   11. Valeria `?mes=2026-1` → 400 con el mismo cuerpo.
   12. Sin token `?mes=2026-10` → 401.

---

## 5. Criterios de aceptación

Base de los criterios con datos: `php artisan migrate:fresh --seeder=VerificacionSeeder`, `npm run dev` con un `api/.env` válido y el token de cada asesor.

**Sin base**
- **CA1.** `npm test` corre `pruebas/mes.test.js` con los casos del paso 2 en verde, junto con las pruebas anteriores, sin la API ni la base encendidas.
- **CA2.** `git grep -n "MAXIMO_CARTERA = 10" api/src` da exactamente 1 resultado, en `api/src/util/cartera.js`. `rutas/clientes.js` y `rutas/reportes.js` la importan de ahí.
- **CA3.** `git grep -nE '\$\{' api/src/rutas/reportes.js` no da resultados: los cuatro textos SQL son fijos con `?`.
- **CA4.** `git grep -n "productos p\|JOIN productos" api/src/rutas/reportes.js` no da resultados: ninguna cifra usa el precio del catálogo.
- **CA5.** En `rutas/reportes.js` no hay `+`, `*`, `reduce`, `toFixed` ni `Math.round` aplicados a `ventas`, `total`, `pronostico` ni `clientes`: los valores de la respuesta son los de las filas.

**A6 y sección 11**
- **CA6.** Dado Valeria, cuando pide `GET /api/reportes/resumen?mes=2026-10`, entonces responde 200 con exactamente `{ "mes": "2026-10", "ventas": 2, "total": 9250, "pronostico": 2512.88, "cartera": { "clientes": 3, "maximo": 10 } }`, con los montos como números y no como texto.
- **CA7.** Dado Valeria, cuando pide `?mes=2026-11`, entonces recibe `ventas: 1`, `total: 3600` y `pronostico: 2512.88` (el pronóstico no cambia con el mes).
- **CA8.** Dado Diego, cuando pide `?mes=2026-10`, entonces recibe 1, 6000, 112.5 y 2 de 10.
- **CA9.** Dado Carlos, cuando pide `?mes=2026-10`, entonces recibe 1, 7200, 4125.75 y 9 de 10.
- **CA10.** Dado Valeria, cuando pide `?mes=2026-13`, entonces responde 400 `{ "error": "Escriba el mes con el formato AAAA-MM." }`.
- **CA11.** Dado Valeria, cuando pide sin `mes`, entonces responde 200 y `mes` es el mes actual en hora de Guatemala.
- **CA12.** Dado un mes sin ventas, cuando se pide, entonces `ventas` es `0` y `total` es `0` (números, no `null`), y `pronostico` y `cartera` no cambian.
- **CA13.** Dado la semilla de verificación, cuando el administrador cambia en el catálogo el precio de Plan avanzado a Q3,000.00, entonces CA6 a CA9 siguen dando las mismas cifras.
- **CA14.** Dado cualquier respuesta del resumen, cuando se revisa, entonces no trae `password`, `vendedor_id`, `user_id`, `supervisor_id` ni `usuario_id`.

**Postman**
- **CA15.** La carpeta "Resumen" pasa sus 12 peticiones en verde, ejecutada sola justo después de `php artisan migrate:fresh --seeder=VerificacionSeeder`, y otra vez en verde si se repite sin recargar la semilla.

---

## 6. Decisiones tomadas y descartadas

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| `MAXIMO_CARTERA` en `util/cartera.js` | Exportarla desde `rutas/clientes.js` | Decisión de la integrante. Una ruta no importa de otra; el comentario de `clientes.js` ya anunciaba el cambio |
| Mes por defecto: `new Date(Date.now() - 6h).toISOString().slice(0, 7)` | `Intl` con `America/Guatemala`; o pedirle el mes a MySQL | Decisión de la integrante. Guatemala es UTC−6 fijo, igual que el SQL (`- INTERVAL 6 HOUR`). Se puede probar sin base pasando `ahora` |
| Regex `^\d{4}-(0[1-9]|1[0-2])$` y solo `undefined` usa el mes por defecto | Aceptar `?mes=` vacío como "mes actual"; o recortar espacios | Decisión de la integrante. Lo que llega y no cumple el formato es un error del cliente: 400 |
| Mes agrupado con `DATE_FORMAT(fecha_cierre - INTERVAL 6 HOUR, '%Y-%m') = ?` | Agrupar en UTC; o un rango `BETWEEN` en UTC | Es la expresión acordada con el panel (`docs/reparto.md`). Así panel y API dan lo mismo |
| Cuatro consultas separadas con `Promise.all` | Una sola consulta con subconsultas; o una con `COUNT(DISTINCT o.id)` y el `JOIN` | Cada cifra queda en un SQL corto que se explica solo en la defensa. Contar sin el `JOIN` evita contar un plan una vez por línea |
| Sin transacción | Leer las cuatro cifras en una transacción | Solo se lee. Un cambio entre una consulta y otra solo afecta esa respuesta; la siguiente sale bien |
| Pronóstico: `ROUND(SUM(...), 2)` con las probabilidades como decimales en un `CASE` | Redondear cada plan; usar los porcentajes enteros de `PROBABILIDAD` en JS | Decisión de la integrante y sección 11.4, errores 4 y 5. Redondear cada plan da otra cifra |
| Pronóstico sin filtro de mes | Filtrar por mes | Sección 11.4, error 4: Q2,512.88 en octubre y en noviembre |
| Total con `precio_unitario` de la línea, sin `JOIN productos` | Unir al catálogo | Sección 11.4, error 2 |
| `COALESCE(..., 0)` en SQL | Convertir `null` a `0` en JavaScript | Nada se calcula en JavaScript. `decimalNumbers` entrega el `0` como número |
| Carpeta de Postman sola, después de `VerificacionSeeder`, al final de la colección | Ponerla primera y correr toda la colección tras `--seed` | Decisión de la integrante. Las cifras de control son de la semilla de la defensa, y las otras carpetas cambian datos |
| Postman compara el cuerpo completo con `eql` | Comprobar campo por campo | Detecta un monto como texto o una llave de más en una sola prueba |

---

## 7. Riesgos

| Riesgo | Mitigación |
|---|---|
| La base no existe y las cifras no se pueden probar ahora | Las pruebas sin base cubren el mes y el formato. CA6 a CA15 se comprueban con la carpeta "Resumen" en cuanto existan las migraciones y la semilla del spec 01 |
| `COUNT(*)` o un `DECIMAL` llegan como texto | `decimalNumbers: true` (spec 02) y `COUNT(*)` dentro del rango de `Number`. La carpeta compara con `eql`, que falla con `"9250.00"` |
| `ROUND` con un valor que no es exacto | Todo es `DECIMAL` × literal decimal, aritmética exacta en MySQL: 2512.875 se redondea a 2512.88 |
| Se ejecuta la carpeta después de Clientes, Planes o Regla propia | Su descripción dice cuándo correrla. Si falla, se recarga la semilla de verificación |
| La semilla del sector agrega planes a Valeria, Diego o Carlos | Pregunta abierta de la sección 11. Esta carpeta usa solo `VerificacionSeeder`, así que no le afecta |

---

## Lo que **no** entra en este spec

- El reporte del panel por asesor, equipo, empresa y tratamiento (A, spec 14).
- La vista `#/resumen` y el contador de la cartera en la SPA (A, specs 22 y 18).
- Resumen de otro asesor, de un equipo o de varios meses.
- Cambios en el esquema.
- `docs/verificacion.md` (spec 23).
