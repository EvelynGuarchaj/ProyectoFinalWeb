# Reparto del trabajo · CRM Clínica Dental

Proyecto final de Programación Web · Pareja de 2 integrantes

- **Integrante A:** _(nombre)_ → **Panel Laravel + base de la SPA**
- **Integrante B:** _(nombre)_ → **API REST + embudo de la SPA**

> La idea central: **cada quien es dueño de sus carpetas y archivos**. Nadie edita un archivo del otro sin avisar. Los pocos archivos compartidos tienen un dueño, que es quien hace el cambio (sección 3).

---

## 1. Decisiones de base (las tomamos juntos el día 1)

### Repositorio y nombres

| Cosa | Nombre |
|---|---|
| Repositorio | `crm-dental` |
| Base de datos MySQL | `crm_dental` |
| Panel Laravel | `http://localhost:8000` |
| API | `http://localhost:3000/api` |
| SPA (Live Server) | `http://127.0.0.1:5500` |

### Regla de nombres (para no romper el contrato)

- **En el código** (tablas, rutas, campos, archivos) usamos los **nombres del núcleo**: `clientes`, `productos`, `oportunidades`, `actividades`. El catedrático hace pruebas automáticas contra esos nombres.
- **En pantalla** usamos el vocabulario dental:

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

### Regla propia del sector (propuesta)

> **Un plan de tratamiento que incluye un tratamiento que requiere evaluación (ortodoncia, implantes) no puede pasar a "Presupuesto enviado" ni a etapas posteriores si el paciente no tiene al menos una cita (`reunion`) completada.**

- Columna nueva: `productos.requiere_evaluacion` (booleano, **por omisión falso**). Así, con solo la semilla de verificación, la regla nunca se activa y el núcleo se comporta exacto como el enunciado.
- Se revisa en la API al cambiar de etapa a `propuesta`, `negociacion` o `ganada`, y al agregar un tratamiento que requiere evaluación a un plan que ya está en `propuesta` o `negociacion`.
- Respuesta si no se cumple: **409** con `{ "error": "El paciente necesita una cita de evaluación completada antes de recibir el presupuesto." }`.
- No necesita campos nuevos en el contrato: la "cita de evaluación" es una actividad tipo `reunion` que ya existe.

### Decisiones de la sección 7 (propuesta sencilla, la confirman en la especificación)

| Pregunta | Decisión | Alternativa descartada |
|---|---|---|
| Transiciones del embudo | Avanzar una etapa a la vez entre las abiertas; **perder desde cualquier etapa abierta**; ganar solo desde negociación. Sin saltos ni retrocesos → **422** | Permitir retrocesos (complica el historial y el pronóstico) |
| Cliente reasignado | Los planes **cerrados** se quedan con su asesor (conserva el crédito). Los **abiertos** pasan al nuevo asesor. Las actividades siguen al paciente | Que todo pase al nuevo asesor (le quita ventas al anterior) |
| Liberar cliente con planes abiertos | **No se permite**: el panel muestra un mensaje. Primero se cierran o se reasigna | Marcar los planes como perdidos automáticamente |
| Cartera de asesor desactivado | Los pacientes **se quedan asignados** hasta que el coordinador los reasigne (coincide con Iván Toj en la semilla) | Liberarlos automáticamente |
| Campos que el contrato no lista | Se **ignoran** | Rechazarlos con 422 |

### Acuerdos técnicos que los dos deben respetar

Esto es lo que más choca entre panel y API si no se acuerda antes:

1. **Fechas:** la base guarda todo en UTC. Laravel con `APP_TIMEZONE=UTC` y `'timezone' => '+00:00'` en la conexión mysql; la API con `timezone: 'Z'` en mysql2 y `SET time_zone = '+00:00'` en cada conexión del pool.
2. **Mes en Guatemala (reportes):** los dos usan la misma expresión: `DATE_FORMAT(fecha_cierre - INTERVAL 6 HOUR, '%Y-%m')`. Hora de Guatemala fija UTC−6.
3. **Dinero:** mysql2 con `decimalNumbers: true` (si no, devuelve `"9250.00"` como texto). Las probabilidades se multiplican en SQL como decimales (`0.75`), nunca en JavaScript ni en PHP con float.
4. **Candado de cartera:** para crear, tomar o asignar un cliente, **los dos** bloquean la fila del vendedor con `SELECT id FROM users WHERE id = ? FOR UPDATE` dentro de una transacción, cuentan sus clientes y luego asignan. Así el panel y la API no se pisan.
5. **Orden de los candados:** siempre primero `users` (el vendedor), después `clientes`, después `oportunidades`. Si los dos lados bloquean en el mismo orden, no hay bloqueos mutuos (deadlocks).
6. **NIT y teléfono:** A los programa en PHP y B en JS, y **los dos deben pasar la misma tabla de casos** (sección 6).
7. **Contraseñas:** solo Laravel crea hashes (bcrypt, que es el que trae por omisión; no cambiarlo a argon). La API usa `bcryptjs` (no `bcrypt`). Todas las cuentas de las dos semillas usan la contraseña pública del enunciado, que **solo puede escribirse en los seeders, el README y la colección de Postman** (ni siquiera en este archivo).
8. **Historial sin duplicados:** como no permitimos retrocesos, cada etapa aparece una sola vez por oportunidad. La migración agrega un índice único `(oportunidad_id, etapa_nueva)` en `historial_etapas`: es el árbitro final si llegan dos cambios de etapa al mismo tiempo.

---

## 2. Quién hace qué

### Integrante A · Panel Laravel + base de la SPA

**Carpetas que son suyas:** `laravel/` completa y `frontend/` (menos `js/vistas/embudo.js` y `js/vistas/plan.js`).

| Código | Tarea | Archivos principales |
|---|---|---|
| L1 | Migraciones con llaves, únicos y CHECK. Columna `requiere_evaluacion`. `VerificacionSeeder` (con los 43 registros de historial) y `SectorSeeder` | `database/migrations/*`, `database/seeders/VerificacionSeeder.php`, `SectorSeeder.php` |
| L2 | Breeze sin registro, cuentas y roles, bloqueo de vendedor e inactivos | `CuentaController`, `CuentaRequest`, `middleware/SoloPanel.php`, `views/cuentas/` |
| L3 | Catálogo de tratamientos (con casilla "requiere evaluación") | `ProductoController`, `ProductoRequest`, `views/productos/` |
| L4 | Pacientes: lista, búsqueda, alta, edición, asignar, reasignar, liberar, eliminar | `ClienteController`, `ClienteRequest`, `app/Support/Nit.php`, `Telefono.php`, `views/clientes/` |
| L5 | Página del equipo del coordinador (cartera x/10 y ventas del mes) | `EquipoController`, `views/equipo/` |
| L6 | Planes (solo lectura) y reporte del mes por asesor y por tratamiento | `OportunidadController`, `ReporteController`, `views/oportunidades/`, `views/reportes/` |
| L7 | Mensajes de validación en español, tratando de usted | `lang/es/validation.php` |
| F1 | Vista de inicio de sesión, guardar/borrar token, volver al login con 401 | `js/vistas/login.js` |
| F2 | Cartera: lista con búsqueda, x de 10, alta de paciente, disponibles y "tomar" | `js/vistas/cartera.js` |
| F4 | Actividades: pendientes, completadas, vencidas, nueva cita/llamada/tarea | `js/vistas/actividades.js` |
| F5 | Resumen del mes con selector | `js/vistas/resumen.js` |
| F6 | Base de la SPA: estado → render, delegación de eventos, indicador de carga, aviso de API apagada, escapar texto, 375 px | `index.html`, `css/estilos.css`, `js/app.js`, `js/api.js`, `js/estado.js`, `js/util.js`, `js/textos.js` |

**Documentos de A:** `docs/plan.md` y `CLAUDE.md`.

