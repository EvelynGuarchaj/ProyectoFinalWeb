# 10 · Base de la SPA e inicio de sesión

**Estado:** Implementado
**Depende de:** 02-api-base-login (`POST /api/auth/login` con sus 200, 401, 403 y 422; middleware que responde 401; CORS con origen exacto `http://127.0.0.1:5500`)
**Fecha:** 2026-11-08
**Dueña:** A
**Rama:** `spec-10-spa-base`
**Objetivo:** dejar en `frontend/` la base de la SPA (estado, rutas, peticiones, utilidades y layout) que cumple el contrato de `docs/plan.md` §3, con el inicio y cierre de sesión del asesor.

**Requisitos que cumple:** F1 completo y F6 completo de `docs/especificacion.md`, con S7 en la parte de la SPA (`escapar()`). Las comprobaciones de F6 sobre `#/cartera`, `#/actividades` y `#/resumen` se hacen aquí sobre las vistas provisionales y se repiten en los specs 18, 20 y 22 con su contenido real.

---

## 1. Alcance

**Incluye**
- `frontend/index.html`: Bootstrap 5.3 (CSS y bundle JS) desde CDN, `<meta name="viewport">`, `<div id="app"></div>` y `<script type="module" src="js/app.js">`. Título **"Dientecitos Felices · Asesores"**.
- `frontend/css/estilos.css`: lo mínimo (indicador de carga, columnas del embudo, actividades vencidas). Nada con ancho fijo que provoque desplazamiento horizontal a 375 px.
- `frontend/js/estado.js`, `sesion.js`, `api.js`, `util.js`, `textos.js` y `app.js`.
- `frontend/js/vistas/login.js` completa.
- `frontend/js/vistas/cartera.js`, `actividades.js` y `resumen.js` provisionales, con la misma forma que `embudo.js`.
- Anotar en `docs/verificacion.md` las filas de F1 y F6.

**No incluye**
- `js/vistas/embudo.js` y `js/vistas/plan.js`: son de B y **no se modifican**. `app.js` solo los importa.
- El contenido de cartera, actividades y resumen (specs 18, 20 y 22).
- Cambios en la API (CORS, mensajes): son del spec 02, de B.
- Corregir el nombre "Clínica Dental Sonrisa" en `docs/plan.md`: va en un PR de documentación aparte.
- Pruebas automáticas de la SPA: se verifica en el navegador y con comandos de `node` sobre `util.js` (sección 4).
- Frameworks, bundlers o dependencias de npm en `frontend/`.

---

## 2. Modelo de datos

No hay tablas ni migraciones. Se define el estado de la SPA, la sesión guardada y las funciones compartidas del contrato.

### 2.1 Estado (`js/estado.js`)

Exactamente la forma de `docs/plan.md` §3.3:

```js
export const estado = {
  sesion: null,        // { token, usuario: { id, nombre, correo } }
  ruta: 'cartera',
  params: {},          // { id: '5' } en #/plan/5
  cargando: 0,
  aviso: null,         // { tipo: 'success' | 'danger' | 'warning', texto, reintentar?, conservar? }
  datos: {},
  formularios: {}      // { [nombreForm]: { valores, errores } }
};
export function cambiar(parcial) {}   // Object.assign(estado, parcial) y avisa a los suscritos
export function suscribir(funcion) {} // app.js suscribe render()
```

- `cambiar()` es la única forma de cambiar el estado. Cada llamada vuelve a dibujar.
- `aviso.reintentar` (booleano) y `aviso.conservar` (booleano) son campos **adicionales** al contrato; las vistas de B no necesitan usarlos.

### 2.2 Sesión (`js/sesion.js`)

| Función | Qué hace |
|---|---|
| `leerSesion()` | `JSON.parse(localStorage.getItem('sesion'))`; si falta, o el JSON no es válido, o no trae `token` y `usuario`, devuelve `null` |
| `guardarSesion(sesion)` | `localStorage.setItem('sesion', JSON.stringify({ token, usuario }))` |
| `borrarSesion()` | `localStorage.removeItem('sesion')` |

