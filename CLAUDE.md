# CLAUDE.md · CRM Clínica Dental

CRM de ventas para la clínica dental "Dientecitos Felices" (Quetzaltenango). Proyecto final de Programación Web, UMES 2026.
Lo trabajan dos integrantes al mismo tiempo. Al empezar, la persona te dice si es **A** o **B**; si no lo dice, pregunta.

## Fuentes de verdad (en este orden)
1. `docs/enunciado.txt`: el enunciado del catedrático. Manda sobre todo.
2. `docs/especificacion.md`: lo que decidimos. No se programa nada que no esté ahí.
3. `docs/plan.md`: arquitectura, contrato de la SPA, fases y tareas.
4. `docs/reparto.md`: quién es dueño de qué y acuerdos técnicos.

Si el código que te piden contradice la especificación, **detente y avisa**. No decidas por tu cuenta.

## Piezas
| Carpeta | Qué es | Tecnología | Dueña |
|---|---|---|---|
| `laravel/` | Panel de administrador y coordinador | Laravel 13, Breeze (Blade), Eloquent | A |
| `api/` | API REST del asesor | Node 24, Express 5, mysql2, jsonwebtoken, bcryptjs, cors | B |
| `frontend/` | SPA del asesor | HTML, Bootstrap 5.3, JavaScript con módulos, sin framework | A (base), B (`js/vistas/embudo.js`, `js/vistas/plan.js`) |
| `docs/` | Especificación, plan, verificación, Postman | Markdown | compartido (ver reparto) |

**Nunca edites un archivo cuya dueña es la otra integrante.** Si hace falta, dilo y para.

## Reglas que no se negocian
- **Un solo dueño del esquema:** las tablas se crean y cambian solo con migraciones de Laravel. La API nunca ejecuta CREATE ni ALTER. **Una migración que ya está en `main` no se edita**: los cambios llegan con migraciones nuevas. La migración original de `users` de Laravel no se toca.
- **Un solo creador de cuentas:** el administrador, desde el panel. No hay registro público. Solo Laravel crea hashes (bcrypt); la API solo los verifica con `bcryptjs`.
- **Panel con Eloquent** (o el constructor de consultas). `DB::raw`/`whereRaw` solo con expresiones fijas, y los datos del usuario siempre como parámetros `?`.
- **API con SQL a mano** con mysql2 y parámetros `?`. Prohibido Sequelize, Prisma, TypeORM, Knex o cualquier ORM o constructor de consultas.
- **SPA sin framework.** LocalStorage solo guarda la sesión (`token` y `usuario`).
- **Nombres:** en el código se usan los nombres del núcleo (`clientes`, `productos`, `oportunidades`, `actividades`, `vendedor`, `supervisor`). En pantalla, el vocabulario dental: Paciente, Tratamiento, Plan de tratamiento, Asesor, Coordinador, Cita. Las rutas y campos del contrato no cambian.
- **Todo en español**, con mensajes que tratan de **usted**.

## Reglas técnicas compartidas (panel y API deben dar el mismo resultado)
- **Fechas:** se guardan en UTC. Laravel `APP_TIMEZONE=UTC` y `'timezone' => '+00:00'` en la conexión mysql. API: mysql2 con `timezone: 'Z'` y `SET time_zone = '+00:00'` al abrir cada conexión. Se muestran en hora de Guatemala (UTC−6 fijo).
- **Mes de una venta:** `DATE_FORMAT(fecha_cierre - INTERVAL 6 HOUR, '%Y-%m')`.
- **Dinero:** DECIMAL, nunca FLOAT. El total de una oportunidad es `SUM(cantidad * precio_unitario)` en SQL. El precio de la línea se copia del catálogo al agregar. Probabilidades: prospecto 0.10, calificado 0.25, propuesta 0.50, negociacion 0.75. Pronóstico = `ROUND(SUM(total × probabilidad), 2)` de las abiertas, sin filtro de mes.
- **Cartera máxima 10.** Para crear, tomar o asignar: transacción + `SELECT id FROM users WHERE id = ? FOR UPDATE` del vendedor, contar, asignar.
- **Orden de candados:** primero `users`, luego `clientes`, luego `oportunidades`.
- **Lo ajeno responde 404** (no 403), en las dos puertas.
- **Duplicados:** se valida antes, pero el árbitro final es el índice único de la base (error 1062 → 409 en la API, error junto al campo en el panel).
- **NIT y teléfono:** se normalizan igual en PHP y JS. Casos obligatorios en la sección 10 de la especificación.
- **XSS:** en Blade nunca `{!! !!}` con datos del usuario. En la SPA todo texto de la API pasa por `escapar()` antes de `innerHTML`.

