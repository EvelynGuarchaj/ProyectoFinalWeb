# Especificación · CRM Clínica Dental Dientecitos Felices

> Versión: spec-v1 · Integrantes: A (nombre) y B (nombre)
> Cada sección indica su responsable. Solo se escribe debajo de los títulos propios; las observaciones a la sección del otro van en el Pull Request.

## 1. Sector, empresa, roles y vocabulario
<!-- Responsable: A -->

### 1.1 Empresa

**Dientecitos Felices** es una clínica dental ficticia de Quetzaltenango. Vende **tratamientos dentales** (limpiezas, resinas, extracciones, endodoncias, coronas, blanqueamientos, ortodoncia e implantes) a pacientes particulares. Cada paciente tiene un asesor que le da seguimiento desde la primera consulta hasta que acepta o rechaza su plan de tratamiento.

Hay dos puertas:

- **Panel web (Laravel):** lo usan el personal administrativo de la clínica (administrador) y los coordinadores de cada equipo de asesores.
- **SPA del asesor (a través de la API):** la usan los asesores para trabajar su cartera de pacientes, sus planes de tratamiento y sus citas.

### 1.2 Roles

| Rol (código) | En pantalla | Puerta | Qué puede hacer | Qué no puede hacer |
|---|---|---|---|---|
| `administrador` | Administrador | Panel | Crear y editar cuentas; administrar el catálogo de tratamientos (incluida la marca "requiere evaluación"); ver y administrar todos los pacientes; ver todos los planes de tratamiento y los reportes de toda la clínica | Entrar a la API. Borrar cuentas: se desactivan |
| `supervisor` | Coordinador | Panel | Ver a los asesores de su equipo, los pacientes de su equipo y los disponibles; crear pacientes y asignarlos, reasignarlos o liberarlos dentro de su equipo; ver los planes de tratamiento y los reportes de su equipo | Ver otros equipos. Administrar cuentas o catálogo. Entrar a la API |
| `vendedor` | Asesor | SPA, a través de la API | Trabajar su cartera de pacientes (máximo 10), tomar pacientes disponibles, llevar sus planes de tratamiento y sus tratamientos, registrar citas, llamadas y tareas, y ver su resumen del mes | Entrar al panel. Ver o tocar datos de otro asesor |

Cada asesor pertenece al equipo de un coordinador. Una cuenta desactivada no entra por ninguna de las dos puertas y, si tenía una sesión abierta, la pierde en su siguiente petición.

### 1.3 Vocabulario

| Núcleo (código) | Pantalla (clínica) |
|---|---|
| cliente | Paciente |
| producto | Tratamiento |
| oportunidad | Plan de tratamiento |
| vendedor | Asesor |
| supervisor | Coordinador |
| administrador | Administrador |
| actividad `llamada` / `reunion` / `tarea` | Llamada / **Cita** / Tarea |
| etapa `prospecto` | Interesado |
| etapa `calificado` | Evaluado |
| etapa `propuesta` | Presupuesto enviado |
| etapa `negociacion` | En negociación |
| etapa `ganada` | Aceptado |
| etapa `perdida` | Rechazado |

**Solo cambian los textos.** Las tablas, columnas, rutas de la API, nombres de campos del contrato y valores de los enumerados (`prospecto`, `reunion`, `vendedor`…) se quedan con los nombres del núcleo, porque las pruebas automáticas del catedrático se ejecutan contra ellos. El vocabulario dental aparece únicamente en las vistas Blade del panel, en las vistas de la SPA (`frontend/js/textos.js`) y en los mensajes que lee una persona.

#### Criterios de aceptación

- **Dado** un asesor con sesión en la SPA, **cuando** abre el embudo, **entonces** ve las columnas "Interesado", "Evaluado", "Presupuesto enviado" y "En negociación", y la respuesta de `GET /oportunidades` trae `etapa` con los valores `prospecto`, `calificado`, `propuesta` o `negociacion`.
- **Dado** el panel, **cuando** el administrador abre el menú, **entonces** ve "Pacientes", "Tratamientos" y "Planes de tratamiento", y las rutas siguen usando los nombres del núcleo.
- **Dado** un asesor, **cuando** registra una actividad de tipo `reunion`, **entonces** la SPA la muestra como "Cita" y en la base queda `actividades.tipo = 'reunion'`.
<!-- fin sección 1 -->

## 2. Regla propia del sector
<!-- Responsable: A -->

### 2.1 Enunciado de la regla

> **Un plan de tratamiento que incluye un tratamiento que requiere evaluación (ortodoncia, implantes) no puede pasar a "Presupuesto enviado" ni a etapas posteriores si el paciente no tiene al menos una cita (`reunion`) completada.**

### 2.2 Por qué tiene sentido en una clínica

La ortodoncia y los implantes no se pueden presupuestar a ciegas: antes hace falta una evaluación clínica y una radiografía (panorámica o periapical) para saber cuántos meses de brackets necesita el paciente, si tiene hueso suficiente para el implante o si antes requiere otro tratamiento. Enviar un presupuesto sin esa cita produce precios que luego cambian y pacientes molestos. Los tratamientos sencillos (limpieza, resina, extracción) sí se pueden presupuestar sin evaluación previa.

### 2.3 Dónde se cumple

| Pieza | Qué hace |
|---|---|
| Base de datos | Columna nueva `productos.requiere_evaluacion`, booleano, **por omisión falso**, creada con una **migración nueva** de Laravel (no se edita la de `productos`). Al tener valor por omisión no rompe el contrato ni la semilla de verificación. |
| Panel (administrador) | El formulario del catálogo de tratamientos tiene la casilla "Requiere evaluación". Solo el administrador la marca o desmarca. |
| API · `PATCH /oportunidades/:id/etapa` | Si la etapa pedida es `propuesta`, `negociacion` o `ganada`, y el plan tiene al menos una línea cuyo producto tiene `requiere_evaluacion = true`, y el paciente no tiene evaluación (2.4), responde **409** y no cambia nada. |
| API · `POST /oportunidades/:id/productos` | Si el producto que se agrega tiene `requiere_evaluacion = true`, el plan está en `propuesta` o `negociacion` y el paciente no tiene evaluación (2.4), responde **409** y no agrega la línea. |

Respuesta cuando no se cumple (mismo formato de error del contrato):

```json
{ "error": "El paciente necesita una cita de evaluación completada antes de recibir el presupuesto." }
```

No se cumple con un CHECK en la base porque depende de otras tablas (`oportunidad_productos`, `productos` y `actividades`); se cumple en el servidor, dentro de la misma transacción del cambio de etapa o de la línea nueva. Como el panel muestra los planes solo en lectura, el único lugar donde un plan cambia de etapa o recibe tratamientos es la API.

### 2.4 Qué cuenta como evaluación

Existe al menos una fila en `actividades` con:

- `cliente_id` igual al paciente del plan,
- `tipo = 'reunion'`,
- `completada = true`.

Cuenta la cita de **cualquier asesor** (por ejemplo, una que se registró antes de que el paciente fuera reasignado). Una cita pendiente, una llamada o una tarea completadas **no** cuentan.

### 2.5 Por qué no choca con el núcleo

Con solo la semilla de verificación cargada, todos los productos tienen `requiere_evaluacion = false` (valor por omisión), así que la condición nunca se cumple y el embudo se comporta exactamente como dice el enunciado: las transiciones mínimas, las cifras de control y los códigos de respuesta no cambian. La regla tampoco toca `perdida`: un plan se puede rechazar siempre, tenga o no evaluación. Y el 409 ya es un código permitido por el contrato en las dos rutas.

### 2.6 Criterios de aceptación