Es el único archivo que toca `localStorage`, y solo con la clave `"sesion"`.

### 2.3 Peticiones (`js/api.js`)

- `class ErrorApi extends Error` con `status`, `message`, `errores` (objeto `{ campo: mensaje }` en los 422, si no `{}`) y `sesionTerminada` (booleano, adicional).
- `pedir(metodo, ruta, cuerpo?)`:
  1. `cambiar({ cargando: estado.cargando + 1 })`.
  2. `fetch('http://localhost:3000/api' + ruta)` con `Content-Type: application/json`, `Authorization: Bearer <token>` si hay sesión, y `JSON.stringify(cuerpo)` si hay cuerpo.
  3. Si `fetch` lanza (API apagada o bloqueo de CORS): `ErrorApi` con `status: 0` y el mensaje **"No se pudo conectar con el servidor. Verifique que la API esté encendida."**
  4. 204 → `null`. 2xx → el JSON.
  5. 401 con sesión y ruta distinta de `/auth/login`: `borrarSesion()`, `cambiar({ sesion: null, datos: {}, formularios: {}, aviso: { tipo: 'warning', texto: 'Su sesión terminó. Ingrese de nuevo.', conservar: true } })`, `location.hash = '#/login'` y lanza `ErrorApi` con `sesionTerminada: true`.
  6. Cualquier otro código no 2xx: `ErrorApi` con el `error` del cuerpo como `message` y `errores` si viene. Si el cuerpo no es JSON, el mensaje genérico de la API: "Ocurrió un error inesperado. Intente de nuevo más tarde."
  7. En `finally`: `cambiar({ cargando: estado.cargando - 1 })`.

### 2.4 Utilidades (`js/util.js`)

| Función | Entrada → salida |
|---|---|
| `escapar(texto)` | Reemplaza `& < > " '` por `&amp; &lt; &gt; &quot; &#39;`. `null`/`undefined` → `''`; números → texto |
| `quetzales(n)` | `Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ' })`: `9250` → `Q9,250.00` |
| `fechaGT(iso)` | `Intl.DateTimeFormat('es-GT', { timeZone: 'America/Guatemala', day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })`: `'2026-10-05T16:00:00.000Z'` → `5 oct 2026, 10:00` (puede llevar punto tras el mes según el motor) |
| `aISO(valorLocal)` | `'2026-12-01T10:00'` → `'2026-12-01T10:00:00-06:00'`; vacío → `''` |
| `mesActualGT()` | `'AAAA-MM'` según `timeZone: 'America/Guatemala'` |
| `errorCampo(errores, campo)` | `<div class="invalid-feedback">mensaje escapado</div>` o `''` |
| `claseError(errores, campo)` | `' is-invalid'` o `''` |
| `mostrarError(e)` | `cambiar({ aviso: { tipo: 'danger', texto: e.message, reintentar: e.status === 0 } })`; si `e.sesionTerminada`, no hace nada (ya hay aviso) |

### 2.5 Textos (`js/textos.js`)

```js
export const ETAPAS = { prospecto: 'Interesado', calificado: 'Evaluado', propuesta: 'Presupuesto enviado',
  negociacion: 'En negociación', ganada: 'Aceptado', perdida: 'Rechazado' };
export const ABIERTAS = ['prospecto', 'calificado', 'propuesta', 'negociacion'];
export const CERRADAS = ['ganada', 'perdida'];
export const PROBABILIDAD = { prospecto: 10, calificado: 25, propuesta: 50, negociacion: 75, ganada: 100, perdida: 0 };
export const TIPOS = { llamada: 'Llamada', reunion: 'Cita', tarea: 'Tarea' };
```

### 2.6 Rutas y layout (`js/app.js`)

