# 01 · Esquema y semilla de verificación

**Estado:** Implementado
**Depende de:** ninguno (usa el esqueleto de Laravel 13 + Breeze de `panel/entorno` y la sección 3 de `docs/especificacion.md`, ya unidos a `main`)
**Fecha:** 2026-11-07
**Dueña:** A
**Rama:** `spec-01-esquema-y-semilla`
**Objetivo:** crear el esquema completo del núcleo en MySQL con migraciones de Laravel, los modelos Eloquent y la semilla de verificación, para que `migrate:fresh --seed` y `migrate:fresh --seeder=VerificacionSeeder` dejen la base lista sin pasos manuales.

**Requisitos que cumple:** L1 y la sección 3 de `docs/especificacion.md` (modelo de datos y dónde se cumple cada regla); mientras la sección 6 (L1) siga pendiente, también las secciones 6 y 11 de `docs/enunciado.txt`. Prepara la base para R3 a R7 y la columna de la regla propia.

---

## 1. Alcance

**Incluye**
- 8 migraciones nuevas (la de `users` de Laravel no se toca).
- 7 modelos con `$table` explícito: `User` (extendido), `Producto`, `Cliente`, `Oportunidad`, `OportunidadProducto`, `HistorialEtapa`, `Actividad`.
- `VerificacionSeeder` con exactamente los datos de la sección 11 del enunciado.
- `SectorSeeder` vacío y `DatabaseSeeder` que llama a los dos en orden.

**No incluye**
- Datos del sector (spec 17-semilla-sector).
- Validaciones de formularios, normalización de NIT/teléfono en PHP (spec 05/16).
- Lógica de la regla propia (spec 13 en la API; panel en spec 12). Aquí solo se crea la columna `requiere_evaluacion`.

---

## 2. Modelo de datos

Orden de las migraciones (por las llaves foráneas), todas creadas con `php artisan make:migration`:

| # | Migración | Tabla |
|---|---|---|
| 1 | `extender_users` (`--table=users`) | users |
| 2 | `crear_productos` | productos |
| 3 | `crear_clientes` | clientes |
| 4 | `crear_oportunidades` | oportunidades |
| 5 | `crear_oportunidad_productos` | oportunidad_productos |
| 6 | `crear_historial_etapas` | historial_etapas |
| 7 | `crear_actividades` | actividades |
| 8 | `agregar_requiere_evaluacion` (`--table=productos`) | productos |

### 2.1 users (extensión)
| Columna | Tipo | Reglas |
|---|---|---|
| rol | ENUM('administrador','supervisor','vendedor') NOT NULL DEFAULT 'vendedor' | |
| supervisor_id | BIGINT UNSIGNED NULL, FK → users.id **sin** ON DELETE / ON UPDATE | MySQL prohíbe acciones referenciales en columnas usadas por una CHECK |
| activo | BOOLEAN NOT NULL DEFAULT TRUE | las cuentas no se borran |

### 2.2 productos
`id`, `nombre` VARCHAR(120) UNIQUE, `precio` DECIMAL(10,2), `activo` BOOLEAN DEFAULT TRUE, timestamps.
Migración 8: `requiere_evaluacion` BOOLEAN NOT NULL DEFAULT FALSE (columna de sector con valor por omisión, no rompe el contrato).

### 2.3 clientes
`id`, `vendedor_id` NULL FK → users, `nombre` VARCHAR(150), `nit` VARCHAR(12) NULL UNIQUE, `telefono` CHAR(8) UNIQUE, `correo` VARCHAR(150) NULL, timestamps.
NIT `NULL` = consumidor final (CF); varios NULL no chocan con el único.

### 2.4 oportunidades
`id`, `cliente_id` FK → clientes, `vendedor_id` FK → users, `titulo` VARCHAR(150), `etapa` ENUM('prospecto','calificado','propuesta','negociacion','ganada','perdida') DEFAULT 'prospecto', `motivo_perdida` VARCHAR(255) NULL, `fecha_cierre` DATETIME NULL (`dateTime`, no `timestamp`), timestamps.
"negociacion" va sin tilde en la base.

### 2.5 oportunidad_productos
`id`, `oportunidad_id` FK `cascadeOnDelete`, `producto_id` FK → productos, `cantidad` SMALLINT UNSIGNED, `precio_unitario` DECIMAL(10,2), timestamps, UNIQUE (`oportunidad_id`, `producto_id`).

### 2.6 historial_etapas
`id`, `oportunidad_id` FK `cascadeOnDelete`, `usuario_id` FK → users, `etapa_anterior` ENUM(etapas) NULL, `etapa_nueva` ENUM(etapas), `created_at` DATETIME (sin `updated_at`), UNIQUE (`oportunidad_id`, `etapa_nueva`).
El único es el árbitro de dos cambios simultáneos; es válido porque no se permiten retrocesos (`docs/reparto.md`, acuerdo 8).