- **R-P1 · Sin cita no hay presupuesto.** **Dado** un plan en `calificado` que incluye "Ortodoncia con brackets" (`requiere_evaluacion = true`) y un paciente sin ninguna actividad `reunion` completada, **cuando** el asesor envía `PATCH /oportunidades/:id/etapa` con `{ "etapa": "propuesta" }`, **entonces** la API responde **409** con el mensaje de 2.3, el plan sigue en `calificado` y **no** se agrega ningún registro en `historial_etapas`.
- **R-P2 · Con cita completada avanza.** **Dado** el mismo plan y que el paciente tiene una actividad `reunion` con `completada = true`, **cuando** el asesor envía `{ "etapa": "propuesta" }`, **entonces** la API responde **200**, el detalle trae `etapa: "propuesta"` y hay un registro nuevo en `historial_etapas` de `calificado` a `propuesta`.
- **R-P3 · Una cita pendiente no cuenta.** **Dado** el plan de R-P1 y que el paciente solo tiene una actividad `reunion` con `completada = false` (y quizás llamadas o tareas completadas), **cuando** el asesor pide pasar a `propuesta`, **entonces** la API responde **409** y el plan no cambia.
- **R-P4 · No se cuela por agregar después.** **Dado** un plan en `negociacion` con solo "Limpieza dental" y un paciente sin cita completada, **cuando** el asesor envía `POST /oportunidades/:id/productos` con el `producto_id` de "Implante dental", **entonces** la API responde **409** con el mensaje de 2.3 y el plan conserva sus líneas y su total.
- **R-P5 · Los planes sin tratamientos de evaluación no se afectan.** **Dado** un plan con solo tratamientos con `requiere_evaluacion = false` y un paciente sin citas, **cuando** el asesor lo avanza de `calificado` a `propuesta`, luego a `negociacion` y luego a `ganada`, **entonces** cada petición responde **200**.
- **R-P6 · Ganar también se protege.** **Dado** un plan en `negociacion` que incluye "Implante dental" (agregado cuando estaba en una etapa anterior) y un paciente sin cita completada, **cuando** el asesor envía `{ "etapa": "ganada" }`, **entonces** la API responde **409** y la oportunidad no registra `fecha_cierre`.
- **R-P7 · Rechazar siempre se puede.** **Dado** el plan de R-P1, **cuando** el asesor envía `{ "etapa": "perdida", "motivo_perdida": "Prefiere otra clínica" }`, **entonces** la API responde **200** y el plan queda en `perdida`.
- **R-P8 · El núcleo queda intacto.** **Dado** `php artisan migrate:fresh --seeder=VerificacionSeeder`, **cuando** se consulta `SELECT COUNT(*) FROM productos WHERE requiere_evaluacion = 1`, **entonces** el resultado es 0 y las cifras de control de la sección 11 no cambian.
- **R-P9 · El administrador marca el tratamiento.** **Dado** el administrador en el catálogo del panel, **cuando** edita un tratamiento y marca "Requiere evaluación", **entonces** en la base queda `requiere_evaluacion = 1`; un coordinador no puede abrir ese formulario.

### 2.7 Cómo se prueba

- **Datos:** el `SectorSeeder` marca "Ortodoncia con brackets" e "Implante dental" con `requiere_evaluacion = true` e incluye pacientes con planes de ortodoncia o implante en `calificado` y `negociacion`: unos sin citas, otros con una cita pendiente y otros con una cita completada.
- **Peticiones:** la colección de Postman (`docs/api.postman_collection.json`, la hace B) tiene una carpeta "Regla propia" con una petición por criterio R-P1 a R-P7, usando el token de un asesor del sector, y comprueba el código de respuesta y el mensaje.
- **Panel:** R-P9 se comprueba a mano en el catálogo y se anota en `docs/verificacion.md`.
- **Núcleo:** R-P8 se comprueba recargando solo la semilla de verificación y repitiendo las cifras de control.
<!-- fin sección 2 -->

## 3. Modelo de datos y dónde se cumple cada regla
<!-- Responsable: A -->

### 3.1 Principios

- **Un solo dueño del esquema.** Todas las tablas se crean y cambian solo con migraciones de Laravel. La API nunca ejecuta `CREATE` ni `ALTER`. La migración original de `users` de Laravel no se toca: `rol`, `supervisor_id` y `activo` llegan en una migración propia, y `productos.requiere_evaluacion` en otra (capa de sector).
- **La base es la última defensa.** Dos aplicaciones escriben en las mismas tablas, así que lo que la base puede hacer cumplir (llaves foráneas, índices únicos y CHECK) vive en la base. El panel y la API validan **antes**, para dar un mensaje claro, pero si las dos validaciones fallan, la base rechaza la escritura.
- **Requisito de versión:** MySQL 8.0.16 o superior. Desde esa versión los CHECK se hacen cumplir; antes solo se leían y se ignoraban.
- **Fechas en UTC.** Todas las columnas de fecha guardan UTC. Laravel usa `APP_TIMEZONE=UTC` y `'timezone' => '+00:00'` en la conexión. La API usa mysql2 con `timezone: 'Z'` y `SET time_zone = '+00:00'` en cada conexión. Solo al mostrarlas se pasan a hora de Guatemala (UTC−6 fijo).
- **Dinero en DECIMAL(10,2)**, nunca FLOAT.
- Todas las tablas tienen `id` BIGINT UNSIGNED autoincremental y, salvo `historial_etapas`, `created_at` y `updated_at`.

**Abreviaturas de la columna "Dónde se cumple":**
- **BD:** la base de datos (FK, UNIQUE, CHECK, NOT NULL, DEFAULT).
- **Panel:** el FormRequest de Laravel (`CuentaRequest`, `ProductoRequest`, `ClienteRequest`).
- **API:** la validación en la ruta de Express (`src/rutas/*.js`).
- **—:** esa puerta no escribe la columna.

### 3.2 `users`

Tabla de Laravel (`name`, `email` único, `password` con bcrypt, `remember_token`, fechas) más tres columnas de una migración propia.

| Columna | Tipo MySQL | Reglas | Dónde se cumple |
|---|---|---|---|
| `rol` | `ENUM('administrador','supervisor','vendedor') NOT NULL DEFAULT 'vendedor'` | Obligatorio; por omisión `vendedor` | BD: ENUM y NOT NULL · Panel: `CuentaRequest` (`in:`) · API: — |
| `supervisor_id` | `BIGINT UNSIGNED NULL`, FK → `users.id` | Obligatorio para vendedores y vacío para los demás. Debe ser una cuenta con rol `supervisor` y activa | BD: FK sin acción + `chk_users_supervisor` · Panel: `CuentaRequest` (regla que consulta la tabla, ver 3.10) · API: — |
| `activo` | `TINYINT(1) NOT NULL DEFAULT 1` | Por omisión verdadero. Las cuentas no se borran: se desactivan | BD: DEFAULT · Panel: no hay ruta para borrar cuentas · API: el middleware vuelve a leer `activo` en cada petición |
| `email` | `VARCHAR(255) NOT NULL UNIQUE` | Único (viene de Laravel) | BD: UNIQUE · Panel: `CuentaRequest` (`unique:users`) · API: — |
| `password` | `VARCHAR(255) NOT NULL` | Hash bcrypt; solo Laravel lo crea | Panel: `Hash::make` · API: solo lo compara con `bcryptjs` y nunca lo devuelve |

```sql
CONSTRAINT chk_users_supervisor CHECK (
  (rol = 'vendedor' AND supervisor_id IS NOT NULL)
  OR (rol <> 'vendedor' AND supervisor_id IS NULL)
)
```

**Por qué `users.supervisor_id` no tiene `ON DELETE`:** MySQL prohíbe las acciones referenciales (`ON DELETE` / `ON UPDATE` con `CASCADE` o `SET NULL`) en una columna que usa un CHECK, y `supervisor_id` aparece en `chk_users_supervisor`. La FK se declara sin acción, que equivale a `RESTRICT`. Además no hace falta, porque las cuentas no se borran.

### 3.3 `productos`

| Columna | Tipo MySQL | Reglas | Dónde se cumple |
|---|---|---|---|
| `nombre` | `VARCHAR(120) NOT NULL UNIQUE` | Obligatorio y único | BD: UNIQUE · Panel: `ProductoRequest` (`required`, `max:120`, `unique`) · API: — |
| `precio` | `DECIMAL(10,2) NOT NULL` | Mayor que cero, en quetzales | BD: `chk_productos_precio` · Panel: `ProductoRequest` (`numeric`, `gt:0`, `decimal:0,2`) · API: — |
| `activo` | `TINYINT(1) NOT NULL DEFAULT 1` | Solo los activos se agregan a una oportunidad. No se borran: se desactivan | BD: DEFAULT · Panel: no hay ruta para borrar · API: `GET /productos` y `POST /oportunidades/:id/productos` filtran `activo = 1` |
| `requiere_evaluacion` | `TINYINT(1) NOT NULL DEFAULT 0` (`BOOLEAN NOT NULL DEFAULT FALSE`) | **Capa de sector** (sección 2). Por omisión falso: no rompe el contrato ni la semilla de verificación | BD: DEFAULT · Panel: casilla en `ProductoRequest` (`boolean`), solo el administrador · API: la lee al cambiar de etapa y al agregar líneas |

```sql
CONSTRAINT chk_productos_precio CHECK (precio > 0)
```

### 3.4 `clientes`

| Columna | Tipo MySQL | Reglas | Dónde se cumple |
|---|---|---|---|
| `vendedor_id` | `BIGINT UNSIGNED NULL`, FK → `users.id` | Vacío significa disponible. Debe ser un vendedor. Máximo 10 por vendedor | BD: FK sin acción · Panel: `ClienteRequest` + candado (3.10) · API: sale del token, nunca del cuerpo, + candado (3.10) |
| `nombre` | `VARCHAR(150) NOT NULL` | Obligatorio | BD: NOT NULL · Panel: `ClienteRequest` (`required`, `max:150`) · API: validación (obligatorio, ≤ 150) |
| `nit` | `VARCHAR(12) NULL UNIQUE` | NIT válido guardado compacto (`4521817K`), o NULL para CF. Único | BD: UNIQUE + `chk_clientes_nit` · Panel: `app/Support/Nit.php` (normaliza y verifica el módulo 11) · API: `src/util/nit.js` (mismo algoritmo, casos de la sección 10) |
| `telefono` | `CHAR(8) NOT NULL UNIQUE` | 8 dígitos que empiezan con 2 a 9. Único | BD: UNIQUE + `chk_clientes_telefono` · Panel: `app/Support/Telefono.php` · API: `src/util/telefono.js` |
| `correo` | `VARCHAR(150) NULL` | Formato de correo válido | Panel: `ClienteRequest` (`nullable`, `email`, `max:150`) · API: validación de formato |