| Hash | Ruta | Vista | Menú |
|---|---|---|---|
| `#/login` | `login` | `vistas/login.js` | — |
| `#/cartera` | `cartera` | `vistas/cartera.js` | Pacientes |
| `#/embudo` | `embudo` | `vistas/embudo.js` (B) | Planes |
| `#/plan/:id` | `plan` | `vistas/plan.js` (B), `params.id` | — |
| `#/actividades` | `actividades` | `vistas/actividades.js` | Actividades |
| `#/resumen` | `resumen` | `vistas/resumen.js` | Resumen |

**Lectura del hash:** `'#/plan/5'` → `{ ruta: 'plan', params: { id: '5' } }`. `#/plan` sin id cuenta como ruta inexistente.

**Al cambiar el hash** (`hashchange` en `window` y una vez al arrancar):
1. Sin sesión y ruta distinta de `login` → `location.hash = '#/login'`.
2. Con sesión y ruta `login`, vacía (`#/`, sin hash) o inexistente → `location.hash = '#/cartera'`.
3. Si no, `cambiar({ ruta, params, aviso })`, donde el aviso se borra salvo que tenga `conservar: true` (en ese caso se conserva una vez y se le quita la marca). `estado.formularios` se conserva.
4. `document.title = vista.titulo + ' · Dientecitos Felices'` y `await vista.cargar(params)`; si lanza, `mostrarError(e)`.

Al arrancar: `cambiar({ sesion: leerSesion() })` antes de leer el hash.

**`render()`** (suscrito a `cambiar`): `#app.innerHTML = layout(estado) + vista.render(estado)`. Después, si `estado.cargando > 0`, pone `disabled` a todos los `button[type="submit"]` de `#app`.

**Layout:**
- Con sesión: `navbar navbar-expand-md` de Bootstrap con botón colapsable, enlaces Pacientes, Planes, Actividades y Resumen (el de la ruta activa con `active`), `escapar(usuario.nombre)` y el botón **"Cerrar sesión"** (`data-accion="salir"`). Sin sesión, no hay barra.
- Indicador de carga: `spinner-border` de Bootstrap con texto oculto "Cargando…", visible solo con `estado.cargando > 0`.
- Aviso: `alert alert-{tipo}` con `escapar(texto)` y un botón de cerrar `data-accion="cerrarAviso"`. Si `aviso.reintentar`, además un botón **"Reintentar"** (`data-accion="reintentar"`).

**Delegación:** un solo `addEventListener('click')` y uno de `'submit'` sobre `#app`.
- `click`: `evento.target.closest('[data-accion]')`; busca primero en las acciones globales y luego en `vista.acciones[nombre](elemento, evento)`.
- `submit`: `evento.target.closest('form[data-form]')`, `preventDefault()`, `datos = Object.fromEntries(new FormData(form))` y `vista.formularios[nombre](datos, form)`.
- Acciones globales: `cerrarAviso` (`cambiar({ aviso: null })`), `salir` (`borrarSesion()`, `cambiar({ sesion: null, datos: {}, formularios: {}, aviso: null })`, `location.hash = '#/login'`) y `reintentar` (`cambiar({ aviso: null })` y `vista.cargar(estado.params)` con `mostrarError` si falla).
- Errores dentro de una acción o formulario que la vista no atrape → `mostrarError(e)`.

### 2.7 Vista de inicio de sesión (`js/vistas/login.js`)

- `titulo: 'Iniciar sesión'`. `cargar()` no pide nada.
- `render`: tarjeta centrada a una columna con "Dientecitos Felices", formulario `data-form="entrar"` con `correo` (`type="email"`, `autocomplete="username"`) y `clave` (`type="password"`, `autocomplete="current-password"`), botón `type="submit"` "Ingresar". Los valores y errores salen de `estado.formularios.entrar`; el correo se vuelve a poner escapado en `value`, la contraseña nunca.
- `formularios.entrar(datos)`:
  1. `cambiar({ formularios: { ...estado.formularios, entrar: { valores: { correo: datos.correo }, errores: {} } }, aviso: null })` **antes** de pedir.
  2. `pedir('POST', '/auth/login', { correo, clave })`.
  3. 200 → `guardarSesion(r)`, `cambiar({ sesion: r, formularios: {} })`, `location.hash = '#/cartera'`.
  4. 422 → `errores` en `estado.formularios.entrar` (mensaje junto a cada campo con `claseError` y `errorCampo`).
  5. 401, 403, 0 o 500 → `mostrarError(e)`: se ve el `error` de la API ("Correo o contraseña incorrectos.", "Esta cuenta no es de asesor. Use el panel de la clínica.", "Su cuenta está desactivada. Comuníquese con el administrador.") o el de la API apagada con "Reintentar". No se escribe nada en `localStorage`.

