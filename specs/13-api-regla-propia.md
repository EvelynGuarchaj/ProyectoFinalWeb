# 13 · API de la regla propia

**Estado:** Aprobado
**Depende de:** SPEC 01 (`01-esquema-y-semilla`: columna `productos.requiere_evaluacion` y tabla `actividades`), SPEC 08 (`08-api-actividades`: `POST /clientes/:id/actividades` y `PATCH /actividades/:id`, que usa la carpeta de Postman) y SPEC 11 (`11-api-oportunidades`: el candado, los dos comentarios que marcan dónde va la regla y la colección con la carpeta "Planes"). La carpeta de Postman necesita además los datos de la semilla del sector, que define el spec 17 (`17-semilla-sector`, de A). **Ese spec todavía no existe**: la sección 2.3 lista lo que esta carpeta espera de él.
**Fecha:** 2026-11-08
**Dueña:** B
**Rama:** `spec-13-api-regla-propia`
**Objetivo:** que la API rechace con 409 un plan con ortodoncia o implante que intenta llegar a "Presupuesto enviado" o más allá sin una cita de evaluación completada del paciente.

**Requisitos que cumple:**
- La sección 2 de `docs/especificacion.md`, en su parte de API: 2.3 (las dos filas de la API y el mensaje), 2.4 (qué cuenta como evaluación) y 2.7 (la carpeta "Regla propia" de Postman).
- Los criterios R-P1 a R-P8 de la sección 2.6. R-P9 (la casilla del catálogo en el panel) es de A y no entra.
- La fila 6 de la tabla de `PATCH /oportunidades/:id/etapa` en la sección 4.4.
- La fila "Regla propia" de la sección 3.10: se cumple en la misma transacción del cambio.

---

## 1. Alcance

**Incluye**
- `api/src/util/reglaPropia.js`, con `MENSAJE_EVALUACION`, `pacienteTieneEvaluacion(cx, clienteId)` y `verificarEvaluacion(cx, oportunidadId, clienteId)`.
- `api/pruebas/reglaPropia.test.js`, con `node:test` y una conexión falsa (sin base). El script `test` ya incluye todo `pruebas/*.test.js`.
- En `api/src/rutas/oportunidades.js`:
  - El candado devuelve también `cliente_id`.
  - La consulta del producto activo devuelve también `requiere_evaluacion`.
  - Los dos comentarios `// Regla propia (spec 13): …` del spec 11 se reemplazan por la revisión.
- La carpeta "Regla propia" de `docs/api.postman_collection.json`, con las variables nuevas que necesita.

**No incluye**
- La migración de `requiere_evaluacion`: ya existe (spec 01).
- La casilla "Requiere evaluación" del catálogo del panel ni R-P9 (A, spec 04).
- Los datos de la semilla del sector (A, spec 17). Este spec solo dice qué datos espera la carpeta de Postman.
- El aviso de la regla en la SPA (specs 19 y 21).
- `docs/verificacion.md` y `pruebas/simultaneas.js` (spec 23).
- Bloquear filas de `actividades` o de `productos` (ver decisiones).
- Paquetes nuevos.

---

## 2. Modelo de datos

Este spec no crea tablas ni columnas. La API nunca ejecuta `CREATE` ni `ALTER`. Usa:

| Tabla | Columnas | Para qué |
|---|---|---|
| `productos` | `id`, `requiere_evaluacion` | Saber si una línea o el producto que llega requiere evaluación |
| `oportunidad_productos` | `oportunidad_id`, `producto_id` | Las líneas del plan |
| `oportunidades` | `id`, `etapa`, `cliente_id` | La etapa actual y el paciente, leídos por el candado |
| `actividades` | `cliente_id`, `tipo`, `completada` | La evaluación del paciente, de **cualquier** asesor (sección 2.4) |

### 2.1 `util/reglaPropia.js`

```js
export const MENSAJE_EVALUACION =
  'El paciente necesita una cita de evaluación completada antes de recibir el presupuesto.';
export async function pacienteTieneEvaluacion(cx, clienteId) { /* true o false */ }
export async function verificarEvaluacion(cx, oportunidadId, clienteId) { /* nada, o lanza 409 */ }
```

