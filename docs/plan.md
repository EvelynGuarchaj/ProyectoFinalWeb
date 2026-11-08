# Plan · CRM Clínica Dental Sonrisa

## 1. Arquitectura

```
Navegador (coordinador/admin) ──► Panel Laravel :8000 ──┐
                                                         ├──► MySQL 8.4 · crm_dental
Navegador (asesor) ──► SPA :5500 ──► API Express :3000 ──┘
```

- **Panel (monolito):** Blade arma las páginas en el servidor. Único dueño del esquema (migraciones) y único creador de cuentas.
- **API (servicio desacoplado):** JWT HS256, SQL a mano con mysql2. Lee y escribe sobre las tablas que definió Laravel.
- **SPA:** HTML + Bootstrap 5.3 + módulos de JavaScript, sin framework. Consume solo la API.

## 2. Estructura del repositorio

```
crm-dental/
├── README.md                      (B)
├── CLAUDE.md                      (A)
├── .claude/                       (A)
├── specs/                         specs por feature (números en specs/README.md)
├── docs/
│   ├── reparto.md
│   ├── especificacion.md          (A y B, por secciones)
│   ├── plan.md                    (A)
│   ├── verificacion.md            (B crea; A y B llenan)
│   └── api.postman_collection.json (B)
├── laravel/                       (A)
│   ├── app/Http/Controllers/      CuentaController, ProductoController, ClienteController,
│   │                              EquipoController, OportunidadController, ReporteController
│   ├── app/Http/Requests/         CuentaRequest, ProductoRequest, ClienteRequest, AsignarRequest
│   ├── app/Http/Middleware/       CuentaPanel, Rol
│   ├── app/Services/Cartera.php   asignar, reasignar y liberar con candado
│   ├── app/Support/               Nit.php, Telefono.php, Formato.php
│   ├── app/Rules/                 NitValido.php, TelefonoValido.php
│   ├── database/seeders/          VerificacionSeeder, SectorSeeder
│   └── resources/views/           cuentas/, productos/, clientes/, equipo/, oportunidades/, reportes/
├── api/                           (B)
│   └── src/  app.js, servidor.js, db.js, middleware/, rutas/, util/
└── frontend/
    ├── index.html                 (A)
    ├── css/estilos.css            (A)
    └── js/
        ├── app.js estado.js api.js sesion.js util.js textos.js   (A)
        └── vistas/
            ├── login.js cartera.js actividades.js resumen.js     (A)
            └── embudo.js plan.js                                 (B)
```

## 3. Contrato de la SPA

Este contrato permite que A y B construyan vistas distintas al mismo tiempo sin tocar los mismos archivos.

### 3.1 Rutas (las registra A en `app.js`)

| Hash | Vista | Dueña |
|---|---|---|
| `#/login` | `vistas/login.js` | A |
| `#/cartera` | `vistas/cartera.js` | A |
| `#/embudo` | `vistas/embudo.js` | B |
| `#/plan/:id` | `vistas/plan.js` (recibe `params.id`) | B |
| `#/actividades` | `vistas/actividades.js` | A |
| `#/resumen` | `vistas/resumen.js` | A |

Sin sesión, cualquier ruta lleva a `#/login`. Con sesión, `#/` lleva a `#/cartera`.

### 3.2 Forma de una vista

Cada archivo de `vistas/` exporta por omisión un objeto así:

```js
export default {
  titulo: 'Texto para el menú y el título',
  // Se llama al entrar a la ruta. Pide datos a la API y los guarda con cambiar().
  async cargar(params) {},
  // Devuelve el HTML de la vista como texto. TODO dato de la API pasa por escapar().
  render(estado) { return ''; },
  // Clic en un elemento con data-accion="nombre". Recibe el elemento y el evento.
  acciones: {
    async nombre(elemento, evento) {}
  },
  // Envío de <form data-form="nombre">. Recibe los datos del formulario como objeto y el form.
  formularios: {
    async nombre(datos, form) {}
  }
};
```

`app.js` tiene **un solo** escuchador de `click` y uno de `submit` sobre `#app` (delegación de eventos) que busca `data-accion` o `data-form` y llama a la función de la vista activa. Las vistas no agregan escuchadores propios.

### 3.3 Estado (`estado.js`)

```js
estado = {
  sesion: null,        // { token, usuario: { id, nombre, correo } }
  ruta: 'cartera',     // nombre de la ruta activa
  params: {},          // ej. { id: '5' } en #/plan/5
  cargando: 0,         // peticiones en curso (> 0 muestra el indicador)
  aviso: null,         // { tipo: 'success' | 'danger' | 'warning', texto }
  datos: {},           // cada vista usa SOLO su clave: datos.cartera, datos.embudo, datos.plan…
  formularios: {}      // { [nombreForm]: { valores, errores } } para conservar lo escrito
}
```