### 2.7 actividades
`id`, `cliente_id` FK `cascadeOnDelete`, `vendedor_id` FK → users, `tipo` ENUM('llamada','reunion','tarea'), `descripcion` VARCHAR(500), `vence_en` DATETIME NULL, `completada` BOOLEAN DEFAULT FALSE, timestamps.

### 2.8 Restricciones CHECK (copiadas de la sección 3 de `docs/especificacion.md`)
Se agregan con `DB::statement('ALTER TABLE … ADD CONSTRAINT <nombre> CHECK (…)')` después de crear la tabla o columna; el `down()` las quita con `ALTER TABLE … DROP CHECK <nombre>` antes de borrar columnas o tablas. Las expresiones son **exactamente** las de la especificación; si una cambia allí, se cambia aquí con una migración nueva.

| Nombre | Tabla | Expresión (especificación §3) | Sección |
|---|---|---|---|
| chk_users_supervisor | users | `(rol = 'vendedor' AND supervisor_id IS NOT NULL) OR (rol <> 'vendedor' AND supervisor_id IS NULL)` | 3.2 |
| chk_productos_precio | productos | `precio > 0` | 3.3 |
| chk_clientes_telefono | clientes | `telefono REGEXP '^[2-9][0-9]{7}$'` | 3.4 |
| chk_clientes_nit | clientes | `nit IS NULL OR REGEXP_LIKE(nit, '^[1-9][0-9]*[0-9K]$', 'c')` | 3.4 |
| chk_oportunidades_motivo | oportunidades | `(etapa = 'perdida') = (motivo_perdida IS NOT NULL)` | 3.5 |
| chk_oportunidades_motivo_vacio | oportunidades | `motivo_perdida IS NULL OR CHAR_LENGTH(TRIM(motivo_perdida)) BETWEEN 1 AND 255` | 3.5 |
| chk_oportunidades_cierre | oportunidades | `(etapa IN ('ganada','perdida')) = (fecha_cierre IS NOT NULL)` | 3.5 |
| chk_lineas_cantidad | oportunidad_productos | `cantidad BETWEEN 1 AND 999` | 3.6 |
| chk_lineas_precio | oportunidad_productos | `precio_unitario > 0` | 3.6 |

La longitud máxima del NIT (12) la pone la columna `VARCHAR(12)`, no la CHECK.

Lo que una CHECK no puede hacer (y queda en el panel y la API): que `supervisor_id` apunte a una cuenta con rol supervisor, el dígito verificador módulo 11 del NIT, y la cartera máxima de 10.

---

## 3. Modelos

Todos con `protected $table` explícito (Laravel pluraliza en inglés).

| Modelo | Tabla | Casts | Relaciones / extras |
|---|---|---|---|
| User | users | `activo` boolean (más los de Breeze) | `$fillable` + `rol`, `supervisor_id`, `activo`; `esAdmin()`, `esSupervisor()`, `esVendedor()`; `supervisor()` belongsTo, `vendedores()` hasMany(supervisor_id), `clientes()` hasMany(vendedor_id), `oportunidades()` hasMany(vendedor_id) |
| Producto | productos | `precio` decimal:2, `activo` y `requiere_evaluacion` boolean | — |
| Cliente | clientes | — | (relaciones mínimas: `vendedor()`, `oportunidades()`, `actividades()`) |
| Oportunidad | oportunidades | `fecha_cierre` datetime | constantes `ETAPAS`, `ABIERTAS`, `PROBABILIDAD`; `cliente()`, `vendedor()`, `lineas()` hasMany OportunidadProducto, `historial()` hasMany HistorialEtapa |
| OportunidadProducto | oportunidad_productos | `precio_unitario` decimal:2 | `oportunidad()`, `producto()` |
| HistorialEtapa | historial_etapas | `created_at` datetime | `const UPDATED_AT = null`; `oportunidad()`, `usuario()` |
| Actividad | actividades | `vence_en` datetime, `completada` boolean | `cliente()`, `vendedor()` |

`Oportunidad::PROBABILIDAD` = prospecto 10, calificado 25, propuesta 50, negociacion 75, ganada 100, perdida 0 (porcentajes enteros; en SQL se usan como 0.10…0.75 según CLAUDE.md).
`Oportunidad::ABIERTAS` = prospecto, calificado, propuesta, negociacion.

---

## 4. Semilla de verificación

Fuente obligatoria: sección 11 de `docs/enunciado.txt`. Se cargan **exactamente** esos datos, insertados directamente (Eloquent o `DB::table`), sin pasar por validaciones del panel.