- `cx` es la conexión de la transacción (`cx.execute`). Las dos funciones nunca usan `consulta()`.
- `pacienteTieneEvaluacion` devuelve `true` si existe al menos una actividad `reunion` completada del paciente, sin importar qué asesor la registró.
- `verificarEvaluacion`:
  1. Pregunta si el plan tiene al menos una línea cuyo producto requiere evaluación. Si no tiene, termina sin consultar `actividades`.
  2. Si tiene, llama a `pacienteTieneEvaluacion`. Si da `false`, lanza `conflicto(MENSAJE_EVALUACION)`.
- Cuentan también las líneas de productos inactivos: la regla mira el plan, no el catálogo.

SQL (textos fijos, parámetros `?`; los literales `'reunion'` y `1` son fijos):

```sql
-- ¿El plan tiene algún tratamiento que requiere evaluación?
SELECT 1 FROM oportunidad_productos l JOIN productos p ON p.id = l.producto_id
 WHERE l.oportunidad_id = ? AND p.requiere_evaluacion = 1 LIMIT 1

-- ¿El paciente tiene una cita completada? (de cualquier asesor)
SELECT 1 FROM actividades
 WHERE cliente_id = ? AND tipo = 'reunion' AND completada = 1 LIMIT 1
```

### 2.2 Cambios en `rutas/oportunidades.js`

```sql
-- Candado (antes: SELECT id, etapa …)
SELECT id, etapa, cliente_id FROM oportunidades WHERE id = ? AND vendedor_id = ? FOR UPDATE

-- Producto activo del POST de líneas (antes: SELECT id …)
SELECT id, requiere_evaluacion FROM productos WHERE id = ? AND activo = 1 LIMIT 1
```

No cambia nada más del spec 11: ni el orden de los demás pasos, ni los mensajes, ni las formas de respuesta.

### 2.3 Lo que la carpeta de Postman espera de la semilla del sector (spec 17)

| Dato | Detalle |
|---|---|
| Asesores | Andrea Gómez (`andrea@dental.test`) y Kevin Chávez (`kevin@dental.test`), activos, con la contraseña pública de las semillas |
| Tratamientos | "Ortodoncia con brackets" e "Implante dental" con `requiere_evaluacion = true`. "Limpieza dental" con `requiere_evaluacion = false`. Los tres activos |
| Sofía Ramírez | Paciente de Andrea. Plan "Ortodoncia completa" en `calificado` con una línea de "Ortodoncia con brackets". Una actividad `reunion` "Cita de evaluación de ortodoncia" **pendiente** y **ninguna** `reunion` completada |
| Otro paciente de Andrea | Al menos uno, distinto de Sofía, sin ninguna `reunion` completada. La carpeta lo busca con un script |
| Héctor Sapón | Paciente de Kevin. Plan "Implante dental" en `calificado` con una línea de "Implante dental", y al menos una `reunion` completada |

Si al escribir el spec 17 cambia algún nombre, se ajustan las peticiones de la carpeta, no el código.

---

## 3. Comportamiento

### 3.1 `PATCH /api/oportunidades/:id/etapa`

En la transacción del spec 11 cambia solo el paso 6. Queda así:

1. **404:** el candado no encuentra la fila.
2. **409:** la etapa actual está en `CERRADAS`.
3. **422:** transición no permitida.
4. **422:** `ganada` sin tratamientos.
5. **422:** `perdida` sin motivo válido.
6. **409 (regla propia):** si la etapa nueva es `propuesta`, `negociacion` o `ganada`, se llama `verificarEvaluacion(cx, id, plan.cliente_id)`.
7. `UPDATE` e `INSERT` del historial, igual que en el spec 11.

`perdida` nunca pasa por la regla (R-P7): un plan se puede rechazar siempre.

El 409 se lanza dentro de `transaccion()`, que hace rollback. Como el `UPDATE` y el historial vienen después, un rechazo no deja nada en la base: ni la etapa, ni `fecha_cierre`, ni el registro del historial (R-P1, R-P6).

### 3.2 `POST /api/oportunidades/:id/productos`

En la transacción del spec 11 cambia solo el paso 4. Queda así:

1. Candado: 404 o 409.
2. Validación: un solo 422. La consulta del producto activo guarda también su `requiere_evaluacion`.
3. Repetido: 409.
4. **409 (regla propia):** si se cumplen las tres condiciones, responde `conflicto(MENSAJE_EVALUACION)`:
   - el producto que llega tiene `requiere_evaluacion` verdadero;
   - la etapa del candado es `propuesta` o `negociacion`;
   - `pacienteTieneEvaluacion(cx, plan.cliente_id)` da `false`.
