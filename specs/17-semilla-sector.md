# 17 · Semilla del sector

**Estado:** Borrador
**Depende de:** 01-esquema-y-semilla (tablas, `VerificacionSeeder`, `DatabaseSeeder`, migración `agregar_requiere_evaluacion`), 05-pacientes (`App\Support\Nit` y `App\Support\Telefono`, usados para validar los datos de este spec)
**Fecha:** 2026-11-08
**Dueña:** A
**Rama:** `spec-17-semilla-sector`
**Objetivo:** llenar `SectorSeeder` con datos creíbles de la clínica (coordinadora, asesores, tratamientos, pacientes, planes y citas) que permitan demostrar la regla propia en la defensa sin tocar las cifras de la semilla de verificación.

**Requisitos que cumple:** L1 de `docs/especificacion.md`, viñeta `SectorSeeder` y sus criterios 4, 5 y 6 (cuentas del sector, `requiere_evaluacion` en 2, cartera de 10 o menos, sin NIT ni teléfonos repetidos y las seis etapas). Da los datos de la sección 2.7 (regla propia: R-P1 a R-P7) y cumple el último criterio de 11.5 (las cifras de Valeria, Diego y Carlos no cambian con las dos semillas). Cierra la pregunta abierta de la sección 11.

---

## 1. Alcance

**Incluye**
- `laravel/database/seeders/SectorSeeder.php`: 3 cuentas, 9 tratamientos, 15 pacientes, 12 planes con 12 líneas, 38 registros de historial y 7 actividades, todo dentro de una transacción y con fechas en UTC.
- Un paciente que no venía en la lista inicial (Marco Ajanel, de Kevin) con un plan en `negociacion` que incluye Implante dental y **sin ninguna actividad**, para poder demostrar R-P6 y el caso "sin citas" de 2.7 (decisión D2).
- `laravel/tests/Feature/SemillaSectorTest.php`.
- `docs/especificacion.md`, sección 11 (es de A): la "Pregunta abierta" se reemplaza por la decisión D4.
- `docs/verificacion.md`: la fila de L1 (semilla del sector) en la subsección del panel, que es de A.

**No incluye**
- Cambiar `VerificacionSeeder`, `DatabaseSeeder` (ya llama a las dos semillas en el orden correcto) ni ninguna migración.
- La lógica de la regla propia en la API (spec 13, B) y la carpeta "Regla propia" de Postman (B). Este spec solo deja los datos; la tabla de la sección 3.6 le dice a B qué plan usar para cada criterio.
- Planes, pacientes o actividades para Valeria, Diego, Carlos o cualquier paciente de la semilla de verificación (decisión D4).
- Cambiar el vocabulario de pantalla: los datos se guardan en las tablas del núcleo (`clientes`, `productos`, `oportunidades`, `actividades`).

---

## 2. Revisión de los datos (hecha al escribir este spec)

- **NIT:** los 9 NIT (10293841, 56473826, 91827361, 33445567, 70123454, 24681350, 81357923, 60708093 y 48151629) pasan `App\Support\Nit::esValido`. Ninguno está en `VerificacionSeeder`.
- **Teléfonos:** los 15 teléfonos (481011xx, 512012xx, 513013xx y 776812xx/776811xx) pasan `App\Support\Telefono::esValido` y ninguno está en `VerificacionSeeder` (que usa 5555…, 776[1-7]…, 4123…, 5234…, 3000…).
- **Correos:** todos terminan en `.test` y ninguno se repite con `@crm.test`.
- **Cartera:** Andrea 6 de 10, Kevin 6 de 10. 3 pacientes disponibles.
- **Etapas:** entre Andrea y Kevin aparecen las seis.
- **Precios de las líneas:** todos son iguales al precio del catálogo (se copia al agregar, regla del núcleo).
- **Fin del historial de las abiertas:** este spec usa **2026-11-02 16:00:00 UTC** (decisión cerrada por la persona); `VerificacionSeeder` usa 2026-10-02 16:00:00. La lógica es la misma; solo cambia la fecha final.

---

## 3. Modelo de datos

No se crean tablas ni columnas. Se insertan filas en las tablas del spec 01 con `DB::table()`, igual que `VerificacionSeeder`. `created_at`/`updated_at` de cuentas, tratamientos, pacientes y actividades = `CarbonImmutable::now('UTC')`.

### 3.1 `users` (3)

Insertar primero a la coordinadora (por la FK y `chk_users_supervisor`). Las tres activas. Un solo hash: `Hash::make(VerificacionSeeder::CONTRASENA)` (decisión D3).