### 2.8 Vistas provisionales

`cartera.js` (`titulo: 'Pacientes'`), `actividades.js` (`titulo: 'Actividades'`) y `resumen.js` (`titulo: 'Resumen del mes'`), con la forma de `embudo.js`: `cargar` vacío, `render` con `<p class="text-muted">… en construcción.</p>`, `acciones: {}` y `formularios: {}`.

---

## 3. Plan de implementación

Cada paso deja la SPA abriéndose en Live Server (`http://127.0.0.1:5500`) sin errores en la consola.

1. **Esqueleto.** `index.html`, `css/estilos.css`, `textos.js`, `estado.js` y las tres vistas provisionales. `app.js` mínimo que importa las seis vistas y dibuja `#/cartera`.
2. **Utilidades.** `util.js` completo (sin `mostrarError` todavía conectado a la vista). Comprobación con `node` (CA12).
3. **Rutas y layout.** Lectura del hash, `hashchange`, ruta inexistente → `#/cartera`, barra de navegación, aviso y delegación de `click`/`submit` con `cerrarAviso`. Sin protección de sesión todavía.
4. **Sesión y peticiones.** `sesion.js` y `api.js` (cargando, status 0, 204, 401 en plena sesión, `ErrorApi`); `mostrarError`; indicador de carga, botones desactivados y "Reintentar".
5. **Inicio y cierre de sesión.** `login.js`, protección de rutas, `salir` y el nombre del asesor en la barra.
6. **Cierre.** `git grep -n "addEventListener\|onclick" frontend/js`, revisión de cada `innerHTML` y anotar F1 y F6 en `docs/verificacion.md`.

---

## 4. Criterios de aceptación

Semilla: `php artisan migrate:fresh --seeder=VerificacionSeeder`, API encendida (`cd api && npm run dev`) y la SPA en `http://127.0.0.1:5500`.

**F1 · Sesión**
- **CA1 · Entrar.** Dado Valeria, cuando ingresa su correo y la contraseña correcta, entonces la SPA muestra `#/cartera` con "Valeria López" en la barra, y en `localStorage` existe solo la clave `sesion` con `token` y `usuario`.
- **CA2 · Coordinador y administrador.** Dado Sergio o Alicia con la contraseña correcta, cuando envían el formulario, entonces se ve "Esta cuenta no es de asesor. Use el panel de la clínica.", la SPA sigue en `#/login` y `localStorage` está vacío. Dado Iván (desactivado), se ve "Su cuenta está desactivada. Comuníquese con el administrador.".
- **CA3 · Contraseña incorrecta.** Dado el correo de Valeria con una contraseña incorrecta, cuando se envía, entonces se ve "Correo o contraseña incorrectos.", el correo sigue escrito, y **no** aparece "Su sesión terminó. Ingrese de nuevo.".
- **CA4 · Campos vacíos.** Dado el formulario con el correo vacío (quitando `required` en las herramientas del navegador), cuando se envía, entonces el mensaje de la API aparece bajo el campo con la clase `is-invalid`.
- **CA5 · Cerrar sesión.** Dado Valeria con sesión, cuando pulsa "Cerrar sesión", entonces la clave `sesion` desaparece de `localStorage` y la SPA muestra `#/login` sin barra de navegación.
- **CA6 · 401 en plena sesión.** Dado Valeria con sesión, cuando el administrador la desactiva en el panel y ella pulsa "Reintentar" o cambia de vista a una que pida datos (o, mientras las vistas son provisionales, cuando se altera el `token` en `localStorage`, se recarga y se ejecuta en la consola `import('./js/api.js').then(m => m.pedir('GET', '/yo'))`), entonces la SPA borra la sesión, muestra `#/login` y el aviso "Su sesión terminó. Ingrese de nuevo.".
- **CA7 · Protección de rutas.** Dado un navegador sin sesión, cuando se abre `#/actividades` o `#/plan/5`, entonces la SPA muestra `#/login`. Dado Valeria con sesión, cuando se abre `#/login`, entonces la SPA muestra `#/cartera`.