5. `INSERT … SELECT` y 201, igual que en el spec 11.

En `prospecto` y `calificado` se puede agregar cualquier tratamiento sin cita. La regla actúa después, al pedir `propuesta`. En `ganada` y `perdida` el plan ya responde 409 por cerrado, antes de llegar aquí.

`requiere_evaluacion` llega de MySQL como `0` o `1`; la ruta lo convierte con `Boolean(...)`.

### 3.3 Marcas en el código

Los dos comentarios `// Regla propia (spec 13): …` se reemplazan por una línea `// Regla propia (sección 2.3 de la especificación).` justo encima de cada revisión. Así `git grep` encuentra los dos lugares.

---

## 4. Plan de implementación

Cada paso deja la API arrancable. La base del sector todavía no existe. Los pasos 1 a 4 se prueban sin base hasta donde se pueda. Al final de cada paso se anota qué falta probar con la semilla.

1. **`util/reglaPropia.js` y `pruebas/reglaPropia.test.js`.** Primero las pruebas, con una conexión falsa: un objeto con `execute(sql, params)` que responde según el texto SQL y anota las llamadas. Casos:
   - Plan sin líneas de evaluación: `verificarEvaluacion` no lanza y **no** consulta `actividades`.
   - Plan con línea de evaluación y paciente sin cita completada: lanza un `ErrorHttp` 409 con `MENSAJE_EVALUACION` exacto.
   - Plan con línea de evaluación y paciente con cita completada: no lanza.
   - `pacienteTieneEvaluacion` da `true` con una fila y `false` sin filas, y pasa `clienteId` como parámetro.

   Prueba: `npm test` en verde, junto con NIT, teléfono, fechas y etapas.