| name | email | rol | supervisor_id |
|---|---|---|---|
| Dra. Lucía Méndez | `lucia@dental.test` | supervisor | NULL |
| Andrea Gómez | `andrea@dental.test` | vendedor | Lucía |
| Kevin Chávez | `kevin@dental.test` | vendedor | Lucía |

### 3.2 `productos` (9, todos activos)

| nombre | precio | requiere_evaluacion |
|---|---|---|
| Limpieza dental | 300.00 | false |
| Resina dental | 350.00 | false |
| Extracción simple | 250.00 | false |
| Extracción de cordal | 900.00 | false |
| Blanqueamiento | 1200.00 | false |
| Endodoncia | 1500.00 | false |
| Corona de porcelana | 2500.00 | false |
| Ortodoncia con brackets | 8000.00 | **true** |
| Implante dental | 9000.00 | **true** |

Los precios van como texto (`'300.00'`) para no pasar por float.

### 3.3 `clientes` (15)

`nit = NULL` para CF. `correo = NULL` cuando no hay.

| Vendedor | nombre | nit | telefono | correo |
|---|---|---|---|---|
| Andrea | Sofía Ramírez | NULL | 48101101 | sofia.ramirez@correo.test |
| Andrea | Jorge Cifuentes | 10293841 | 48101102 | NULL |
| Andrea | Colegio Bilingüe Los Altos | 56473826 | 77681103 | administracion@colegiolosaltos.test |
| Andrea | Mariela Chun | NULL | 48101104 | NULL |
| Andrea | Rodrigo Tzoc | 91827361 | 48101105 | NULL |
| Andrea | Gabriela Barrios | NULL | 48101106 | NULL |
| Kevin | Esteban Quemé | 33445567 | 51201201 | NULL |
| Kevin | Paola Fernández | NULL | 51201202 | NULL |
| Kevin | Textiles del Altiplano | 70123454 | 77681204 | NULL |
| Kevin | Héctor Sapón | 24681350 | 51201205 | NULL |
| Kevin | Claudia Ixcot | NULL | 51201206 | NULL |
| Kevin | **Marco Ajanel** | **48151629** | **51201207** | NULL |
| — | Fernando Pú | 81357923 | 51301301 | NULL |
| — | Daniela Monterroso | NULL | 51301302 | NULL |
| — | Andrés Velásquez | 60708093 | 51301303 | NULL |

### 3.4 `oportunidades` (12), `oportunidad_productos` (12) e `historial_etapas` (38)

Recorridos sin retrocesos, como en `VerificacionSeeder`:
- `ganada` = prospecto → calificado → propuesta → negociacion → ganada (5 registros)
- `perdida desde negociacion` = prospecto → calificado → propuesta → negociacion → perdida (5)
- abiertas = el prefijo de `ganada` hasta su etapa (prospecto 1, calificado 2, propuesta 3, negociacion 4)

Fechas del historial: la última es `fecha_cierre` (cerradas) o `2026-11-02 16:00:00` (abiertas); cada registro anterior va 3 días antes. `created_at` de la oportunidad y de sus líneas = primera fecha del historial; `updated_at` de la oportunidad = última. El primer registro tiene `etapa_anterior = NULL`. `usuario_id` del historial = vendedor del plan.

| # | Título | Paciente | Vendedor | Etapa | Cierre UTC | Motivo | Líneas | Historial |
|---|---|---|---|---|---|---|---|---|
| 1 | Ortodoncia completa | Sofía Ramírez | Andrea | calificado | — | — | Ortodoncia con brackets 1 × 8000.00 | 2 |
| 2 | Implante de molar | Jorge Cifuentes | Andrea | negociacion | — | — | Implante dental 1 × 9000.00 | 4 |
| 3 | Limpieza para docentes | Colegio Bilingüe Los Altos | Andrea | ganada | 2026-10-20 16:00:00 | — | Limpieza dental 25 × 300.00 | 5 |
| 4 | Blanqueamiento | Mariela Chun | Andrea | propuesta | — | — | Blanqueamiento 1 × 1200.00 | 3 |
| 5 | Endodoncia y corona | Rodrigo Tzoc | Andrea | perdida | 2026-10-28 18:00:00 | Decidió tratarse en otra clínica | Endodoncia 1 × 1500.00; Corona de porcelana 1 × 2500.00 | 5 |
| 6 | Primera consulta | Gabriela Barrios | Andrea | prospecto | — | — | (sin líneas) | 1 |
| 7 | Resinas | Esteban Quemé | Kevin | ganada | 2026-11-03 17:00:00 | — | Resina dental 3 × 350.00 | 5 |
| 8 | Extracción de cordales | Paola Fernández | Kevin | negociacion | — | — | Extracción de cordal 2 × 900.00 | 4 |
| 9 | Limpieza anual | Textiles del Altiplano | Kevin | calificado | — | — | Limpieza dental 12 × 300.00 | 2 |
| 10 | Implante dental | Héctor Sapón | Kevin | calificado | — | — | Implante dental 1 × 9000.00 | 2 |
| 11 | Ortodoncia | Claudia Ixcot | Kevin | prospecto | — | — | Ortodoncia con brackets 1 × 8000.00 | 1 |
| 12 | **Implante de premolar** | **Marco Ajanel** | **Kevin** | **negociacion** | — | — | **Implante dental 1 × 9000.00** | **4** |