```sql
CONSTRAINT chk_clientes_telefono CHECK (telefono REGEXP '^[2-9][0-9]{7}$'),
CONSTRAINT chk_clientes_nit      CHECK (nit IS NULL OR nit REGEXP '^[1-9][0-9]*[0-9K]$')
```

Los CHECK solo revisan la **forma** del NIT (sin ceros a la izquierda y con un verificador de 0 a 9 o K). El dígito verificador del módulo 11 se calcula en el panel y en la API. Un repetido lo detecta primero la validación; si dos peticiones llegan a la vez, el índice único decide: error 1062 → **409** en la API y error junto al campo en el panel.

### 3.5 `oportunidades`

| Columna | Tipo MySQL | Reglas | Dónde se cumple |
|---|---|---|---|
| `cliente_id` | `BIGINT UNSIGNED NOT NULL`, FK → `clientes.id` | Obligatorio; el cliente debe estar en la cartera de quien crea | BD: FK sin acción · Panel: — (solo lectura) · API: consulta con `vendedor_id` del token (lo ajeno → 404) |
| `vendedor_id` | `BIGINT UNSIGNED NOT NULL`, FK → `users.id` | El dueño de la oportunidad | BD: FK sin acción · API: sale del token |
| `titulo` | `VARCHAR(150) NOT NULL` | Obligatorio | BD: NOT NULL · API: validación (obligatorio, ≤ 150) |
| `etapa` | `ENUM('prospecto','calificado','propuesta','negociacion','ganada','perdida') NOT NULL DEFAULT 'prospecto'` | Nace en `prospecto`. Transiciones según la sección 5 | BD: ENUM y DEFAULT · API: transiciones (422), cerrada no cambia (409), regla propia (409) |
| `motivo_perdida` | `VARCHAR(255) NULL` | Presente si y solo si la etapa es `perdida`; de 1 a 255 caracteres sin contar espacios sobrantes | BD: `chk_oportunidades_motivo` + `chk_oportunidades_motivo_vacio` · API: validación (recorta y exige 1–255 → 422) |
| `fecha_cierre` | `DATETIME NULL` (UTC) | Presente si y solo si la etapa es `ganada` o `perdida` | BD: `chk_oportunidades_cierre` · API: la pone el servidor al cerrar, nunca el cuerpo |

```sql
CONSTRAINT chk_oportunidades_motivo
  CHECK ((etapa = 'perdida') = (motivo_perdida IS NOT NULL)),
CONSTRAINT chk_oportunidades_motivo_vacio
  CHECK (motivo_perdida IS NULL OR CHAR_LENGTH(TRIM(motivo_perdida)) BETWEEN 1 AND 255),
CONSTRAINT chk_oportunidades_cierre
  CHECK ((etapa IN ('ganada','perdida')) = (fecha_cierre IS NOT NULL))
```

### 3.6 `oportunidad_productos`

| Columna | Tipo MySQL | Reglas | Dónde se cumple |
|---|---|---|---|
| `oportunidad_id` | `BIGINT UNSIGNED NOT NULL`, FK → `oportunidades.id` **ON DELETE CASCADE** | Al borrar la oportunidad se borran sus líneas | BD: FK en cascada |
| `producto_id` | `BIGINT UNSIGNED NOT NULL`, FK → `productos.id` | Un producto aparece una sola vez por oportunidad. Solo productos activos | BD: FK sin acción + UNIQUE `(oportunidad_id, producto_id)` · API: consulta previa (409) y 1062 → 409; `activo = 1` (422) |
| `cantidad` | `SMALLINT UNSIGNED NOT NULL` | Entero de 1 a 999 | BD: `chk_lineas_cantidad` · API: validación (entero 1–999 → 422) |
| `precio_unitario` | `DECIMAL(10,2) NOT NULL` | Se copia del catálogo al agregar; mayor que cero. Nunca viene del cuerpo | BD: `chk_lineas_precio` · API: `INSERT … SELECT precio FROM productos` (ignora cualquier precio enviado) |

```sql
CONSTRAINT chk_lineas_cantidad CHECK (cantidad BETWEEN 1 AND 999),
CONSTRAINT chk_lineas_precio   CHECK (precio_unitario > 0)
```

El total de una oportunidad **no se guarda**: se calcula con `SUM(cantidad * precio_unitario)` en SQL cada vez que se consulta.

### 3.7 `historial_etapas`

| Columna | Tipo MySQL | Reglas | Dónde se cumple |
|---|---|---|---|
| `oportunidad_id` | `BIGINT UNSIGNED NOT NULL`, FK → `oportunidades.id` **ON DELETE CASCADE** | Al borrar la oportunidad se borra su historial | BD: FK en cascada |
| `usuario_id` | `BIGINT UNSIGNED NOT NULL`, FK → `users.id` | Quién hizo el cambio | BD: FK sin acción · API: sale del token |
| `etapa_anterior` | `ENUM(…seis etapas…) NULL` | NULL en el registro de creación | BD: ENUM · API: la escribe el servidor |
| `etapa_nueva` | `ENUM(…seis etapas…) NOT NULL` | Obligatoria. Cada etapa una sola vez por oportunidad | BD: NOT NULL + UNIQUE `(oportunidad_id, etapa_nueva)` · API: inserta en la misma transacción que el cambio |
| `created_at` | `DATETIME NOT NULL` (UTC) | Cuándo ocurrió. Sin `updated_at` | BD: NOT NULL · API: lo pone el servidor |

**Índice único `(oportunidad_id, etapa_nueva)`:** como no se permiten retrocesos (sección 5), cada etapa aparece como máximo una vez por oportunidad. La API ya bloquea la oportunidad con `SELECT … FOR UPDATE` antes de cambiarla. Si aun así dos cambios simultáneos llegaran a escribir la misma etapa, el segundo `INSERT` en el historial choca con el índice (1062), la transacción se revierte entera y la API responde **409**. Así no pueden quedar dos registros ni un cambio de etapa sin historial.

### 3.8 `actividades`

| Columna | Tipo MySQL | Reglas | Dónde se cumple |
|---|---|---|---|
| `cliente_id` | `BIGINT UNSIGNED NOT NULL`, FK → `clientes.id` **ON DELETE CASCADE** | Al borrar el cliente se borran sus actividades. El cliente debe estar en la cartera del vendedor | BD: FK en cascada · API: consulta con `vendedor_id` del token (lo ajeno → 404) |
| `vendedor_id` | `BIGINT UNSIGNED NOT NULL`, FK → `users.id` | Quién la registró | BD: FK sin acción · API: sale del token |
| `tipo` | `ENUM('llamada','reunion','tarea') NOT NULL` | Obligatorio | BD: ENUM · API: validación (422) |
| `descripcion` | `VARCHAR(500) NOT NULL` | Obligatoria | BD: NOT NULL · API: validación (1–500 → 422) |
| `vence_en` | `DATETIME NULL` (UTC) | Opcional. Si llega sin zona horaria → 422 | API: `src/util/fechas.js` |
| `completada` | `TINYINT(1) NOT NULL DEFAULT 0` | Por omisión falso | BD: DEFAULT · API: validación booleana en `PATCH /actividades/:id` |

### 3.9 Resumen de llaves foráneas

| FK | Al borrar el padre |
|---|---|
| `oportunidad_productos.oportunidad_id` → `oportunidades` | **CASCADE** |
| `historial_etapas.oportunidad_id` → `oportunidades` | **CASCADE** |
| `actividades.cliente_id` → `clientes` | **CASCADE** |
| `users.supervisor_id` → `users` | Sin acción (RESTRICT). No puede tener acción porque `supervisor_id` está en un CHECK |
| `clientes.vendedor_id`, `oportunidades.cliente_id`, `oportunidades.vendedor_id`, `oportunidad_productos.producto_id`, `historial_etapas.usuario_id`, `actividades.vendedor_id` | Sin acción (RESTRICT) |

Consecuencia: un paciente que tiene al menos un plan de tratamiento (abierto o cerrado) **no se puede eliminar**, porque `oportunidades.cliente_id` lo restringe. El panel revisa esto antes de borrar y muestra un mensaje en lugar del error de la base.

### 3.10 Reglas que no pueden vivir en la base

Un CHECK de MySQL solo ve la fila que se escribe; no puede consultar otras filas ni otras tablas. Estas reglas dependen de otras filas, así que se cumplen en el servidor:

| Regla | Por qué no cabe en la base | Panel | API |
|---|---|---|---|
| `supervisor_id` debe ser una cuenta con rol `supervisor` y activa | Hay que leer otra fila de `users` | `CuentaRequest`: `Rule::exists('users','id')->where('rol','supervisor')->where('activo',1)`, y el selector solo ofrece supervisores activos | No escribe cuentas |
| Un vendedor tiene como máximo 10 clientes | Hay que contar filas de `clientes` | Al crear o asignar: transacción → `SELECT id FROM users WHERE id = ? FOR UPDATE` del vendedor → contar sus clientes → si son 10, error junto al campo → asignar | Al crear o tomar: la misma secuencia; si ya tiene 10 → **409** |
| `clientes.vendedor_id` debe ser un vendedor activo | Hay que leer otra fila de `users` | `ClienteRequest`: `exists` con `rol = 'vendedor'`, `activo = 1` y, para el coordinador, de su equipo | Sale del token, ya validado por el middleware |
| Solo se agregan productos activos; el precio se copia del catálogo | Hay que leer `productos` | — | Consulta `activo = 1` y copia `precio` en el mismo `INSERT … SELECT` |
| Una oportunidad cerrada no cambia; transiciones permitidas; ganar exige un producto | Dependen del estado anterior y de otras tablas | — | Transacción con `SELECT … FOR UPDATE` de la oportunidad → 409 / 422 |
| Regla propia (sección 2) | Depende de `oportunidad_productos`, `productos` y `actividades` | — | En la misma transacción del cambio → 409 |

**El candado de cartera** hace que la regla de 10 aguante peticiones simultáneas: las dos puertas bloquean la **misma fila** (`users` del vendedor). La segunda operación espera a que termine la primera y cuenta ya con el cliente nuevo. **Orden de los candados**, igual en las dos puertas: primero `users`, después `clientes` y después `oportunidades`. Así no se producen bloqueos mutuos.

#### Criterios de aceptación

- **Dado** la base recién migrada, **cuando** se ejecuta `INSERT INTO productos (nombre, precio) VALUES ('Prueba', 0)`, **entonces** MySQL rechaza la fila por `chk_productos_precio` (error 3819).
- **Dado** un vendedor, **cuando** se intenta `UPDATE users SET supervisor_id = NULL WHERE id = <vendedor>`, **entonces** MySQL lo rechaza por `chk_users_supervisor`.
- **Dado** un cliente, **cuando** se intenta guardar `telefono = '12345678'` o `nit = '04521817K'` directamente en SQL, **entonces** MySQL lo rechaza por `chk_clientes_telefono` o `chk_clientes_nit`.
- **Dado** una oportunidad en `negociacion`, **cuando** se intenta `UPDATE oportunidades SET etapa = 'ganada'` sin `fecha_cierre`, **entonces** MySQL lo rechaza por `chk_oportunidades_cierre`; y con `etapa = 'perdida'` sin motivo, o con `motivo_perdida = '   '`, lo rechazan `chk_oportunidades_motivo` y `chk_oportunidades_motivo_vacio`.
- **Dado** una línea, **cuando** se intenta guardar `cantidad = 0` o `cantidad = 1000`, **entonces** MySQL la rechaza por `chk_lineas_cantidad`.
- **Dado** una oportunidad con historial `prospecto → calificado`, **cuando** se intenta insertar otro registro con `etapa_nueva = 'calificado'` para ella, **entonces** MySQL responde 1062.
- **Dado** una oportunidad con líneas e historial, **cuando** se borra en SQL, **entonces** desaparecen sus filas de `oportunidad_productos` y `historial_etapas`; y al borrar un cliente sin oportunidades desaparecen sus actividades.
- **Dado** `php artisan migrate:fresh --seeder=VerificacionSeeder`, **cuando** se consulta `SHOW CREATE TABLE productos`, **entonces** aparece `requiere_evaluacion tinyint(1) NOT NULL DEFAULT '0'` y ningún producto la tiene en 1.
- **Dado** un vendedor con 9 clientes, **cuando** el panel le asigna uno y la API le crea otro al mismo tiempo, **entonces** exactamente uno lo consigue y el vendedor queda con 10 (la API responde 409 o el panel muestra el error junto al campo).
<!-- fin sección 3 -->

## 4. Contrato de la API
<!-- Responsable: B -->
_Pendiente._
<!-- fin sección 4 -->

## 5. Decisiones del enunciado (sección 7)
<!-- Responsable: B -->
_Pendiente._
<!-- fin sección 5 -->

## 6. Requisitos del panel (L1–L7)
<!-- Responsable: A -->
_Pendiente._
<!-- fin sección 6 -->

## 7. Requisitos de la API (A1–A8)
<!-- Responsable: B -->
_Pendiente._
<!-- fin sección 7 -->

## 8. Requisitos de la SPA (F1–F6)

### F1 · Sesión
<!-- Responsable: A -->
Vista: `#/login` (`js/vistas/login.js`). Usa `POST /auth/login` con `{ correo, clave }`.

- **Inicio de sesión.** Formulario con correo y contraseña. Con 200, la SPA guarda `{ token, usuario }` y entra a `#/cartera`. El encabezado muestra `usuario.nombre` y el botón "Cerrar sesión".
- **Qué se guarda.** La sesión se guarda en `localStorage` bajo una sola clave, `"sesion"`, con el valor `JSON.stringify({ token, usuario })`. Es lo único que la SPA escribe en `localStorage`: ni listas, ni filtros, ni datos de pacientes.
- **Errores al entrar.** El 401 (credenciales incorrectas) y el 403 (no es asesor o la cuenta está desactivada) muestran el texto de `error` que devuelve la API, sin escribir nada en `localStorage`. Un 422 muestra cada mensaje junto a su campo.
- **Cerrar sesión.** Borra la clave `"sesion"` y vuelve a `#/login`.
- **401 en plena sesión.** Si cualquier petición con token responde 401, `api.js` borra la clave `"sesion"`, va a `#/login` y muestra el aviso **"Su sesión terminó. Ingrese de nuevo."**. Esto no aplica al 401 de la propia petición de inicio de sesión, que se trata como credenciales incorrectas.
- **Protección de rutas.** Sin sesión, cualquier ruta lleva a `#/login`. Con sesión, `#/login` lleva a `#/cartera`.

#### Criterios de aceptación

- **Dado** la cuenta de Valeria (asesora activa), **cuando** ingresa su correo y la contraseña correcta, **entonces** la SPA muestra `#/cartera` con su nombre en el encabezado, y en `localStorage` existe solo la clave `sesion` con `token` y `usuario`.
- **Dado** una cuenta de coordinador o de administrador, **cuando** ingresa con la contraseña correcta, **entonces** la SPA muestra el mensaje del 403 de la API, sigue en `#/login` y `localStorage` queda vacío.
- **Dado** una contraseña incorrecta, **cuando** se envía el formulario, **entonces** se muestra el mensaje del 401 de la API y no aparece el aviso de sesión terminada.
- **Dado** un asesor con sesión, **cuando** pulsa "Cerrar sesión", **entonces** la clave `sesion` desaparece de `localStorage` y la SPA muestra `#/login`.
- **Dado** un asesor con sesión al que el administrador desactiva desde el panel, **cuando** hace cualquier acción en la SPA, **entonces** la API responde 401, la SPA borra la sesión, vuelve a `#/login` y muestra "Su sesión terminó. Ingrese de nuevo.".
- **Dado** un navegador sin sesión, **cuando** se abre `#/actividades`, **entonces** la SPA muestra `#/login`.

### F2 · Cartera
<!-- Responsable: A -->
Vista: `#/cartera` (`js/vistas/cartera.js`). Usa `GET /clientes?q=`, `GET /reportes/resumen`, `POST /clientes`, `GET /clientes/disponibles` y `POST /clientes/:id/tomar`.

- **Mis pacientes.** Lista con nombre, NIT (o "CF") y teléfono. Encima, un buscador: al enviarlo, la SPA pide `GET /clientes?q=<texto>`. **La búsqueda la hace el servidor** (por nombre o NIT); la SPA no filtra la lista en el navegador. Si no hay resultados, muestra "No se encontraron pacientes.".
- **Contador.** Muestra "**x de 10** pacientes" con `cartera.clientes` y `cartera.maximo` de `GET /reportes/resumen`. Se vuelve a pedir después de crear o tomar un paciente.
- **Alta de paciente.** Formulario con nombre, NIT, teléfono y correo (opcional), enviado a `POST /clientes`.
  - Con 201, el paciente aparece en la lista, sube el contador y el formulario se limpia.
  - Con **422**, cada mensaje de `errores` aparece **junto a su campo** (`is-invalid` y `invalid-feedback` de Bootstrap) y **lo escrito se conserva** en todos los campos.
  - Con **409** (NIT o teléfono repetido, cartera llena), el mensaje de `error` aparece encima del formulario y también se conserva lo escrito.