2. **Candado con `cliente_id`.** Se cambia `SQL_CANDADO`. Prueba sin base: `npm test` y el arnés del spec 11 (404, 400 y 401) siguen igual.
3. **Regla en el cambio de etapa.** Sección 3.1. Prueba sin base: arnés (404, 400 y 401 sin cambios). `git grep -n "Regla propia (spec 13)"` da 1 resultado, el del POST.
4. **Regla al agregar un tratamiento.** Sección 3.2 y el cambio de `SQL_PRODUCTO_ACTIVO`. Prueba sin base: arnés. `git grep -n "Regla propia (spec 13)"` ya no da resultados y `git grep -n "Regla propia (sección 2.3"` da 2.
5. **Postman.** Variables nuevas: `correo_andrea` (valor `andrea@dental.test`), `correo_kevin` (valor `kevin@dental.test`), `token_andrea`, `token_kevin` y los ids que guarda la carpeta. Carpeta "Regla propia":
   - Su descripción dice que se ejecuta en orden, justo después de `php artisan migrate:fresh --seed`.
   - El mismo script de carpeta que las demás: ninguna respuesta trae `password`, `vendedor_id`, `user_id`, `supervisor_id` ni `usuario_id`.
   - Cada petición prueba su código. Los 409 de la regla prueban el cuerpo exacto `{ error: MENSAJE_EVALUACION }`.
   - Los ids se guardan con scripts, nunca fijos.

   **Preparación**
   1. Login de Andrea → `token_andrea`.
   2. `GET /productos`:
      - Guarda `idOrtodoncia`, `idImplante` e `idLimpieza`.
      - Comprueba que los dos primeros traen `requiere_evaluacion: true` y "Limpieza dental" `false`.
   3. `GET /oportunidades`:
      - Guarda `idPlanSofia`: título "Ortodoncia completa" y paciente "Sofía Ramírez".
      - Guarda `idSofia`, el id del paciente.
      - Comprueba que está en `calificado`.
   4. `GET /oportunidades/{{idPlanSofia}}`:
      - Comprueba que tiene la línea "Ortodoncia con brackets".
      - Guarda `historialSofia`, el número de registros.
   5. `GET /actividades`:
      - Guarda `idCitaSofia`: "Cita de evaluación de ortodoncia" de Sofía, `reunion` y pendiente.
      - Comprueba que Sofía no tiene ninguna `reunion` completada.
      - Guarda en `clientesConCita` (JSON) los ids de los pacientes con alguna `reunion` completada.
   6. `GET /clientes`: guarda `idPacienteSinCita`, el primer paciente que no es Sofía y no está en `clientesConCita`. Falla si no hay ninguno.

   **R-P1 · Sin cita no hay presupuesto**
   7. `PATCH /oportunidades/{{idPlanSofia}}/etapa` `{ "etapa": "propuesta" }` → 409 con el mensaje.
   8. `GET` del plan: sigue en `calificado` y el historial tiene `historialSofia` registros.

   **R-P3 · Una cita pendiente no cuenta** (con `idPacienteSinCita`)
   9. `POST /oportunidades` con el título "Ortodoncia sin cita (Postman)" → 201. Guarda `idPlanCitaPendiente`.
   10. `POST …/productos` con `idOrtodoncia` y cantidad 1 → 201: en `prospecto` la regla no actúa.
   11. `PATCH …/etapa` a `calificado` → 200.
   12. `POST /clientes/{{idPacienteSinCita}}/actividades` `{ "tipo": "reunion", "descripcion": "Cita de evaluación (Postman)" }` → 201, pendiente. Guarda `idCitaPendiente`.
   13. `POST /clientes/{{idPacienteSinCita}}/actividades` `{ "tipo": "llamada", "descripcion": "Llamada de seguimiento (Postman)" }` → 201. Guarda `idLlamada`. Después `PATCH /actividades/{{idLlamada}}` `{ "completada": true }` → 200.
   14. `PATCH …/etapa` a `propuesta` → 409 con el mensaje.
   15. `GET` del plan: sigue en `calificado`.

   **R-P7 · Rechazar siempre se puede**
   16. `PATCH /oportunidades/{{idPlanCitaPendiente}}/etapa` `{ "etapa": "perdida", "motivo_perdida": "Prefiere otra clínica" }` → 200 en `perdida`.

   **R-P5 · Sin tratamientos de evaluación no se afecta**
   17. `POST /oportunidades` "Limpieza (Postman)" con `idPacienteSinCita` → 201. Guarda `idPlanLimpieza`.
   18. `POST …/productos` con `idLimpieza` → 201.
   19. `PATCH …/etapa` a `calificado`, `propuesta`, `negociacion` y `ganada`: 4 peticiones, cada una → 200.

   **R-P4 · No se cuela por agregar después**
   20. `POST /oportunidades` "Limpieza e implante (Postman)" con `idPacienteSinCita` → 201. Guarda `idPlanImplanteTarde`.
   21. `POST …/productos` con `idLimpieza` → 201.
   22. `PATCH …/etapa` a `calificado`, `propuesta` y `negociacion`: cada una → 200. La última guarda `totalAntes`.
   23. `POST …/productos` con `idImplante` → 409 con el mensaje.
   24. `GET` del plan: 1 línea y `total` igual a `totalAntes`.

   **R-P6 · Ganar también se protege**
   25. `POST /oportunidades` "Implante (Postman)" con `idPacienteSinCita` → 201. Guarda `idPlanImplante`.
   26. `POST …/productos` con `idImplante` → 201: en `prospecto` la regla no actúa.
   27. `PATCH …/etapa` a `calificado` → 200.
   28. `PATCH /actividades/{{idCitaPendiente}}` `{ "completada": true }` → 200: ahora el paciente sí tiene evaluación.
   29. `PATCH …/etapa` a `propuesta` y a `negociacion`: cada una → 200.
   30. `PATCH /actividades/{{idCitaPendiente}}` `{ "completada": false }` → 200: el paciente vuelve a quedar sin evaluación.
   31. `PATCH …/etapa` a `ganada` → 409 con el mensaje.
   32. `GET` del plan: sigue en `negociacion` y `fecha_cierre` es `null`.

   **R-P2 · Con cita completada avanza**
   33. `PATCH /actividades/{{idCitaSofia}}` `{ "completada": true }` → 200.
   34. `PATCH /oportunidades/{{idPlanSofia}}/etapa` `{ "etapa": "propuesta" }` → 200:
       - El detalle trae `etapa: "propuesta"`.
       - El historial tiene `historialSofia + 1` registros y el último va de `calificado` a `propuesta`.

   **Cita de otro asesor** (Kevin)
   35. Login de Kevin → `token_kevin`.
   36. `GET /oportunidades`: guarda `idPlanHector`, el plan "Implante dental" de "Héctor Sapón", y comprueba que está en `calificado`.
   37. `PATCH /oportunidades/{{idPlanHector}}/etapa` `{ "etapa": "propuesta" }` → 200.