### 3.5 `actividades` (7)

| Paciente | Vendedor | tipo | descripcion | vence_en UTC | completada |
|---|---|---|---|---|---|
| Jorge Cifuentes | Andrea | reunion | Cita de evaluación para implante | 2026-10-10 15:00:00 | true |
| Héctor Sapón | Kevin | reunion | Cita de evaluación con radiografía | 2026-10-30 15:00:00 | true |
| Sofía Ramírez | Andrea | reunion | Cita de evaluación de ortodoncia | 2026-11-20 15:00:00 | false |
| Mariela Chun | Andrea | llamada | Confirmar presupuesto de blanqueamiento | 2026-11-25 15:00:00 | false |
| Paola Fernández | Kevin | tarea | Enviar presupuesto de cordales | 2026-11-01 15:00:00 | false |
| Colegio Bilingüe Los Altos | Andrea | llamada | Coordinar horarios de limpieza | 2026-10-18 15:00:00 | true |
| Claudia Ixcot | Kevin | reunion | Agendar evaluación de ortodoncia | 2026-11-28 15:00:00 | false |

### 3.6 Qué plan demuestra cada criterio de la regla propia (para la colección de B)

| Criterio | Plan | Por qué sirve |
|---|---|---|
| R-P1 · sin cita no hay presupuesto | 1 · Sofía (calificado, ortodoncia) | Sin `reunion` completada → `propuesta` da 409 |
| R-P2 · con cita completada avanza | 10 · Héctor (calificado, implante) | Tiene `reunion` completada → `propuesta` da 200 |
| R-P3 · una cita pendiente no cuenta | 1 · Sofía | Su única `reunion` está pendiente → 409 |
| R-P4 · no se cuela por agregar después | 8 · Paola (negociacion, cordal) | Sin `reunion` completada (solo una tarea) → agregar Implante dental da 409. Usa Extracción de cordal en lugar de Limpieza dental; la condición es la misma (solo tratamientos sin evaluación) |
| R-P5 · planes sin evaluación no se afectan | 9 · Textiles (calificado, limpieza) | Sin actividades → `propuesta`, `negociacion` y `ganada` dan 200 |
| R-P6 · ganar también se protege | 12 · Marco (negociacion, implante) | Sin ninguna actividad → `ganada` da 409 y no registra `fecha_cierre` |
| R-P7 · rechazar siempre se puede | 1 · Sofía | `perdida` con motivo da 200 |

Las peticiones cambian la base: entre corrida y corrida de la carpeta se vuelve a cargar con `php artisan migrate:fresh --seed`.

### 3.7 Cifras del sector (con las dos semillas)

Calculadas con las reglas del núcleo (mes en hora de Guatemala, total = `SUM(cantidad × precio_unitario)`, pronóstico de las abiertas sin filtro de mes):

| Quién | Mes | Ventas | Total vendido | Pronóstico | Cartera |
|---|---|---|---|---|---|
| Andrea Gómez | octubre 2026 | 1 | Q7,500.00 | Q9,350.00 | 6 de 10 |
| Kevin Chávez | octubre 2026 | 0 | Q0.00 | Q12,050.00 | 6 de 10 |
| Kevin Chávez | noviembre 2026 | 1 | Q1,050.00 | Q12,050.00 | 6 de 10 |
| Equipo de Lucía Méndez | octubre 2026 | 1 | Q7,500.00 | Q21,400.00 | |
| Toda la empresa (dos semillas) | octubre 2026 | 5 | Q29,950.00 | Q28,151.13 | |