- **Pacientes disponibles.** Lista de `GET /clientes/disponibles`, solo con el nombre, y un botón **"Tomar"** en cada fila.
  - Con 200, el paciente pasa a "Mis pacientes", sale de disponibles y sube el contador.
  - Con **409** (otro asesor lo tomó antes o la cartera está llena), la SPA muestra el mensaje de `error` de la API y vuelve a cargar la lista de disponibles.

#### Criterios de aceptación

- **Dado** Valeria con la semilla de verificación, **cuando** abre `#/cartera`, **entonces** ve sus 3 pacientes y el contador "3 de 10".
- **Dado** la cartera de Valeria, **cuando** escribe parte de un nombre o de un NIT y busca, **entonces** el navegador envía `GET /clientes?q=…` (visible en la pestaña Red) y la lista muestra solo lo que devolvió el servidor.
- **Dado** el formulario de alta, **cuando** se envía con NIT `4521817-1` y teléfono `5555123`, **entonces** aparece un mensaje en español junto al NIT y otro junto al teléfono, y el nombre, el NIT y el teléfono escritos siguen en sus campos.
- **Dado** el formulario de alta, **cuando** se envía con el NIT de un paciente que ya existe, **entonces** se muestra el mensaje del 409 y lo escrito se conserva.
- **Dado** un paciente disponible, **cuando** el asesor pulsa "Tomar", **entonces** el paciente aparece en "Mis pacientes" y el contador sube en 1.
- **Dado** dos asesores con la misma lista de disponibles, **cuando** el primero toma un paciente y después el segundo pulsa "Tomar" en ese mismo paciente, **entonces** el segundo ve el mensaje del 409 y el paciente desaparece de su lista de disponibles.
- **Dado** un paciente llamado `<img src=x onerror=alert(1)>`, **cuando** aparece en la lista, **entonces** se ve el texto literal y no se ejecuta nada.

### F3 · Embudo
<!-- Responsable: B -->
_Pendiente._

### F4 · Actividades
<!-- Responsable: A -->
Vista: `#/actividades` (`js/vistas/actividades.js`). Usa `GET /actividades?estado=`, `POST /clientes/:id/actividades`, `PATCH /actividades/:id` y `GET /clientes` (para elegir el paciente).

- **Pestañas.** "Pendientes" pide `GET /actividades?estado=pendiente` (es la pestaña inicial) y "Completadas" pide `GET /actividades?estado=completada`. Cada fila muestra el paciente, el tipo en pantalla (Llamada, Cita o Tarea), la descripción y la fecha de vencimiento en hora de Guatemala (o "Sin fecha").
- **Vencidas.** Una actividad con `vencida: true` se resalta en rojo (`border-danger` / `text-danger`) y lleva la etiqueta **"Vencida"**. La SPA usa el campo `vencida` que calcula la API; no lo calcula con el reloj del navegador.
- **Nueva actividad.** Formulario con:
  - paciente (selector con los pacientes de su cartera),
  - tipo: Llamada, Cita o Tarea (se envían `llamada`, `reunion` y `tarea`),
  - descripción,
  - fecha y hora (`<input type="datetime-local">`, opcional).

  Se envía a `POST /clientes/:id/actividades`. La fecha escrita se interpreta como hora de Guatemala y se envía en ISO 8601 con zona: `2026-12-01T10:00` → `"2026-12-01T10:00:00-06:00"` (función `aISO` de `util.js`). El resultado no depende de la zona horaria de la computadora. Con 422, los errores aparecen junto a su campo y lo escrito se conserva.
- **Marcar como completada.** Cada pendiente tiene un botón **"Marcar como completada"** que envía `PATCH /actividades/:id` con `{ "completada": true }`. Con 200, la actividad sale de "Pendientes" y aparece en "Completadas".

#### Criterios de aceptación

- **Dado** Valeria con la semilla de verificación, **cuando** abre `#/actividades`, **entonces** ve la pestaña "Pendientes" con sus actividades no completadas, y las que tienen `vencida: true` aparecen en rojo con la etiqueta "Vencida".
- **Dado** la pestaña "Completadas", **cuando** se abre, **entonces** el navegador pide `GET /actividades?estado=completada` y ninguna fila lleva la etiqueta "Vencida".
- **Dado** el formulario, **cuando** el asesor elige un paciente, el tipo "Cita", una descripción y la fecha 1 de diciembre de 2026 a las 10:00, **entonces** el cuerpo enviado tiene `"tipo": "reunion"` y `"vence_en": "2026-12-01T10:00:00-06:00"`, la API responde 201 y la lista muestra "01/12/2026 10:00".
- **Dado** la computadora configurada en otra zona horaria (por ejemplo, Europe/Madrid), **cuando** se crea la misma actividad, **entonces** se envía el mismo `vence_en` con `-06:00` y se muestra la misma hora.
- **Dado** el formulario sin descripción, **cuando** se envía, **entonces** aparece el mensaje del 422 junto al campo y el paciente, el tipo y la fecha elegidos se conservan.
- **Dado** una actividad pendiente, **cuando** el asesor pulsa "Marcar como completada", **entonces** la API recibe `{ "completada": true }`, la actividad desaparece de "Pendientes" y aparece en "Completadas".

### F5 · Resumen del mes
<!-- Responsable: A -->
Vista: `#/resumen` (`js/vistas/resumen.js`). Usa `GET /reportes/resumen?mes=AAAA-MM`.

- **Selector de mes.** Un `<input type="month">`. Al abrir la vista, la SPA pide `GET /reportes/resumen` sin `?mes=` (la API usa el mes actual de Guatemala) y pone en el selector el `mes` que devuelve. Al cambiar el mes, pide `?mes=AAAA-MM`.
- **Tarjetas.** Cuatro tarjetas:
  - **Planes aceptados**: `ventas`, el número de ventas del mes.
  - **Total vendido**: `total`.
  - **Pronóstico**: `pronostico`, con la nota "de sus planes abiertos, sin importar el mes".
  - **Cartera**: "`cartera.clientes` de `cartera.maximo`".
- **Montos en quetzales.** Todo monto se muestra con la función `quetzales` de `util.js`: `Q` + separador de miles con coma + dos decimales (`9250` → **`Q9,250.00`**, `2512.88` → `Q2,512.88`, `0` → `Q0.00`). La SPA no hace cálculos con los montos: solo les da formato.
- **Fechas.** Cualquier fecha que muestre la SPA (aquí y en las otras vistas) se pasa a hora de Guatemala con `fechaGT` de `util.js`, restando 6 horas fijas a la hora UTC, sin depender de la zona horaria de la computadora.

#### Criterios de aceptación

- **Dado** Valeria con la semilla de verificación, **cuando** elige octubre de 2026, **entonces** ve: Planes aceptados **2**, Total vendido **Q9,250.00**, Pronóstico **Q2,512.88** y Cartera **3 de 10**.
- **Dado** la vista recién abierta, **cuando** carga, **entonces** el navegador pide `GET /reportes/resumen` sin `mes` y el selector muestra el mes que devolvió la API.
- **Dado** un mes sin ventas, **cuando** se elige, **entonces** se ve Planes aceptados 0 y Total vendido Q0.00, mientras el pronóstico y la cartera no cambian.
- **Dado** la computadora en otra zona horaria, **cuando** se abre la vista, **entonces** se ven exactamente las mismas cifras y fechas.

### F6 · Calidad de la interfaz
<!-- Responsable: A -->
Base de la SPA: `index.html`, `css/estilos.css`, `js/app.js`, `js/api.js`, `js/estado.js`, `js/util.js` y `js/textos.js`.

**Rutas de la SPA (hash):**

| Ruta | Vista | Requisito |
|---|---|---|
| `#/login` | Inicio de sesión | F1 |
| `#/cartera` | Mis pacientes y disponibles | F2 |
| `#/embudo` | Planes de tratamiento (tablero) | F3 (B) |
| `#/plan/:id` | Detalle del plan de tratamiento | F3 (B) |
| `#/actividades` | Citas, llamadas y tareas | F4 |
| `#/resumen` | Resumen del mes | F5 |

Una ruta que no existe lleva a `#/cartera`. Las vistas de F3 las registra A en `app.js` desde el inicio; B solo escribe su contenido.