---

## 5. Criterios de aceptación

Base de los criterios con datos: `php artisan migrate:fresh --seed` (verificación + sector), `npm run dev` con un `api/.env` válido y el token de cada asesor.

**Sin base**
- **CA1.** `npm test` corre `pruebas/reglaPropia.test.js` con los casos del paso 1 en verde, junto con las pruebas anteriores, sin la API ni la base encendidas.
- **CA2.** `git grep -n "Regla propia (spec 13)" api/src` no da resultados. `git grep -n "Regla propia (sección 2.3" api/src/rutas/oportunidades.js` da exactamente 2: el cambio de etapa y el agregar un tratamiento.
- **CA3.** `git grep -nE '\$\{' api/src/util/reglaPropia.js` no da resultados. Las dos consultas de 2.1 son textos fijos con `?`.

**Regla propia (sección 2.6)**
- **CA4 · R-P1.** Dado "Ortodoncia completa" de Sofía en `calificado` y sin cita completada, cuando Andrea pide `propuesta`, entonces responde 409 `{ "error": "El paciente necesita una cita de evaluación completada antes de recibir el presupuesto." }`. El plan sigue en `calificado` y `SELECT COUNT(*) FROM historial_etapas WHERE oportunidad_id = <id>` no cambia.
- **CA5 · R-P2.** Dado el mismo plan, cuando se marca completada la "Cita de evaluación de ortodoncia" y se pide `propuesta`, entonces responde 200 con `etapa: "propuesta"` y un registro nuevo `calificado → propuesta`.
- **CA6 · R-P3.** Dado un plan en `calificado` con "Ortodoncia con brackets", y un paciente con solo una `reunion` pendiente y una llamada completada, cuando se pide `propuesta`, entonces responde 409 y el plan sigue en `calificado`.
- **CA7 · R-P4.** Dado un plan en `negociacion` con solo "Limpieza dental" y un paciente sin cita completada, cuando se agrega "Implante dental", entonces responde 409 con el mensaje. El plan conserva 1 línea y su total.
- **CA8 · R-P5.** Dado un plan con solo "Limpieza dental" y un paciente sin cita completada, cuando avanza `calificado → propuesta → negociacion → ganada`, entonces cada paso responde 200.
- **CA9 · R-P6.** Dado un plan en `negociacion` con "Implante dental" y un paciente cuya única `reunion` volvió a pendiente, cuando se pide `ganada`, entonces responde 409. El plan sigue en `negociacion` y `fecha_cierre` es `NULL` en la base.
- **CA10 · R-P7.** Dado un plan con "Ortodoncia con brackets" en `calificado` y un paciente sin cita completada, cuando se pide `perdida` con motivo, entonces responde 200 en `perdida`.
- **CA11 · Cita de otro asesor.** Dado "Implante dental" de Héctor Sapón (Kevin) en `calificado` y una `reunion` completada de Héctor, cuando Kevin pide `propuesta`, entonces responde 200. Si esa cita la registró otro asesor, también cuenta (sección 2.4); se comprueba en la base con `SELECT vendedor_id FROM actividades WHERE cliente_id = <Héctor> AND tipo = 'reunion' AND completada = 1`.
- **CA12 · R-P8.** Dado `php artisan migrate:fresh --seeder=VerificacionSeeder`, cuando se consulta `SELECT COUNT(*) FROM productos WHERE requiere_evaluacion = 1`, entonces el resultado es 0. Las carpetas "Sesión", "Clientes", "Actividades" y "Planes" siguen en verde.

**Postman**
- **CA13.** La carpeta "Regla propia" pasa todas sus peticiones en verde, ejecutada en orden justo después de `php artisan migrate:fresh --seed`. Ninguna petición usa un id fijo.

---