Andrea: 8000 × 0.25 + 9000 × 0.75 + 1200 × 0.50 + 0 × 0.10 = 9,350.00. Kevin: 1800 × 0.75 + 3600 × 0.25 + 9000 × 0.25 + 8000 × 0.10 + 9000 × 0.75 = 12,050.00. Empresa: 6,751.13 + 21,400.00 = 28,151.13.

---

## 4. Plan de implementación

1. **`SectorSeeder`, esqueleto.** `run()` abre `DB::transaction` y llama a métodos privados `cuentas()`, `productos()`, `clientes()`, `oportunidades()` y `actividades()`, con la misma forma que `VerificacionSeeder` (cada uno devuelve un mapa nombre/correo → id). Constantes `FIN_ABIERTAS = '2026-11-02 16:00:00'` y `DIAS_ENTRE_REGISTROS = 3`. Al terminar este paso, `migrate:fresh --seed` corre sin errores aunque todavía no inserte nada.
2. **Cuentas y tratamientos** (3.1 y 3.2). `migrate:fresh --seed` deja 10 cuentas y 14 productos.
3. **Pacientes** (3.3). Deja 36 clientes.
4. **Planes, líneas e historial** (3.4), con el mismo cálculo de fechas que `VerificacionSeeder`.
5. **Actividades** (3.5).
6. **`tests/Feature/SemillaSectorTest.php`**: en `setUp()` siembra `VerificacionSeeder` y luego `SectorSeeder` (mismo patrón que `EsquemaYSemillaTest`) y comprueba los criterios 1 a 9 de la sección 5.
7. **`docs/especificacion.md` sección 11:** reemplazar el párrafo "Pregunta abierta" por la decisión D4. **`docs/verificacion.md`:** anotar la fila de L1 (semilla del sector) con la evidencia.
8. `cd laravel && php artisan test` en verde (las pruebas que ya existían siguen pasando).

---

## 5. Criterios de aceptación

1. **Dado** una base vacía, **cuando** se ejecuta `php artisan migrate:fresh --seed`, **entonces** termina sin errores y `SELECT COUNT(*)` da 10 en `users`, 14 en `productos`, 36 en `clientes`, 23 en `oportunidades`, 25 en `oportunidad_productos`, 81 en `historial_etapas` y 11 en `actividades` (semilla de verificación + sector).
2. **Dado** esa base, **cuando** se buscan `lucia@dental.test`, `andrea@dental.test` y `kevin@dental.test`, **entonces** existen, están activas, Lucía es `supervisor` sin supervisor, Andrea y Kevin son `vendedor` con `supervisor_id` de Lucía, y sus tres `password` empiezan con `$2y$` y verifican con la contraseña pública (`Hash::check` contra `VerificacionSeeder::CONTRASENA`).
3. **Dado** esa base, **cuando** se consulta `SELECT nombre FROM productos WHERE requiere_evaluacion = 1`, **entonces** salen exactamente Ortodoncia con brackets e Implante dental.
4. **Dado** esa base, **cuando** se consulta `SELECT vendedor_id, COUNT(*) FROM clientes WHERE vendedor_id IS NOT NULL GROUP BY vendedor_id`, **entonces** ningún conteo pasa de 10 (Andrea 6, Kevin 6), y no hay `nit`, `telefono` ni `users.email` repetidos.
5. **Dado** esa base, **cuando** se recorren los `nit` no nulos y los `telefono` de los 15 pacientes del sector, **entonces** todos pasan `Nit::esValido` y `Telefono::esValido`.
6. **Dado** esa base, **cuando** se consulta `SELECT DISTINCT etapa FROM oportunidades` de los planes de Andrea y Kevin, **entonces** aparecen las seis etapas.
7. **Dado** esa base, **cuando** se consulta el historial de "Endodoncia y corona", **entonces** tiene 5 registros (prospecto, calificado, propuesta, negociacion, perdida), el primero con `etapa_anterior` NULL, separados 3 días, y el último en `2026-10-28 18:00:00`; y el de "Implante de premolar" tiene 4 registros y el último en `2026-11-02 16:00:00`.
8. **Dado** esa base, **cuando** se revisan los pacientes de la sección 3.6, **entonces** Sofía, Paola, Textiles y Marco no tienen ninguna `reunion` completada, y Jorge y Héctor sí tienen una.
9. **Dado** esa base, **cuando** se calculan las cifras de Valeria, Diego y Carlos (octubre 2026) y las de Andrea, Kevin y el equipo de Lucía, **entonces** las primeras son las de 11.1 sin cambios y las segundas son las de la tabla 3.7.
10. **Dado** `php artisan migrate:fresh --seeder=VerificacionSeeder`, **cuando** se consulta `SELECT COUNT(*) FROM productos WHERE requiere_evaluacion = 1` y los conteos del criterio 1 de L1, **entonces** da 0 y los conteos siguen siendo 7, 5, 21, 11, 13, 43 y 4 (R-P8).
11. **Dado** el repositorio, **cuando** se busca el texto de la contraseña pública con `git grep`, **entonces** solo aparece en `VerificacionSeeder.php`, `README.md` y `docs/api.postman_collection.json` (no en `SectorSeeder.php` ni en este spec).
12. **Dado** Alicia en el panel con las dos semillas, **cuando** abre el reporte de octubre de 2026, **entonces** ve a Andrea y Kevin en el equipo de Lucía con las cifras de 3.7 y la empresa con 5 ventas, Q29,950.00 y Q28,151.13 (comprobación a mano).