- **Estado → render.** Hay un único objeto `estado` (`estado.js`). Las vistas no tocan el DOM directamente: cambian el estado con `cambiar(...)`, y eso vuelve a llamar a `render(estado)` de la vista activa, que devuelve el HTML completo de la vista. Una petición pendiente o un error también son parte del estado.
- **Delegación de eventos.** Hay un solo escuchador de `click` y uno de `submit` sobre el contenedor de la aplicación. Los botones llevan `data-accion="nombre"` (más `data-id` si hace falta) y los formularios llevan `data-form="nombre"`. `app.js` busca esa función en `acciones` o en `formularios` de la vista activa. No se usan `onclick` en el HTML ni escuchadores por elemento.
- **Indicador de carga.** `api.js` lleva la cuenta de peticiones en curso. Mientras sea mayor que 0, se ve un indicador (spinner de Bootstrap) y los botones de envío del formulario activo se desactivan.
- **API apagada.** Si `fetch` falla sin respuesta (error de red), la SPA muestra el mensaje **"No se pudo conectar con el servidor. Verifique que la API esté encendida."** en lugar de una pantalla vacía, con un botón "Reintentar". Un 500 muestra el mensaje genérico de la API.
- **Escapar texto.** Todo texto que viene de la API pasa por `escapar()` (`util.js`) antes de entrar en un `innerHTML`. Esto incluye nombres, descripciones, títulos, motivos y mensajes de error. Es la regla S7.
- **Teléfono a 375 px.** A 375 píxeles de ancho no hay desplazamiento horizontal. Se usan la rejilla de Bootstrap, tablas con `table-responsive`, el menú colapsable (`navbar-expand-md`) y formularios a una columna.
- **Textos.** Todo lo que se ve está en español, trata de usted y usa el vocabulario de la sección 1 (los nombres de etapas y tipos salen de `textos.js`).

#### Criterios de aceptación

- **Dado** el código de `js/vistas/`, **cuando** se busca `addEventListener` y `onclick`, **entonces** solo aparecen los escuchadores delegados de `app.js`, y cada botón o formulario de las vistas usa `data-accion` o `data-form`.
- **Dado** la red limitada a "Slow 3G" en las herramientas del navegador, **cuando** se abre cualquier vista, **entonces** se ve el indicador de carga hasta que llegan los datos.
- **Dado** la API apagada, **cuando** se inicia sesión o se abre cualquier vista, **entonces** se ve "No se pudo conectar con el servidor. Verifique que la API esté encendida." y no una pantalla en blanco.
- **Dado** un paciente o una actividad con el texto `<script>alert(1)</script>` o `<img src=x onerror=alert(1)>`, **cuando** se muestra en cualquier vista, **entonces** aparece como texto literal y no se ejecuta nada.
- **Dado** el navegador en modo teléfono a 375 px de ancho, **cuando** se recorren `#/login`, `#/cartera`, `#/actividades` y `#/resumen`, **entonces** ninguna muestra barra de desplazamiento horizontal.
- **Dado** una ruta inexistente como `#/otra`, **cuando** se abre con sesión, **entonces** la SPA muestra `#/cartera`.
<!-- fin sección 8 -->

## 9. Seguridad (S1–S8)
<!-- Responsable: A -->

Cada regla dice **cómo la cumplimos** (pieza y mecanismo) y **cómo se comprueba** (una prueba concreta). Las pruebas usan la semilla de verificación (`php artisan migrate:fresh --seeder=VerificacionSeeder`) salvo que se diga otra cosa. El resultado de cada prueba se anota en `docs/verificacion.md`.

Datos de la semilla que usan las pruebas:
- **Equipo de Sergio:** Valeria (Comercial El Quetzal, Ferretería Xela, María Coyoy) y Diego (Farmacia Los Altos, Luis Tzul).
- **Equipo de Sandra:** Carlos (Abarrotes San Juan, entre otros) e Iván (desactivado).
- **Disponibles:** Agencia Cuatro Caminos, Café Zunil y Clínica San Rafael.

### S1 · Solo Laravel genera hashes; la API solo los verifica

**Cómo la cumplimos**
- **Panel:** las cuentas se crean y cambian solo en `CuentaController`, con `Hash::make()` y el driver `bcrypt` que trae Laravel (no se cambia a argon). Las semillas también usan `Hash::make()`. Si una cuenta se edita sin escribir contraseña, el hash se conserva.
- **API:** `src/rutas/auth.js` solo llama a `bcryptjs.compare(clave, users.password)`. La API no tiene ninguna ruta que escriba en `users.password` y no importa `bcryptjs.hash`.
- **Código:** la contraseña pública de las semillas solo aparece en `VerificacionSeeder`, `SectorSeeder`, `README.md` y `docs/api.postman_collection.json`.

**Cómo se comprueba**
- **Dado** la base recién sembrada, **cuando** se ejecuta `SELECT email, password FROM users`, **entonces** todos los `password` empiezan con `$2y$` y ninguno es texto legible.
- **Dado** el hash que creó Laravel para Valeria, **cuando** ella inicia sesión con `POST /auth/login`, **entonces** la API responde 200: `bcryptjs` verifica el hash `$2y$` de Laravel.
- **Dado** el repositorio, **cuando** se ejecuta `git grep -n "<contraseña pública>"`, **entonces** solo aparecen los dos seeders, el README y la colección de Postman.
- **Dado** el repositorio, **cuando** se ejecuta `git grep -n "bcrypt.hash\|hashSync\|genSalt" api/`, **entonces** no hay resultados.

### S2 · Los secretos no entran al repositorio

**Cómo la cumplimos**
- **Ignorar:** el `.gitignore` de la raíz (de A) ignora `laravel/.env` y `api/.env`. El de Laravel ya ignora su `.env`.
- **Plantillas:** se suben `laravel/.env.example` y `api/.env.example` con nombres de variables y valores de ejemplo, sin contraseñas ni secretos reales. Por ejemplo, `JWT_SECRET=` vacío y `CORS_ORIGIN=http://127.0.0.1:5500`.
- **`JWT_SECRET`:** cada integrante genera el suyo, de al menos 32 caracteres aleatorios, con `node -e "console.log(require('crypto').randomBytes(48).toString('hex'))"`. Ese comando da 96 caracteres. La API no arranca si `JWT_SECRET` falta o tiene menos de 32 caracteres: muestra un error en la consola y termina.
- **Si un `.env` llegó a un commit:** se cambian todos sus secretos. Borrarlo en un commit posterior no lo saca del historial.

**Cómo se comprueba**
- **Dado** el repositorio, **cuando** se ejecuta `git log --all --name-only --format= | grep -E '(^|/)\.env$'`, **entonces** no hay resultados.
- **Dado** los dos `.env` locales, **cuando** se ejecuta `git check-ignore laravel/.env api/.env`, **entonces** Git imprime las dos rutas (están ignoradas).
- **Dado** `api/.env` con `JWT_SECRET=corto`, **cuando** se ejecuta `npm run dev`, **entonces** la API no arranca y explica que el secreto debe tener al menos 32 caracteres.

### S3 · Ningún dato del usuario se concatena en el SQL

**Cómo la cumplimos**
- **API:** toda consulta usa `pool.execute(sql, [params])` de mysql2 con `?`. La búsqueda se escribe `WHERE c.vendedor_id = ? AND (c.nombre LIKE CONCAT('%', ?, '%') OR c.nit LIKE CONCAT('%', ?, '%'))`. Ningún SQL se arma con plantillas `${…}` ni con `+` sobre datos de la petición. Los filtros que cambian la consulta (`?etapa=`, `?estado=`) se comparan contra una lista fija de valores (400 si no está) antes de usarse.
- **Panel:** Eloquent o el constructor de consultas, por ejemplo `->where('nombre', 'like', '%'.$q.'%')`, que viaja como parámetro enlazado. `DB::raw`, `whereRaw` y `selectRaw` solo llevan expresiones fijas (por ejemplo, la del mes en hora de Guatemala), con los datos del usuario como `?`.

**Cómo se comprueba**
- **Dado** el código, **cuando** se ejecuta `git grep -nE '\$\{' api/src` y se revisa cada resultado, **entonces** ninguno está dentro de un texto SQL.
- **Dado** el código, **cuando** se ejecuta `git grep -nE "DB::raw|whereRaw|selectRaw|orderByRaw" laravel/app`, **entonces** cada resultado usa una expresión fija y los datos del usuario como `?`.
- **Dado** Valeria con sesión, **cuando** pide `GET /clientes?q=' OR '1'='1`, **entonces** la API responde 200 con una lista vacía: no aparecen pacientes de otros asesores y no hay error 500.
- **Dado** el coordinador Sergio en el panel, **cuando** busca `' OR 1=1 -- ` en la lista de pacientes, **entonces** la lista sale vacía, sin error, y no muestra pacientes del equipo de Sandra.

### S4 · Las respuestas no exponen hashes, llaves internas ni datos de otro asesor

**Cómo la cumplimos**
- **API:** cada consulta nombra sus columnas, nunca usa `SELECT *`, y cada respuesta se arma con un objeto explícito con los campos del contrato.
  - No viajan `password`, `remember_token`, `vendedor_id`, `user_id`, `supervisor_id` ni `usuario_id`.
  - El historial devuelve `usuario` como nombre, no como id.
  - `GET /clientes/disponibles` devuelve solo `{ id, nombre }`.
  - Un error 500 devuelve un mensaje genérico; el detalle queda en la consola del servidor.
- **Panel:** el modelo `User` mantiene `password` y `remember_token` en `$hidden`, y ninguna vista los muestra.