**F6 · Calidad de la interfaz**
- **CA8 · Delegación.** Dado el código, cuando se ejecuta `git grep -n "addEventListener\|onclick" frontend/js`, entonces solo aparecen en `app.js` el `click` y el `submit` sobre `#app` y el `hashchange` de `window`.
- **CA9 · Indicador de carga.** Dado la red en "Slow 3G", cuando se envía el formulario de inicio de sesión, entonces se ve el spinner y el botón "Ingresar" está desactivado hasta que llega la respuesta.
- **CA10 · API apagada.** Dado la API detenida, cuando se envía el formulario de inicio de sesión, entonces se ve "No se pudo conectar con el servidor. Verifique que la API esté encendida." con el botón "Reintentar", y no una pantalla en blanco. Dado la SPA abierta como `http://localhost:5500` (otro origen, S6), se ve el mismo mensaje.
- **CA11 · Escapar.** Dado en la consola `localStorage.setItem('sesion', JSON.stringify({ token: 'x', usuario: { id: 1, nombre: '<img src=x onerror=alert(1)>', correo: 'a@b.c' } }))` y una recarga, entonces la barra muestra el texto literal y no aparece ninguna alerta. Cada `innerHTML` de `frontend/js` inserta HTML armado por la vista, con los datos de la API pasados por `escapar()`.
- **CA12 · Utilidades.** Dado `cd frontend/js && node --input-type=module -e "import * as u from './util.js'; …"`, entonces `u.escapar('<a href=\"x\">\'&')` da `&lt;a href=&quot;x&quot;&gt;&#39;&amp;`, `u.escapar(null)` da `''`, `u.quetzales(9250)` da `Q9,250.00`, `u.aISO('2026-12-01T10:00')` da `2026-12-01T10:00:00-06:00` y `u.fechaGT('2026-10-05T16:00:00.000Z')` contiene `5`, `oct`, `2026` y `10:00`. (Si `util.js` importa `estado.js`, la importación funciona igual en Node porque no toca el DOM al cargar.)
- **CA13 · 375 px.** Dado el modo teléfono a 375 px, cuando se recorren `#/login`, `#/cartera`, `#/actividades` y `#/resumen`, entonces ninguna muestra barra de desplazamiento horizontal y el menú queda colapsado tras el botón de hamburguesa.
- **CA14 · Ruta inexistente.** Dado Valeria con sesión, cuando se abre `#/otra`, `#/` o `#/plan`, entonces la SPA muestra `#/cartera`. Cuando se abre `#/plan/5`, la ruta activa es `plan` con `params.id === '5'` y se ve "Detalle en construcción.".
- **CA15 · Aviso.** Dado un aviso visible, cuando se pulsa su botón de cerrar, desaparece; y cuando se cambia de vista, desaparece (salvo el de sesión terminada al llegar a `#/login`).
- **CA16 · Textos.** Dado cualquier pantalla de este spec, todo lo visible está en español y trata de usted; el título de la pestaña termina en "· Dientecitos Felices".
- **CA17 · Archivos de B intactos.** `git diff main -- frontend/js/vistas/embudo.js frontend/js/vistas/plan.js` está vacío.

---