## 6. Decisiones tomadas y descartadas

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| Dos funciones: `pacienteTieneEvaluacion` y `verificarEvaluacion` | Una sola que se llama después del `INSERT`; o un parámetro opcional `productoNuevoId` | Decisión de la integrante. En el POST la línea nueva aún no existe; con dos funciones cada ruta revisa exactamente lo que dice la fila de la sección 2.3 y nada se escribe antes de revisar |
| La revisión usa la conexión de la transacción | Usar `consulta()` | Lee lo mismo que la transacción va a escribir, después del candado del plan (sección 3.10) |
| El candado devuelve `cliente_id` | Una consulta aparte del paciente | Ya se bloqueó esa fila; leer el paciente ahí no agrega consultas |
| En el cambio de etapa, la regla va después de los 422 y antes del `UPDATE` | Revisarla primero | Es el orden de la tabla de la sección 4.4 (fila 6). Un rechazo no deja historial |
| Si el plan no tiene líneas de evaluación, no se consulta `actividades` | Consultar siempre | Con la semilla de verificación la regla nunca se activa y no agrega consultas (R-P8) |
| Cuentan las líneas de productos inactivos | Contar solo las activas | La regla mira lo que el plan incluye, no lo que hoy se vende |
| Cuentan las citas de cualquier asesor | Solo las del asesor actual | Sección 2.4: una cita registrada antes de una reasignación sigue siendo evaluación |
| No se bloquean `actividades` ni `productos` | `FOR SHARE` sobre esas filas | El orden de candados acordado es `users → clientes → oportunidades`. Agregar tablas abre riesgo de bloqueo mutuo con el panel. Ver riesgos |
| Postman crea sus propios planes para R-P3 a R-P7 | Solo la carpeta pedida y el resto a mano; o depender de planes de la semilla del sector | Decisión de la integrante. Cumple la sección 2.7 (una petición por criterio) sin cambiarla y depende de muy pocos datos del spec 17 |
| R-P7 usa el plan de R-P3, no el de Sofía | Rechazar el plan de Sofía | El plan de Sofía se necesita después para R-P2. El de R-P3 está en la misma condición: ortodoncia, `calificado` y sin cita completada |
| R-P6 llega a `negociacion` marcando la cita completada y luego pendiente otra vez | Pedir a la semilla un plan en ese estado | Es la única forma por la API de tener un plan en `negociacion` con implante y sin evaluación. Usa solo rutas del contrato |
| `correo_andrea` y `correo_kevin` llevan su valor en la colección | Dejarlos vacíos como los de la semilla de verificación | Los correos del sector están en `docs/reparto.md` y no son secretos. La contraseña sigue en `{{clave}}` |
| Pruebas con conexión falsa en `node:test` | Probar solo con la base | La base no existe todavía. Las pruebas comprueban el SQL fijo, los parámetros y el mensaje exacto |

---

## 7. Riesgos

| Riesgo | Mitigación |
|---|---|
| El spec 17 (semilla del sector) no existe y los nombres de la sección 2.3 pueden cambiar | La tabla 2.3 es lo que hay que acordar con A. Si cambia un nombre, se ajusta la carpeta de Postman, no el código |
| Andrea no tiene ningún paciente sin cita completada además de Sofía | La petición 6 falla con un mensaje claro. Se pide a A en el spec 17 |
| Una cita se marca pendiente justo mientras otro asesor avanza el plan | La regla se revisa en el instante del cambio, como pide la sección 2.3. No se bloquea `actividades` para no romper el orden de candados. El plan ya avanzado no retrocede: es el mismo caso que R-P6 y la regla vuelve a actuar al pedir `ganada` |
| El administrador cambia `requiere_evaluacion` mientras un plan avanza | El mismo criterio: vale lo que dice el catálogo en el instante del cambio |
| La carpeta crea datos (planes, citas, una venta de Andrea) y depende del orden | Se corre una vez, en orden, justo después de `migrate:fresh --seed`, como dice su descripción. La venta solo cambia cifras del sector; las cifras de control usan solo la semilla de verificación |
| `requiere_evaluacion` llega como `0`/`1` | `Boolean(...)` en la ruta. Las pruebas del paso 1 usan `1` y `0` como MySQL |

---

## Lo que **no** entra en este spec

- La casilla "Requiere evaluación" del panel y R-P9 (A, spec 04).
- Los datos de la semilla del sector (A, spec 17).
- El aviso en la SPA (specs 19 y 21).
- `docs/verificacion.md` y `pruebas/simultaneas.js` (spec 23).
- Bloquear `actividades` o `productos`.