- `cambiar(parcial)`: mezcla `parcial` en el estado y vuelve a dibujar. **Es la única forma de cambiar el estado.**
- Para los datos de una vista: `cambiar({ datos: { ...estado.datos, embudo: lista } })`.

### 3.4 Funciones compartidas

| Archivo | Función | Qué hace |
|---|---|---|
| `api.js` | `pedir(metodo, ruta, cuerpo?)` | `fetch` a `http://localhost:3000/api` + ruta, con el token. Devuelve el JSON (o `null` en 204). Lleva la cuenta de `estado.cargando`. Si falla lanza `ErrorApi` |
| `api.js` | `class ErrorApi` | `{ status, message, errores }`. `errores` viene en los 422 (`{ campo: mensaje }`). `status` 0 = la API no responde |
| `api.js` | (automático) | Con 401 en plena sesión: borra la sesión, pone el aviso "Su sesión terminó. Ingrese de nuevo." y va a `#/login` |
| `util.js` | `escapar(texto)` | Escapa `& < > " '`. Acepta `null`/números |
| `util.js` | `quetzales(n)` | `9250` → `Q9,250.00` |
| `util.js` | `fechaGT(iso)` | Fecha y hora en Guatemala, ej. `5 oct 2026, 10:00` |
| `util.js` | `aISO(valorLocal)` | `'2026-12-01T10:00'` (de un `datetime-local`) → `'2026-12-01T10:00:00-06:00'` |
| `util.js` | `mesActualGT()` | `'2026-11'` según la hora de Guatemala |
| `util.js` | `errorCampo(errores, campo)` | HTML del mensaje de error de ese campo (escapado), o `''` |
| `util.js` | `claseError(errores, campo)` | `' is-invalid'` o `''` |
| `util.js` | `mostrarError(e)` | Pone `e.message` como aviso de tipo `danger` |
| `textos.js` | `ETAPAS`, `ABIERTAS`, `CERRADAS`, `PROBABILIDAD`, `TIPOS` | Nombres de pantalla de etapas y tipos de actividad, probabilidades |

## 4. Fases y tareas

| Fase | Tarea | Requisito | Responsable | Estado |
|---|---|---|---|---|
| 0 | Especificación (sus secciones) | P1 | A y B | |
| 0 | Entorno Laravel + Breeze + MySQL | — | A | |
| 0 | Entorno de la API | — | B | |
| 1 | Migraciones, modelos y VerificacionSeeder | L1 | A | |
| 1 | Cuentas y roles | L2 | A | |
| 1 | Base de la API, login y middleware | A1, A2 | B | |
| 2 | Catálogo | L3 | A | |
| 2 | Pacientes y asignación | L4 | A | |
| 2 | Mi equipo | L5 | A | |
| 2 | Clientes y actividades de la API | A3, A5 | B | |
| 3 | Base de la SPA y login | F6, F1 | A | |
| 3 | Planes y reportes del panel | L6 | A | |
| 3 | Validación en español | L7 | A | |
| 3 | Semilla del sector | L1 | A | |
| 3 | Oportunidades, regla propia, resumen y productos | A4, A6 | B | |
| 4 | Cartera, actividades y resumen de la SPA | F2, F4, F5 | A | |
| 4 | Embudo y detalle del plan | F3 | B | |
| 4 | Colección de Postman y errores | A7, A8 | B | |
| 5 | Verificación, bitácora y README | P3, P5, A8 | A y B | |

## 5. Qué se le pide al agente en cada fase

- **Fase 0:** redactar secciones de la especificación general con `/seccion`, revisando las decisiones que tomó.
- **Fases 1 a 5:** cada feature con `/spec` (el agente pregunta y escribe `specs/NN-slug.md`), aprobación humana del spec y `/spec-impl NN-slug` (implementa paso a paso con pausas). Después `/verifica <código>` y `/explica`. Los números de los specs están en `specs/README.md`. Prompts completos en las guías de A y B.
- **Revisión:** `/revisa <rama>` sobre el PR de la compañera.
- **Fase 5:** `/verifica` de cada requisito para llenar la matriz; `/bitacora` para cada caso.
- **Nunca se le pide:** hacer commits o push, leer `.env`, editar migraciones ya unidas a main, tocar archivos de la otra integrante.

## 6. Historial de cambios del plan

| 2026-11-08 | Las migraciones del spec 01 (A) y la base de la API del spec 02 (B, PR #4) se empezaron antes de spec-v1, y el PR #4 se unió a main antes de etiquetar por error. A partir de spec-v1, todo el código entra por spec aprobado y PR revisado. |
| 2026-11-08 | L4 (sección 6 de la especificación) se aclaró después de spec-v1: el coordinador puede editar los pacientes disponibles (sin asesor), además de los de su equipo. Se decidió al implementar el spec 05. |