### Integrante B · API REST + embudo de la SPA

**Carpetas que son suyas:** `api/` completa, `frontend/js/vistas/embudo.js`, `frontend/js/vistas/plan.js` y `docs/api.postman_collection.json`.

| Código | Tarea | Archivos principales |
|---|---|---|
| A1 | `POST /auth/login` con JWT HS256 (403 a no vendedores, 401 idéntico) | `src/rutas/auth.js` |
| A2 | Middleware que valida el token y **vuelve a leer al usuario** en cada petición | `src/middleware/auth.js` |
| A3 | Clientes: cartera, alta, disponibles, tomar (atómico), editar | `src/rutas/clientes.js`, `src/util/nit.js`, `src/util/telefono.js` |
| A4 | Oportunidades, productos de la oportunidad, cambio de etapa con historial en una transacción. **Regla propia** | `src/rutas/oportunidades.js` |
| A5 | Actividades con fechas ISO con zona y `vencida` calculada | `src/rutas/actividades.js`, `src/util/fechas.js` |
| A6 | `GET /reportes/resumen` con SQL | `src/rutas/reportes.js` |
| A7 | Errores en JSON: 400, 404, 422 por campo, 500 genérico. CORS con el origen exacto | `src/middleware/errores.js`, `src/app.js` |
| A8 | Colección de Postman (una petición por endpoint, errores principales, prueba de la regla propia) | `docs/api.postman_collection.json` |
| `GET /productos` | Tratamientos activos | `src/rutas/productos.js` |
| Base | Conexión, arranque, `.env.example` | `src/db.js`, `src/app.js`, `src/servidor.js`, `.env.example` |
| F3 | Embudo: tablero por etapa abierta, lista de cerrados | `js/vistas/embudo.js` |
| F3 | Detalle del plan: tratamientos, cantidades, subtotal, total, historial, cambiar etapa, pedir motivo al rechazar | `js/vistas/plan.js` |

**Documentos de B:** `README.md` y `docs/verificacion.md` (matriz, pruebas de seguridad y de peticiones simultáneas, cifras de control).

### Compartido (los dos)

| Qué | Cómo se reparte |
|---|---|
| `docs/especificacion.md` (P1) | **A** escribe: sector, regla propia, modelo de datos, requisitos L, requisitos F1, F2, F4, F5 y F6, seguridad (S1–S8) y cifras de control. **B** escribe: contrato de la API, decisiones de la sección 7, requisitos A y F3. A deja el esqueleto con todas las secciones marcadas; cada quien escribe solo en la suya |
| `docs/verificacion.md` (P3) | **B** crea el esqueleto con una subsección por pieza. **A** llena las filas del panel y de sus vistas de la SPA; **B**, las de la API y del embudo |
| Bitácora de IA (P5) | Cada uno anota **al menos 2 casos propios** en su subsección de la bitácora, al final de `verificacion.md` (el enunciado pide 3 en total; con 4 sobra) |
| Cambio de requisito (spec-v2) | Los dos actualizan la especificación; lo implementa el dueño de la carpeta afectada. Siempre con **migraciones nuevas**, nunca editando las que existen |
| Revisión de código | Cada Pull Request lo revisa y aprueba **el otro**. Esto también prepara la defensa |

---

## 3. Cómo no chocar en Git

### Ramas

- `main`: siempre funciona. Nadie hace commit directo.
- Cada feature tiene su spec (fernando-skills: `/spec` y `/spec-impl`) y su rama `spec-NN-slug`. **Los números ya están repartidos en `specs/README.md`** (A usa 01, 03, 04, 05, 07, 09, 10, 12, 14, 16, 17, 18, 20 y 22; B usa 02, 06, 08, 11, 13, 15, 19, 21 y 23), así nunca crean dos specs con el mismo número.
- Los documentos van en ramas `docs/<tema>` (por ejemplo `docs/spec-a`, `docs/verificacion`).
- Se une a `main` con Pull Request aprobado por la otra, con "Create a merge commit" (no "Squash").