**Cómo se comprueba**
- **Dado** la colección de Postman, **cuando** se ejecutan las peticiones de login, clientes, disponibles, oportunidades, actividades y resumen, **entonces** las pruebas de cada petición confirman que el JSON no contiene las claves `password`, `vendedor_id`, `user_id`, `supervisor_id` ni `usuario_id`.
- **Dado** Valeria con sesión, **cuando** pide `GET /clientes/disponibles`, **entonces** cada elemento tiene solo `id` y `nombre`, sin NIT, teléfono ni correo.
- **Dado** una consulta que falla a propósito (la base apagada a mitad de la prueba), **cuando** se pide `GET /clientes`, **entonces** la respuesta es 500 con un mensaje genérico, sin texto de SQL ni nombres de tablas.

### S5 · Lo ajeno responde 404 en las dos puertas

**Cómo la cumplimos**
- **API:** el dueño sale siempre de `req.usuario.id`, que el middleware lee del token y vuelve a leer de la base, nunca del cuerpo.
  - Toda consulta de un recurso lleva su condición de dueño. Para clientes y oportunidades es `WHERE id = ? AND vendedor_id = ?`. Para actividades es un `JOIN clientes` con `clientes.vendedor_id = ?`. Lo mismo vale en las escrituras.
  - Si la consulta no devuelve fila, la respuesta es **404**, igual que si el id no existiera.
- **Panel:** el coordinador busca cada paciente, plan y asesor con un alcance de Eloquent que solo incluye su equipo. Los pacientes visibles son los de un asesor con `supervisor_id` igual al suyo, más los disponibles. La búsqueda termina con `findOrFail()`, así que lo de otro equipo responde **404** aunque se escriba la URL a mano. No se usan políticas que respondan 403.

**Cómo se comprueba**
- **Dado** el token de Valeria y el id de Farmacia Los Altos (paciente de Diego), **cuando** pide `GET /clientes/:id`, **entonces** la API responde **404** con `{ error }`.
- **Dado** lo mismo, **cuando** envía `PATCH /clientes/:id` con `{ "nombre": "Cambiado" }`, **entonces** la API responde **404** y en la base el nombre sigue siendo "Farmacia Los Altos".
- **Dado** el token de Valeria, **cuando** pide `GET /oportunidades/:id` de "Licencias de caja" (de Diego) o envía `PATCH /actividades/:id` de una actividad de un paciente de Diego, **entonces** las dos responden **404**.
- **Dado** Sergio con sesión en el panel y el id de Abarrotes San Juan (de Carlos, equipo de Sandra), **cuando** abre a mano la URL de edición de ese paciente, **entonces** el panel responde **404**; y si envía el formulario de edición directamente (PUT/PATCH), también **404**, sin cambios en la base.
- **Dado** Sergio con sesión en el panel, **cuando** abre a mano el detalle de un plan de Carlos, **entonces** el panel responde **404**.
- **Dado** el token de Valeria, **cuando** envía `POST /clientes` con un `vendedor_id` de Diego en el cuerpo, **entonces** el paciente queda en la cartera de Valeria (el campo se ignora).

### S6 · CORS acepta solo el origen exacto de la SPA

**Cómo la cumplimos**
- **API:** `src/app.js` usa `cors({ origin: process.env.CORS_ORIGIN })`, un texto exacto y no `*`, ni `true`, ni una expresión regular. El valor vive en `api/.env` (`CORS_ORIGIN=http://127.0.0.1:5500`).
- **Atención:** `http://localhost:5500` es **otro origen**. La SPA se abre siempre con `127.0.0.1`.

**Cómo se comprueba**
- **Dado** la API encendida, **cuando** se ejecuta `curl -i -H "Origin: http://127.0.0.1:5500" http://localhost:3000/api/productos`, **entonces** la respuesta trae `Access-Control-Allow-Origin: http://127.0.0.1:5500`.
- **Dado** la API encendida, **cuando** se repite con `-H "Origin: http://otro.test"`, **entonces** la respuesta no trae la cabecera `Access-Control-Allow-Origin`.
- **Dado** la SPA abierta como `http://localhost:5500` (otro origen), **cuando** se intenta iniciar sesión, **entonces** la consola del navegador muestra el bloqueo de CORS y la SPA muestra el mensaje de que no se pudo conectar con el servidor.
- **Dado** el código, **cuando** se busca `origin: '*'` o `origin: true` en `api/src`, **entonces** no hay resultados.

### S7 · El texto del usuario nunca se convierte en código

**Cómo la cumplimos**
- **SPA:** todo texto de la API pasa por `escapar()` de `js/util.js` antes de entrar en un `innerHTML`. `escapar()` reemplaza `&`, `<`, `>`, `"` y `'` por sus entidades, así que también sirve dentro de atributos como `value="…"` o `data-id="…"`. Esto vale para todas las vistas, incluidas `embudo.js` y `plan.js` (sección 8, F6).
- **Panel:** las vistas Blade usan solo `{{ }}`, que escapa. Nunca se usa `{!! !!}` con datos del usuario.
- **Por qué importa:** el token vive en `localStorage`. Un script inyectado en el nombre de un paciente podría leerlo y actuar en nombre del asesor.

**Cómo se comprueba**
- **Dado** el administrador en el panel, **cuando** crea un paciente con nombre `<img src=x onerror=alert(1)>` (NIT CF y un teléfono válido) y lo asigna a Valeria, **entonces** la lista de pacientes del panel muestra ese texto literal y no aparece ninguna alerta.
- **Dado** ese paciente, **cuando** Valeria abre `#/cartera`, crea un plan de tratamiento y una cita para él, y abre `#/embudo`, `#/plan/:id` y `#/actividades`, **entonces** en todas las vistas se ve el texto literal y no aparece ninguna alerta.
- **Dado** el código, **cuando** se ejecuta `git grep -n "{!!" laravel/resources/views`, **entonces** ningún resultado imprime datos del usuario.
- **Dado** el código, **cuando** se busca `innerHTML` en `frontend/js`, **entonces** cada uso inserta HTML armado por la vista, con los datos de la API pasados por `escapar()`.

### S8 · Las reglas aguantan peticiones simultáneas

**Cómo la cumplimos** (API y panel usan el mismo orden de candados: `users` → `clientes` → `oportunidades`):

| Regla | Mecanismo |
|---|---|
| **Tomar un paciente** (`POST /clientes/:id/tomar`) | Transacción → `SELECT id FROM users WHERE id = ? FOR UPDATE` (el asesor) → contar su cartera (si son 10 → 409) → `UPDATE clientes SET vendedor_id = ? WHERE id = ? AND vendedor_id IS NULL`. Si `affectedRows` es 0, otro lo tomó antes: rollback y **409** (o 404 si el id no existe). El `UPDATE` condicional es atómico en MySQL: de N peticiones, solo una encuentra `vendedor_id IS NULL`. |
| **Cartera máxima** (crear, tomar, asignar) | El `FOR UPDATE` sobre la fila del asesor pone en fila todas las operaciones de ese asesor, también entre el panel y la API. Cada una cuenta después de que termina la anterior. |
| **Cambio de etapa** | Transacción → `SELECT … FROM oportunidades WHERE id = ? AND vendedor_id = ? FOR UPDATE` → validar desde la etapa **actual** → `UPDATE` + `INSERT` en `historial_etapas`. El índice único `(oportunidad_id, etapa_nueva)` es el respaldo: un 1062 revierte todo y responde 409. |
| **Duplicados** (NIT, teléfono, producto por plan) | La consulta previa da un mensaje claro, pero el árbitro es el índice único: el 1062 se atrapa y responde **409** en la API. En el panel, el error aparece junto al campo. |

**Cómo se comprueba** (scripts con peticiones en paralelo, por ejemplo con `Promise.all` de `fetch`; resultados en `docs/verificacion.md`):
- **Dado** el paciente disponible Café Zunil y tokens de asesores activos con espacio (Valeria, Diego y Carlos de la semilla de verificación, más Andrea y Kevin de la semilla del sector), **cuando** se envían **10 peticiones `POST /clientes/:id/tomar` al mismo tiempo**, repartidas entre esos tokens, **entonces** exactamente **una** responde 200, las otras **nueve** responden 409, y en la base `clientes.vendedor_id` de Café Zunil es el asesor de la petición ganadora.
- **Dado** Valeria con 3 pacientes, **cuando** se envían 8 `POST /clientes` simultáneos con NIT y teléfonos distintos y válidos, **entonces** exactamente 7 responden 201, 1 responde 409 y Valeria queda con 10 pacientes.
- **Dado** un asesor con 9 pacientes, **cuando** el coordinador le asigna uno desde el panel y al mismo tiempo el asesor toma otro desde la API, **entonces** solo uno lo consigue y el asesor queda con 10.
- **Dado** el plan "Capacitación de personal" en `propuesta`, **cuando** se envían 2 `PATCH /oportunidades/:id/etapa` simultáneos con `{ "etapa": "negociacion" }`, **entonces** uno responde 200, el otro responde 409 o 422, y `historial_etapas` tiene un solo registro `propuesta → negociacion` para ese plan.
- **Dado** dos asesores, **cuando** envían al mismo tiempo `POST /clientes` con el mismo teléfono `5555-9876`, **entonces** uno responde 201, el otro **409**, y en la base hay un solo paciente con `telefono = '55559876'`.