## 5. Decisiones tomadas y descartadas

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| Nombre "Dientecitos Felices" en el título | "Clínica Dental Sonrisa" (pedido inicial y `docs/plan.md`) | Decidido por la integrante: la especificación manda sobre el plan. `plan.md` se corrige aparte |
| Botón "Cerrar sesión" | "Salir" (pedido inicial) | Decidido por la integrante: es el texto de F1. La acción se sigue llamando `salir` |
| 401 y 403 del login muestran el `error` de la API; 422 junto a cada campo | Texto fijo "Correo o contraseña incorrectos." en la SPA | Decidido por la integrante: es lo que pide F1, y un solo origen del mensaje evita que la SPA y la API digan cosas distintas |
| "Reintentar" en el aviso cuando `status === 0` y botones de envío desactivados con `cargando > 0`, en la base | Que cada vista los ponga en la fase 4 | Decidido por la integrante: F6 los pide para todas las vistas, incluidas las de B |
| Al navegar se borra el aviso y se conserva `estado.formularios` | Borrar ambos; no borrar nada | Decidido por la integrante: el aviso es de la pantalla anterior; lo escrito lo limpia cada vista |
| Campo `aviso.conservar` para que "Su sesión terminó" sobreviva al salto a `#/login` | Que el enrutador no borre nunca el aviso de tipo `warning` | El `hashchange` llega después del 401 y lo borraría; una marca explícita es más clara que una regla por tipo |
| Campo `ErrorApi.sesionTerminada` y `mostrarError` lo ignora | Dejar que la vista muestre el 401 | Si no, el aviso "Su cuenta ya no tiene acceso…" taparía "Su sesión terminó. Ingrese de nuevo." |
| `PROBABILIDAD` en porcentaje (10, 25, 50, 75, 100, 0) | Fracción (0.10…) | Es la misma escala que el campo `probabilidad` de la API (§4.3); el pronóstico lo calcula el servidor, la SPA solo lo muestra |
| El `hashchange` se escucha en `window` | Interceptar los clics de los enlaces del menú | Los enlaces son `<a href="#/…">` normales; así funcionan atrás/adelante del navegador. No es un escuchador por elemento de vista |
| `estado.sesion` se carga con `leerSesion()` al arrancar, sin pedir `/yo` | Validar la sesión contra la API al abrir | Una sesión vencida se detecta en la primera petición con el 401 automático; evita una petición extra en cada recarga |
| Base de la API fija `http://localhost:3000/api` en `api.js` | Archivo de configuración | Es el valor del contrato (§4.1); la SPA no tiene paso de compilación |
| Verificación manual en el navegador y `node` para `util.js` | Pruebas automáticas con un framework de pruebas | La SPA no usa npm; las pruebas del enunciado son manuales en F1–F6 |

---

## 6. Riesgos

- **Redibujar borra lo que se está escribiendo.** Cada `cambiar()` (incluido el `cargando` de `pedir`) reemplaza el `innerHTML`. Toda vista con formulario debe guardar `valores` en `estado.formularios` **antes** de llamar a `pedir`, como hace `login.js`. B debe saberlo para `plan.js`.
- **El menú colapsado se cierra al redibujar.** El `collapse` de Bootstrap vive en el DOM; tras un `render()` vuelve a cerrarse. Es aceptable: se redibuja al navegar.
- **Formato de `Intl` según el motor.** `es-GT` puede dar `oct` u `oct.` y la coma puede variar; CA12 comprueba las partes y no el texto exacto.
- **Origen de la SPA.** Abrirla como `localhost:5500` en lugar de `127.0.0.1:5500` provoca el bloqueo de CORS (S6); se ve como API apagada.
- **Contrato con B.** `aviso.reintentar`, `aviso.conservar` y `ErrorApi.sesionTerminada` son adicionales. Si B ya usa `estado.aviso` o `ErrorApi` en `embudo.js`/`plan.js`, debe seguir funcionando sin conocerlos; avisarle en el PR.

---

## Lo que **no** entra en este spec

- Contenido de cartera, actividades y resumen (specs 18, 20 y 22).
- Cambios en `embudo.js` y `plan.js` (B).
- Corrección del nombre en `docs/plan.md`.
- Pruebas automáticas de la SPA.