### Mensajes de commit (en español, cortos)

```
panel: agrega migración de clientes con CHECK de teléfono
api: tomar cliente de forma atómica
spa: tablero del embudo por etapa
docs: decisiones de la sección 7
```

### Archivos compartidos y su único dueño

| Archivo | Dueño | El otro… |
|---|---|---|
| `.gitignore` | A (lo crea el día 1) | pide por mensaje lo que falte |
| `CLAUDE.md` | A | propone reglas de la API por mensaje |
| `README.md` | B | le pasa a B los pasos para levantar Laravel |
| `frontend/js/app.js` (rutas y menú) | A | **no lo toca**: A deja registradas desde el inicio las vistas `embudo` y `plan` |
| `frontend/js/api.js`, `util.js`, `textos.js` | A | si necesita una función nueva, se la pide a A o la pone dentro de su propia vista |
| Migraciones | A | si la API necesita una columna, se la pide a A |

### Contrato entre las dos mitades de la SPA

A deja creados `embudo.js` y `plan.js` como **vistas provisionales que ya cumplen el contrato de vistas** (descrito en `docs/plan.md`, sección "Contrato de la SPA"):

```js
// js/vistas/embudo.js
export default {
  titulo: 'Planes de tratamiento',
  async cargar(params) {},
  render(estado) { return '<p class="text-muted">Embudo en construcción.</p>'; },
  acciones: {},
  formularios: {}
};
```

B solo reemplaza el contenido de esos dos archivos. Las dos vistas usan `api.js` (`pedir`), `estado.js` (`estado`, `cambiar`), `util.js` (`escapar`, `quetzales`, `fechaGT`, `aISO`, `errorCampo`) y `textos.js` (nombres de etapas) que hace A.

---

## 4. Fases y orden de trabajo

> El enunciado exige que el código llegue **después** de la etiqueta `spec-v1`. Antes de eso solo se prepara el entorno.

| Fase | Integrante A | Integrante B | Al terminar |
|---|---|---|---|
| **0 · Especificación y entorno** | Sus secciones de la especificación. Crea el proyecto Laravel con Breeze, MySQL `crm_dental`, `.gitignore`, `CLAUDE.md` | Sus secciones de la especificación. Instala Node 24, Postman, prepara `api/package.json` | Etiqueta **`spec-v1`** |
| **1 · Cimientos** | **Primero que todo:** migraciones + `VerificacionSeeder` (B depende de esto). Luego L2 cuentas y roles | `db.js`, `app.js`, errores, `nit.js`, `telefono.js`, `fechas.js` (no necesitan la base) y luego A1 + A2 login y middleware | `migrate:fresh --seeder=VerificacionSeeder` funciona y Valeria obtiene token |
| **2 · Núcleo** | L3 catálogo, L4 pacientes, L5 equipo | A3 clientes (con atómico), A5 actividades | Un paciente asignado en el panel aparece en `GET /clientes` |
| **3 · Dinero y embudo** | L6 planes y reporte, L7 mensajes, `SectorSeeder` | A4 oportunidades + regla propia, A6 resumen, `GET /productos` | Las cifras de control coinciden en panel **y** API |
| **4 · SPA** | F6 base, F1 login, F2 cartera, F4 actividades, F5 resumen | F3 embudo y detalle del plan, colección de Postman (A8) | Un plan ganado en la SPA aparece en el reporte del panel |
| **5 · Verificación** | `plan.md` al día, revisa `verificacion.md`, bitácora | `README.md`, `verificacion.md`, pruebas simultáneas, bitácora | Etiqueta **`entrega-final`** |

Consejo del enunciado que vale la pena seguir: hagan **una petición real de la SPA a la API apenas exista el login** (fase 1 o 2). Ahí aparecen los problemas de CORS.

---

## 5. Datos del sector (para el `SectorSeeder`, lo hace A)

Reglas: no repetir correos, NIT ni teléfonos de la semilla de verificación, máximo 10 pacientes por asesor, NIT válidos.