**Pregunta abierta:** en el cambio de etapa simultáneo, la segunda petición espera el candado y luego ve la etapa ya cambiada. Pedir `negociacion` estando en `negociacion` ¿es una transición no permitida (**422**) o un conflicto con el estado actual (**409**)? Depende de la sección 5 (B). La prueba de arriba acepta los dos códigos hasta que B lo decida.

**Pregunta abierta:** ¿puede el coordinador **editar** los datos de un paciente disponible (sin asesor), o solo verlo y asignarlo? El enunciado dice que el coordinador ve los disponibles y los asigna, pero no dice si los edita. La respuesta cambia qué URLs de S5 deben responder 404.
<!-- fin sección 9 -->

## 10. Casos compartidos de NIT y teléfono
<!-- Acordado por A y B. Panel y API deben dar exactamente este resultado -->

| Entrada | Se guarda como | ¿Válido? |
|---|---|---|
| `452 1817-k` | `4521817K` | sí |
| `4521817-K` | `4521817K` | sí (repetido del anterior → 409) |
| `00012345679` | `12345679` | sí |
| `cf` / `CF` | `NULL` (la API devuelve `"CF"`) | sí |
| `4521817-1` | — | no (verificador incorrecto) |
| `+502 5555-1234` | `55551234` | sí |
| `(502) 7761.2345` | `77612345` | sí |
| `50255551234` | `55551234` | sí |
| `5021 2345` | `50212345` | sí (no se quita el 502) |
| `1234-5678` | — | no (empieza con 1) |
| `5555123` | — | no (7 dígitos) |

Regla del prefijo: `+502` se quita siempre; `502` sin `+` solo si después quedan exactamente 8 dígitos.
<!-- fin sección 10 -->

## 11. Cifras de control
<!-- Responsable: A -->

Con la semilla de verificación recién cargada (`php artisan migrate:fresh --seeder=VerificacionSeeder`), el sistema debe mostrar exactamente estas cifras **en el panel y en la API**. Las copiamos tal cual de la sección 11 del enunciado. Los nombres de productos son los de la semilla de verificación, que es igual para todas las parejas, y no se traducen al vocabulario dental.

### 11.1 Por persona, equipo y empresa

| Quién | Mes | Ventas | Total vendido | Pronóstico | Cartera |
|---|---|---|---|---|---|
| Valeria López | octubre 2026 | 2 | Q9,250.00 | Q2,512.88 | 3 de 10 |
| Valeria López | noviembre 2026 | 1 | Q3,600.00 | Q2,512.88 | 3 de 10 |
| Diego Castillo | octubre 2026 | 1 | Q6,000.00 | Q112.50 | 2 de 10 |
| Carlos Ajú | octubre 2026 | 1 | Q7,200.00 | Q4,125.75 | 9 de 10 |
| Equipo de Sergio Ruiz | octubre 2026 | 3 | Q15,250.00 | Q2,625.38 | |
| Equipo de Sandra Pérez | octubre 2026 | 1 | Q7,200.00 | Q4,125.75 | |
| Toda la empresa | octubre 2026 | 4 | Q22,450.00 | Q6,751.13 | |

### 11.2 Por producto · octubre 2026

| Producto | Unidades vendidas | Total |
|---|---|---|
| Plan básico | 5 | Q6,000.00 |
| Plan avanzado | 2 | Q5,200.00 |
| Instalación | 1 | Q450.00 |
| Soporte anual | 3 | Q10,800.00 |

### 11.3 Dónde se mira cada cifra

| Cifras | API | Panel |
|---|---|---|
| Valeria, Diego y Carlos | `GET /reportes/resumen?mes=AAAA-MM` con el token de cada uno, y la vista `#/resumen` de la SPA | Reporte del mes, fila de cada asesor; página del equipo (cartera x de 10) |
| Equipo de Sergio / de Sandra | — | Reporte del mes con la sesión de Sergio o de Sandra |
| Toda la empresa y por producto | — | Reporte del mes con la sesión de Alicia (administradora) |

### 11.4 Errores que vamos a vigilar

Si las cifras no coinciden, el enunciado señala tres causas:

1. **Meses agrupados en UTC.** Si a Valeria le salen **Q5,650.00** en octubre, se está agrupando por mes en UTC. La venta "Equipo para sucursal" se cerró el 1 de noviembre a las 03:30 UTC, que en Guatemala es el **31 de octubre a las 21:30**. Prevención: el panel y la API agrupan con `DATE_FORMAT(fecha_cierre - INTERVAL 6 HOUR, '%Y-%m')` (sección 3, fechas en UTC).
2. **Precio del catálogo en lugar del guardado.** Si le salen **Q9,551.00**, se está multiplicando por el precio actual del catálogo (Plan avanzado a Q2,750.50) y no por el `precio_unitario` guardado en la línea (Q2,600.00). Prevención: el total es siempre `SUM(oportunidad_productos.cantidad * oportunidad_productos.precio_unitario)`; los reportes no hacen `JOIN` a `productos.precio`.
3. **DECIMAL entregado como texto.** Si la API devuelve `"9250.00"` entre comillas, mysql2 está entregando el DECIMAL como texto. Prevención: el pool de mysql2 se crea con `decimalNumbers: true`, y la respuesta trae `9250` como número.

Otros dos errores que también vigilamos:

4. **Pronóstico filtrado por mes.** Si el pronóstico de Valeria cambia entre octubre y noviembre, se está filtrando por mes. El pronóstico usa todas las oportunidades abiertas, sin filtro de mes: Q2,512.88 en los dos.
5. **Probabilidades multiplicadas fuera de SQL.** El pronóstico es `ROUND(SUM(total × probabilidad), 2)` en SQL, con las probabilidades como decimales (0.10, 0.25, 0.50, 0.75). Valeria: 2,750.50 × 0.75 + 900.00 × 0.50 = 2,512.875 → **Q2,512.88**. Nunca se calcula con float en JavaScript ni en PHP.

### 11.5 Criterios de aceptación

- **Dado** la semilla de verificación y el token de Valeria, **cuando** pide `GET /reportes/resumen?mes=2026-10`, **entonces** la API responde 200 con `{ "mes": "2026-10", "ventas": 2, "total": 9250, "pronostico": 2512.88, "cartera": { "clientes": 3, "maximo": 10 } }`, con los montos como números y no como texto.
- **Dado** lo mismo, **cuando** pide `?mes=2026-11`, **entonces** recibe `ventas: 1`, `total: 3600` y `pronostico: 2512.88`.
- **Dado** el token de Diego, y luego el de Carlos, **cuando** cada uno pide `?mes=2026-10`, **entonces** Diego recibe 1, 6000, 112.5 y 2 de 10, y Carlos recibe 1, 7200, 4125.75 y 9 de 10.
- **Dado** Valeria en la SPA, **cuando** abre `#/resumen` y elige octubre de 2026, **entonces** ve 2, Q9,250.00, Q2,512.88 y 3 de 10.
- **Dado** Sergio en el panel, **cuando** abre el reporte de octubre de 2026, **entonces** ve 3 ventas, Q15,250.00 y Q2,625.38 para su equipo, y no ve a Carlos.
- **Dado** Sandra en el panel, **cuando** abre el reporte de octubre de 2026, **entonces** ve 1 venta, Q7,200.00 y Q4,125.75.
- **Dado** Alicia en el panel, **cuando** abre el reporte de octubre de 2026, **entonces** ve 4 ventas, Q22,450.00 y Q6,751.13 para toda la empresa, y la tabla por producto de 11.2 exacta.
- **Dado** la semilla de verificación, **cuando** el administrador cambia en el catálogo el precio de Plan avanzado a Q3,000.00, **entonces** las cifras de 11.1 y 11.2 no cambian.
- **Dado** la semilla de verificación más la semilla del sector (`php artisan migrate:fresh --seed`), **cuando** se consultan las filas de Valeria, Diego y Carlos, **entonces** sus cifras siguen siendo las de 11.1, porque la semilla del sector no les agrega planes.

**Pregunta abierta:** ¿la semilla del sector puede agregar ventas a los asesores o pacientes de la semilla de verificación? Si las agrega, las cifras de equipo y de empresa de 11.1 solo valen con `--seeder=VerificacionSeeder`. Propongo que la semilla del sector use solo a Lucía, Andrea y Kevin, para que el último criterio se cumpla. Hay que confirmarlo al escribir el `SectorSeeder`.
<!-- fin sección 11 -->