---

## 6. Decisiones tomadas y descartadas

- **D1 · Datos de la persona, sin cambios.** Cuentas, tratamientos, pacientes, planes y actividades son los que dio la persona (cerrados); el fin del historial de las abiertas es 2026-11-02 16:00:00 UTC. *Descartado:* reutilizar 2026-10-02 de `VerificacionSeeder`, porque la persona eligió otra fecha.
- **D2 · Un paciente más para R-P6.** Se agrega Marco Ajanel (NIT 48151629, tel. 51201207) con "Implante de premolar" en `negociacion` y sin actividades. Es el único estado que no se puede alcanzar por la API (la regla lo impide), y R-P6 lo necesita. Se justifica como un plan anterior a que el administrador marcara el implante como "requiere evaluación". Nombre, NIT y teléfono los propuso el agente y se validaron con `Nit` y `Telefono`; la persona puede cambiarlos al revisar. *Descartado:* quitar la cita de Jorge (dejaría su plan en negociacion sin evaluación y perdería el ejemplo de "cita completada" en esa etapa) y dejar R-P6 sin datos.
- **D3 · Contraseña con la constante.** `Hash::make(VerificacionSeeder::CONTRASENA)`: el texto vive en un solo archivo del repositorio y este spec no lo nombra. *Descartado:* escribir el literal en `SectorSeeder` (está permitido, pero queda duplicado).
- **D4 · El sector solo usa sus propias cuentas y pacientes.** Ningún plan ni actividad del sector toca a Valeria, Diego, Carlos, Iván ni a los pacientes de la verificación. Así, las filas por asesor de 11.1 valen con las dos semillas, y las de equipo y empresa solo con `--seeder=VerificacionSeeder` (con las dos, la empresa queda como en 3.7). Esto reemplaza la "Pregunta abierta" de la sección 11. *Descartado:* agregar ventas a los asesores de la verificación.
- **D5 · `DB::table()` dentro de una transacción**, como `VerificacionSeeder`: las fechas históricas (`created_at` del historial) no pasan por Eloquent, y si algo falla no queda la semilla a medias. *Descartado:* modelos Eloquent con `create()`, que pondrían `created_at` = ahora.
- **D6 · Prueba automática.** `SemillaSectorTest` cubre los criterios 1 a 10. *Descartado:* solo consultas a mano.

---

## 7. Riesgos

- **Las pruebas de la regla gastan los datos.** Después de correr la carpeta "Regla propia" (o de ensayar la defensa), Sofía, Héctor, Textiles y Marco cambian de etapa. Mitigación: `php artisan migrate:fresh --seed` antes de cada corrida (anotado en 3.6).
- **El plan de Marco no cumple la regla en la base.** Es a propósito (D2). Si alguien agrega más tarde una comprobación de integridad que exija la regla sobre los datos ya guardados, este plan fallaría. La sección 2.3 dice que la regla solo se cumple en las transiciones, no sobre lo que ya está guardado.
- **Las fechas del sector son de noviembre.** "Resinas" se cierra el 2026-11-03 y la tarea de Paola venció el 2026-11-01, así que en la SPA sale "Vencida". Es lo esperado; si la defensa es antes de esas fechas, los textos "Vencida" cambian.
- **Cifras de empresa con las dos semillas.** Quien pruebe el reporte de Alicia con `--seed` verá 5 ventas y no 4. Mitigación: D4 queda escrita en la sección 11 de la especificación y la tabla 3.7 da las cifras esperadas.