- **Coordinadora:** Dra. Lucía Méndez · `lucia@dental.test`
- **Asesores:** Andrea Gómez · `andrea@dental.test`, Kevin Chávez · `kevin@dental.test`
- **Tratamientos** (precios aproximados en Guatemala; confírmenlos antes de entregar):

| Tratamiento | Precio | Requiere evaluación |
|---|---|---|
| Limpieza dental | Q300.00 | no |
| Resina dental | Q350.00 | no |
| Extracción simple | Q250.00 | no |
| Extracción de cordal | Q900.00 | no |
| Blanqueamiento | Q1,200.00 | no |
| Endodoncia | Q1,500.00 | no |
| Corona de porcelana | Q2,500.00 | no |
| Ortodoncia con brackets | Q8,000.00 | **sí** |
| Implante dental | Q9,000.00 | **sí** |

- Pacientes con planes en **todas** las etapas y algunas citas completadas y pendientes, para que la regla propia se pueda demostrar en la defensa.

---

## 6. Casos de prueba compartidos (NIT y teléfono)

El panel (A) y la API (B) deben dar **exactamente** el mismo resultado:

| Entrada | Se guarda como | ¿Válido? |
|---|---|---|
| `452 1817-k` | `4521817K` | sí |
| `4521817-K` | `4521817K` | sí (y es repetido del anterior → 409) |
| `00012345679` | `12345679` | sí |
| `cf` / `CF` | `NULL` (la API devuelve `"CF"`) | sí |
| `4521817-1` | — | no (verificador incorrecto) |
| `+502 5555-1234` | `55551234` | sí |
| `(502) 7761.2345` | `77612345` | sí |
| `1234-5678` | — | no (empieza con 1) |
| `5555123` | — | no (7 dígitos) |
| `50255551234` | `55551234` | sí (se quita el `502` porque quedan 8 dígitos) |
| `5021 2345` | `50212345` | sí (**no** se quita el `502`: si se quitara, solo quedarían 5 dígitos) |

Regla del prefijo: `+502` se quita siempre; `502` sin `+` solo se quita si después quedan exactamente 8 dígitos.

---

## 7. Preparación de la defensa

La nota es **individual** y el catedrático puede preguntar a cualquiera sobre cualquier parte. Por eso:

- **A debe poder explicar de la parte de B:** cómo se toma un cliente de forma atómica, por qué el middleware vuelve a leer al usuario, y cómo se guarda el cambio de etapa con el historial en una sola transacción.
- **B debe poder explicar de la parte de A:** las restricciones CHECK de las migraciones, cómo el coordinador solo ve su equipo (404 a lo ajeno), y cómo el reporte agrupa por mes en hora de Guatemala.
- **Los dos:** por qué solo Laravel crea el esquema y las cuentas, por qué `bcryptjs`, por qué escapar texto en la SPA, y la regla propia.
- Una semana antes: **ensayo cruzado**. Cada uno hace 5 preguntas al otro sobre su parte y le pide un cambio en vivo (por ejemplo, "cambia el máximo de cartera a 12" o "agrega un filtro").

---

## 8. Retos opcionales (si sobra tiempo)

| Reto | Quién | Por qué |
|---|---|---|
| Exportar el reporte del mes a CSV | A | Es una ruta más en `ReporteController` |
| Limitar intentos fallidos de login | B | Es un middleware en `auth.js` |

---

## Lista rápida del día 1

- [ ] Llenar los nombres de A y B en este archivo
- [ ] Crear el repo `crm-dental` y agregarse los dos (y al catedrático)
- [ ] Registrar el sector "Clínica dental" en el aula virtual
- [ ] Confirmar la regla propia y las decisiones de la sección 7
- [ ] A: proyecto Laravel + Breeze + MySQL + `.gitignore`
- [ ] B: estructura de `api/` + `.env.example`
- [ ] Empezar `docs/especificacion.md`, cada quien en su sección