- **Cuentas (7):** correos exactos de la tabla; contraseña pública del enunciado con `Hash::make` (bcrypt), calculada una sola vez y reutilizada. Supervisores: Sergio Ruiz (Valeria, Diego) y Sandra Pérez (Carlos, Iván). Iván Toj con `activo = false`. Se insertan primero administrador y supervisores, luego vendedores (por la CHECK y la FK).
- **Productos (5):** "Licencia anterior" inactivo; `requiere_evaluacion = false` en todos.
- **Clientes (21):** NIT compacto como en la tabla, `NULL` donde dice CF; correo solo donde aparece. Valeria 3, Diego 2, Carlos 9, Iván 1, disponibles 6.
- **Oportunidades (11):** etapa, `motivo_perdida = 'Eligió a la competencia'` solo en Plan familiar, `fecha_cierre` UTC de la tabla.
- **Líneas:** cantidad × precio guardado de la tabla (Renovación de planes guarda Plan avanzado a 2600.00). Plan familiar lleva una línea de "Licencia anterior" aunque esté inactivo (dato histórico).
- **Historial (43):** creación (`etapa_anterior` NULL → prospecto) + un registro por cada avance; ganadas pasan por las 4 abiertas; Plan familiar: propuesta → perdida. `usuario_id` = vendedor de la oportunidad. Fechas cada 3 días hacia atrás desde la fecha final (cierre, o 2026-10-02 16:00 UTC si sigue abierta). `oportunidades.created_at` = fecha del registro de creación.
- **Actividades (4):** `vence_en` UTC y `completada` según la tabla.

Tabla de control del historial:

| Oportunidad | Etapa final | Registros | Creación (UTC) | Último registro (UTC) |
|---|---|---|---|---|
| Renovación de planes | ganada | 5 | 2026-09-23 16:00 | 2026-10-05 16:00 |
| Equipo para sucursal | ganada | 5 | 2026-10-20 03:30 | 2026-11-01 03:30 |
| Soporte del hogar | ganada | 5 | 2026-10-20 06:30 | 2026-11-01 06:30 |
| Ampliación a bodega | negociacion | 4 | 2026-09-23 16:00 | 2026-10-02 16:00 |
| Capacitación de personal | propuesta | 3 | 2026-09-26 16:00 | 2026-10-02 16:00 |
| Plan familiar | perdida | 4 | 2026-10-11 18:00 | 2026-10-20 18:00 |
| Seguimiento anual | prospecto | 1 | 2026-10-02 16:00 | 2026-10-02 16:00 |
| Licencias de caja | ganada | 5 | 2026-09-28 15:00 | 2026-10-10 15:00 |
| Mantenimiento | calificado | 2 | 2026-09-29 16:00 | 2026-10-02 16:00 |
| Plan anual | ganada | 5 | 2026-10-03 20:00 | 2026-10-15 20:00 |
| Cotización de equipo | negociacion | 4 | 2026-09-23 16:00 | 2026-10-02 16:00 |
| **Total** | | **43** | | |

**SectorSeeder:** clase vacía con el comentario `// Se llena en la fase 3 (spec 17-semilla-sector).`
**DatabaseSeeder:** `$this->call([VerificacionSeeder::class, SectorSeeder::class]);`

---

## 5. Plan de implementación

1. **Migraciones** (en el orden de la sección 2), cada una con `up()` y `down()` simétricos; CHECK con `DB::statement` después de `Schema::create`/`Schema::table`.
2. **Modelos** (sección 3), empezando por extender `User`.
3. **Semilla:** `VerificacionSeeder`, `SectorSeeder`, `DatabaseSeeder`.
4. **Pruebas** (`tests/Feature/EsquemaYSemillaTest.php`) contra la base MySQL de pruebas `crm_test` (ya existe), configurada en `phpunit.xml` (`DB_CONNECTION=mysql`, `DB_DATABASE=crm_test`); no se usa SQLite. Cubren conteos, cifras de control y rechazo de cada CHECK.

---

## 6. Criterios de aceptación

Derivados de los criterios de la sección 3 de `docs/especificacion.md` y de la sección 11 del enunciado. Cuando se escriba L1 (sección 6, hoy pendiente), se ajustarán a sus Dado/Cuando/Entonces.