## Regla propia del sector
Un plan que incluye un tratamiento con `productos.requiere_evaluacion = true` no puede pasar a `propuesta`, `negociacion` ni `ganada`, ni recibir ese tratamiento estando en `propuesta` o `negociacion`, si el paciente no tiene al menos una actividad `reunion` completada. Responde 409. Detalle en la sección 2 de la especificación.

## Semillas y cifras de control
- `php artisan migrate:fresh --seed` → semilla de verificación + semilla del sector.
- `php artisan migrate:fresh --seeder=VerificacionSeeder` → solo la de verificación (la de la defensa).
- Todas las cuentas de las dos semillas usan la contraseña pública del enunciado. **Esa contraseña solo puede escribirse en los seeders, el README y la colección de Postman**: nunca la pongas en este archivo, en la especificación, en el código del panel o de la API, ni en ningún otro lugar.
- Con la semilla de verificación: Valeria, octubre 2026 → 2 ventas, Q9,250.00, pronóstico Q2,512.88, cartera 3 de 10. Toda la empresa, octubre 2026 → 4 ventas, Q22,450.00, pronóstico Q6,751.13.

## Specs por feature (fernando-skills: /spec y /spec-impl)
- `docs/especificacion.md` es la especificación general (qué hace el sistema). Cada feature tiene además su spec en `specs/NN-slug.md` (cómo se construye): se diseña con `/spec` y se implementa con `/spec-impl`.
- **El número y el nombre de cada spec ya están asignados en `specs/README.md`.** Usa siempre ese número y ese nombre, aunque veas otro número libre: las dos integrantes crean specs al mismo tiempo.
- Los specs se escriben en español: `**Estado:**`, `**Depende de:**`, `**Fecha:**`, `**Objetivo:**` y las secciones Alcance, Modelo de datos, Plan de implementación, Criterios de aceptación, Decisiones tomadas y descartadas y Riesgos. Estados: Borrador, En revisión, Aprobado, Implementado, Obsoleto.
- Cada spec dice qué requisitos de `docs/especificacion.md` cumple (L, A, F, S, R) y no puede contradecirla. Sus criterios de aceptación salen de los Dado/Cuando/Entonces de esos requisitos.
- El agente nunca cambia el estado de un spec: lo cambia la persona.
- Rama de cada spec: `spec-NN-slug`. La persona la crea antes de `/spec`; `/spec-impl` la reutiliza.

## Cómo trabajar
- Antes de escribir código, presenta un plan corto (archivos y cómo cumples cada criterio) y espera aprobación.
- No hagas `git commit` ni `git push`: los hace la persona.
- No leas ni muestres archivos `.env`; usa `.env.example`.
- Al terminar una tarea, explica cómo comprobar cada criterio de aceptación.

## Comandos útiles
- Panel: `cd laravel && php artisan serve` (http://localhost:8000) y `npm run dev`
- Pruebas del panel: `cd laravel && php artisan test`
- API: `cd api && npm run dev` (http://localhost:3000/api)
- SPA: Live Server sobre `frontend/` (http://127.0.0.1:5500)