- **CA1 · Esquema completo.** Dado MySQL vacío, cuando se corre `php artisan migrate:fresh`, entonces existen las 7 tablas con sus FK, los únicos (productos.nombre, clientes.nit, clientes.telefono, (oportunidad_id, producto_id), (oportunidad_id, etapa_nueva)) y las 9 CHECK con los nombres de la sección 2.8 (`information_schema.CHECK_CONSTRAINTS`).
- **CA2 · Migración de users intacta.** `0001_01_01_000000_create_users_table.php` no aparece en el diff (`git diff main -- laravel/database/migrations/0001_01_01_000000_create_users_table.php` vacío).
- **CA3 · Las CHECK rechazan.** Un INSERT con precio 0, teléfono `12345678`, NIT `0123`, cantidad 1000, etapa `perdida` sin motivo, motivo `'   '`, etapa `ganada` sin fecha de cierre, o vendedor sin supervisor falla con error 3819.
- **CA4 · Reversible.** `php artisan migrate:rollback` hasta el inicio termina sin errores y vuelve a migrar.
- **CA5 · Semilla exacta.** Dado `php artisan migrate:fresh --seeder=VerificacionSeeder`, entonces hay 7 users, 5 productos, 21 clientes, 11 oportunidades, 13 líneas, 43 registros de historial y 4 actividades, y ninguna fila del sector.
- **CA6 · Cifras de control.** Con esa semilla: Valeria, octubre 2026 (mes en UTC−6) → 2 ventas, Q9,250.00; noviembre → 1, Q3,600.00; pronóstico de Valeria Q2,512.88; cartera 3 de 10. Toda la empresa, octubre → 4 ventas, Q22,450.00, pronóstico Q6,751.13. Por producto, octubre: Plan básico 5 / Q6,000.00, Plan avanzado 2 / Q5,200.00, Instalación 1 / Q450.00, Soporte anual 3 / Q10,800.00.
- **CA7 · Las dos semillas.** `php artisan migrate:fresh --seed` termina sin errores y ejecuta VerificacionSeeder y luego SectorSeeder.
- **CA8 · Contraseñas.** Todas las cuentas tienen hash bcrypt (`$2y$`) que verifica con la contraseña pública; la contraseña solo aparece en `VerificacionSeeder.php` (`grep -r` en `laravel/` fuera de `database/seeders` no la encuentra).

---

## 7. Decisiones tomadas y descartadas

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| CHECK con `DB::statement` | Validar solo en el panel y la API | Dos aplicaciones escriben; la base es el árbitro final (sección 6 del enunciado) |
| `supervisor_id` FK sin acciones ON DELETE/UPDATE | `nullOnDelete()` | MySQL no permite acciones referenciales en columnas usadas por una CHECK; además las cuentas no se borran |
| `requiere_evaluacion` en migración aparte | Incluirla en `crear_productos` | Separa núcleo y capa de sector; muestra el patrón de "columna nueva con valor por omisión" |
| `fecha_cierre`, `vence_en`, `created_at` del historial como DATETIME | TIMESTAMP | TIMESTAMP convierte con la zona de la sesión y termina en 2038; DATETIME guarda UTC tal cual |
| Único `(oportunidad_id, etapa_nueva)` en historial | Candado de aplicación solamente | Sin retrocesos, cada etapa aparece una vez; la base frena el doble registro concurrente |
| Semilla con inserciones directas | Pasar por los servicios del panel | Los datos son fijos y algunos violarían reglas de uso (línea de producto inactivo, precio distinto del catálogo) |
| `PROBABILIDAD` en enteros (10…100) | Decimales 0.10… | Pedido por la integrante; el cálculo en SQL usa los decimales de CLAUDE.md |

---

## 8. Riesgos

- **Desfase con la especificación:** las CHECK de la sección 2.8 se copiaron de la sección 3. Si la sección 3 cambia, hay que actualizar este spec y agregar una migración nueva (las que ya están en `main` no se editan).
- **Pruebas con SQLite:** Laravel prueba por omisión con SQLite, que no acepta `ALTER TABLE … ADD CONSTRAINT … CHECK` ni `REGEXP`. **Decidido por la integrante:** las pruebas usan MySQL con la base `crm_test` (ya existe), configurada en `phpunit.xml`; no se saltan las CHECK fuera de MySQL, porque saltarlas esconde justo lo que se quiere probar. Como `RefreshDatabase` borra y recrea las tablas, `crm_test` debe ser una base distinta de la de desarrollo.
- **Versión de MySQL:** las CHECK se aplican desde MySQL 8.0.16; en versiones anteriores se ignoran en silencio. Verificar con `SELECT VERSION();`.
- **Orden de inserción de cuentas:** insertar un vendedor antes que su supervisor rompe la FK; la semilla inserta por grupos.
- **Zona horaria del entorno:** el esqueleto ya trae `APP_TIMEZONE=UTC` en `.env.example` y `'timezone' => '+00:00'` en `config/database.php`. Cada integrante debe tener lo mismo en su `.env` local, o las fechas de la semilla se guardarán corridas.
- **`updated_at` de oportunidades:** la sección 11 no lo fija; se propone igual a la fecha de su último registro de historial.
