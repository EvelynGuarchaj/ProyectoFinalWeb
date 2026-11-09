# 18 · Cartera en la SPA

**Estado:** Aprobado 
**Depende de:** 10-spa-base (`estado.js`, `api.js`, `util.js`, `app.js` con delegación, indicador de carga y aviso), 06-api-clientes (B: `GET /clientes?q=`, `POST /clientes`, `GET /clientes/disponibles` y `POST /clientes/:id/tomar`; hoy en la rama `origin/spec-06-api-clientes`, todavía no en `main`), 15-api-resumen (B: `GET /reportes/resumen`; su spec todavía no existe en `specs/`)
**Fecha:** 2026-11-08
**Dueña:** A
**Rama:** `spec-18-spa-cartera`
**Objetivo:** reemplazar la vista provisional `#/cartera` por la cartera del asesor: sus pacientes con búsqueda en el servidor, el contador "x de 10", el alta de pacientes y la lista de disponibles con el botón "Tomar".

**Requisitos que cumple:** F2 completo de `docs/especificacion.md`, y sobre `#/cartera` las comprobaciones de F6 que el spec 10 hizo con la vista provisional (delegación, indicador de carga, API apagada, `escapar()` (S7) y 375 px).

---

## 1. Alcance

**Incluye**
- `frontend/js/vistas/cartera.js`: reemplaza la vista provisional del spec 10 con su contenido real.
- `frontend/css/estilos.css`: solo si hace falta algún ajuste menor para 375 px. Sin anchos fijos.
- Anotar en `docs/verificacion.md` las filas de F2.

**No incluye**
- Editar un paciente (`PATCH /clientes/:id`) o ver su detalle (`GET /clientes/:id`): F2 no lo pide.
- Registrar actividades de un paciente desde la cartera (spec 20, F4).
- Liberar o borrar pacientes: lo hace el panel (specs 05 y 07).
- Cambios en `api.js`, `util.js`, `estado.js`, `app.js` o `textos.js` (spec 10). Si hiciera falta uno, se avisa antes y no se hace dentro de este spec.
- Cambios en la API (specs 06 y 15, de B) y en `embudo.js` y `plan.js` (B).
- Filtrar, contar o validar en el navegador: la búsqueda, el contador y las validaciones son del servidor.
- Pruebas automáticas de la SPA: se verifica en el navegador (sección 4).

---

## 2. Modelo de datos

No hay tablas ni migraciones. Se define la parte del estado que usa la vista y la forma de la pantalla.

### 2.1 Estado de la vista

La vista usa solo `estado.datos.cartera` y los formularios `alta` y `buscar` (contrato de `docs/plan.md` §3.3):

```js
estado.datos.cartera = {
  mios: [],            // GET /clientes?q= → [{ id, nombre, nit, telefono, correo }]
  disponibles: [],     // GET /clientes/disponibles → [{ id, nombre }]
  resumen: null,       // { clientes, maximo } de GET /reportes/resumen, o null si falló
  q: ''                // texto buscado con el que se pidió "mios" ('' = sin filtro)
};
estado.formularios.alta = {
  valores: { nombre, nit, telefono, correo },
  errores: {},         // 422: { campo: mensaje }
  error: null          // 409: texto que se muestra encima del formulario
};
estado.formularios.buscar = { valores: { q } };
```

- `formularios.alta.error` es un campo **adicional** al contrato `{ valores, errores }`, para el 409 que F2 quiere encima del formulario y no en el aviso general.
- Nada de esto va a `localStorage` (F1).

### 2.2 Peticiones

| Momento | Peticiones |
|---|---|
| `cargar()` | `GET /clientes`, `GET /clientes/disponibles` y `GET /reportes/resumen`, en paralelo. Pone `q: ''` |
| Buscar | `GET /clientes?q=<encodeURIComponent(q recortado)>`; si `q` queda vacío, `GET /clientes` |
| Limpiar | `GET /clientes` y `q: ''` |
| Alta con 201 | `GET /clientes` (búsqueda limpia) y `GET /reportes/resumen` |
| Tomar con 200 | `GET /clientes` (búsqueda limpia), `GET /clientes/disponibles` y `GET /reportes/resumen` |
| Tomar con 409 o 404 | `GET /clientes/disponibles` |

**Contador degradado.** `GET /reportes/resumen` se pide aparte de las listas, dentro de su propio `try/catch`:
- Si falla con `sesionTerminada` o con `status === 0`, el error se vuelve a lanzar (lo atiende `app.js` con `mostrarError`).
- Con cualquier otro error (por ejemplo 404 "Ruta no encontrada." mientras el spec 15 no esté en `main`), se guarda `resumen: null`, **sin aviso**, y la vista muestra "Contador no disponible". Las listas se ven igual.
- La SPA **nunca** calcula el contador con el largo de la lista.

### 2.3 Pantalla

`titulo: 'Pacientes'`. Rejilla de Bootstrap `row g-3`:

**Columna izquierda (`col-12 col-lg-7`)**
1. Encabezado "Mis pacientes" y el contador: **"`clientes` de `maximo` pacientes"** (por ejemplo "3 de 10 pacientes"), o "Contador no disponible" si `resumen` es `null`.
2. Buscador: `<form data-form="buscar" role="search">` con `<input type="search" name="q">` (etiqueta "Buscar por nombre o NIT", valor escapado de `formularios.buscar.valores.q`), botón `type="submit"` "Buscar" y botón `type="button" data-accion="limpiarBusqueda"` "Limpiar" (solo si `q` no está vacío).
3. Tabla dentro de `table-responsive` con las columnas Nombre, NIT y Teléfono. El NIT y el teléfono se muestran tal como los devuelve la API (`"CF"` incluido), escapados.
4. Lista vacía: con `q` no vacío, **"No se encontraron pacientes."**; con `q` vacío, "Aún no tiene pacientes en su cartera.".

**Columna derecha (`col-12 col-lg-5`)**
1. Tarjeta "Agregar paciente" con `<form data-form="alta" novalidate>` a una columna:
   - `nombre` (texto), `nit` (texto, con la ayuda "Con su dígito verificador, o CF"), `telefono` (`type="tel"`, `inputmode="tel"`) y `correo` (`type="email"`, con la etiqueta "Correo (opcional)").
   - Cada campo con `claseError` y `errorCampo`, y su `value` escapado desde `formularios.alta.valores`.
   - Si `formularios.alta.error` existe, un `alert alert-danger` **encima del formulario**, con el texto escapado.
   - Botón `type="submit"` "Agregar paciente".
2. Tarjeta "Pacientes disponibles": lista con el nombre escapado y, en cada fila, `<form data-form="tomar">` con `<input type="hidden" name="id">` y un botón `type="submit"` "Tomar". Lista vacía: "No hay pacientes disponibles.".

A 375 px las dos columnas se apilan en este orden: contador, buscador, Mis pacientes, Agregar y Disponibles.

### 2.4 Formularios y acciones

**`formularios.buscar(datos)`**
1. `q = datos.q.trim()`. `cambiar({ formularios: { ...estado.formularios, buscar: { valores: { q } } } })` **antes** de pedir.
2. Pide `mios` según 2.2 y guarda `datos.cartera = { ...cartera, mios, q }`.

**`acciones.limpiarBusqueda()`**: borra `formularios.buscar`, pide `GET /clientes` y guarda `q: ''`.

**`formularios.alta(datos)`**
1. Guarda `valores` (los cuatro campos tal como se escribieron), `errores: {}` y `error: null` **antes** de pedir.
2. `pedir('POST', '/clientes', { nombre, nit, telefono, correo })`. Los cuatro se envían como texto; un correo vacío viaja como `""` y la API lo guarda como `null` (spec 06).
3. **201**: borra `formularios.alta` y `formularios.buscar`, recarga según 2.2 y pone `aviso: { tipo: 'success', texto: 'Se agregó a ' + r.nombre + ' a su cartera.' }`. El texto **no** se escapa aquí: el layout del spec 10 ya lo pasa por `escapar()`.
4. **422**: `errores: e.errores` y se conservan los `valores`.
5. **409**: `error: e.message` y se conservan los `valores`.
6. Otro error (0, 500, sesión terminada): `mostrarError(e)`; los `valores` siguen guardados.

**`formularios.tomar(datos)`**
1. `id = datos.id`, y el nombre se toma de `cartera.disponibles` para el aviso.
2. `pedir('POST', '/clientes/' + id + '/tomar')`.
3. **200**: borra `formularios.buscar`, recarga según 2.2 y pone `aviso: { tipo: 'success', texto: 'Se agregó a ' + r.nombre + ' a su cartera.' }`.
4. **409 o 404**: `mostrarError(e)` (se ve "Este paciente ya no está disponible." o "Su cartera ya tiene 10 pacientes, el máximo permitido.") y vuelve a pedir `GET /clientes/disponibles`.
5. Otro error: `mostrarError(e)`.

"Tomar" es un `submit` (y no un `data-accion`) para que la base del spec 10 lo desactive mientras `estado.cargando > 0` y no se envíe dos veces.

---

## 3. Plan de implementación

Cada paso deja la SPA abriéndose en Live Server (`http://127.0.0.1:5500`) sin errores en la consola. Requiere la API con las rutas de clientes (rama de B fusionada, o la rama `spec-06-api-clientes` corriendo en local para probar).

1. **Carga y lista.** `cargar()` con las tres peticiones y el contador degradado; `render` con las dos columnas, la tabla de Mis pacientes, el contador y la lista de disponibles (sin formularios todavía). Comprobar CA1 y CA9.
2. **Búsqueda.** Formulario `buscar`, botón "Limpiar" y los dos textos de lista vacía. Comprobar CA2.
3. **Alta.** Formulario `alta` con 201, 422 y 409, y el aviso de éxito. Comprobar CA3, CA4 y CA5.
4. **Tomar.** Formularios `tomar` con 200, 409 y 404. Comprobar CA6, CA7 y CA8.
5. **Cierre.** `git grep -n "addEventListener\|onclick" frontend/js`, revisar cada `${…}` de `cartera.js` (todo dato de la API pasa por `escapar()`), las pruebas de F6 sobre `#/cartera` (CA10 a CA14) y anotar F2 en `docs/verificacion.md`.

---

## 4. Criterios de aceptación

Semilla: `php artisan migrate:fresh --seeder=VerificacionSeeder`, API encendida (`cd api && npm run dev`) y la SPA en `http://127.0.0.1:5500`. Los CA del contador (CA1, CA3, CA6, CA8) se comprueban con el spec 15 en `main`; antes de eso se comprueba CA9.

**F2 · Cartera**
- **CA1 · Mis pacientes.** Dado Valeria, cuando abre `#/cartera`, entonces ve sus 3 pacientes ordenados por nombre (Comercial El Quetzal, Ferretería Xela y María Coyoy, esta con NIT "CF") y el contador "3 de 10 pacientes".
- **CA2 · Búsqueda en el servidor.** Dado la cartera de Valeria, cuando busca `quetzal` y luego `4521-8`, entonces la pestaña Red muestra `GET /clientes?q=quetzal` y `GET /clientes?q=4521-8`, y las dos veces la lista muestra solo Comercial El Quetzal. Cuando busca `Farmacia`, se ve "No se encontraron pacientes.". Cuando pulsa "Limpiar", vuelven los 3.
- **CA3 · Alta correcta.** Dado el formulario de alta con nombre "Ana Pérez", NIT `cf`, teléfono `+502 5555-0001` y correo vacío, cuando se envía, entonces se ve el aviso verde "Se agregó a Ana Pérez a su cartera.", el paciente aparece en la lista con NIT "CF" y teléfono `55550001`, el contador pasa a "4 de 10 pacientes" y el formulario queda vacío. Si había una búsqueda activa, se limpia.
- **CA4 · 422 junto a cada campo.** Dado el formulario de alta con nombre "Prueba", NIT `4521817-1` y teléfono `5555123`, cuando se envía, entonces aparece un mensaje en español bajo el NIT y otro bajo el teléfono (`is-invalid` e `invalid-feedback`), y el nombre, el NIT y el teléfono escritos siguen en sus campos.
- **CA5 · 409 encima del formulario.** Dado el formulario de alta con el NIT `4521817-K` (el de Comercial El Quetzal), cuando se envía, entonces se ve "Ya existe un paciente con ese NIT." encima del formulario y lo escrito se conserva. Con el teléfono `5555-1234`, se ve "Ya existe un paciente con ese teléfono.".
- **CA6 · Tomar.** Dado Valeria y un paciente disponible, cuando pulsa "Tomar", entonces se ve el aviso verde, el paciente aparece en Mis pacientes, sale de disponibles y el contador sube en 1.
- **CA7 · Tomado por otro asesor.** Dado Valeria en una ventana normal y Diego en una ventana privada, los dos en `#/cartera`, cuando Valeria toma "Agencia Cuatro Caminos" y después Diego pulsa "Tomar" en ese mismo paciente, entonces Diego ve "Este paciente ya no está disponible." y el paciente desaparece de su lista de disponibles.
- **CA8 · Cartera llena.** Dado Carlos (9 pacientes), cuando toma un disponible, entonces el contador dice "10 de 10 pacientes". Cuando intenta tomar otro, ve "Su cartera ya tiene 10 pacientes, el máximo permitido." y la lista de disponibles se vuelve a pedir. Cuando intenta un alta con datos válidos, ve el mismo mensaje encima del formulario y lo escrito se conserva.
- **CA9 · Contador degradado.** Dado que `GET /reportes/resumen` no responde 200 (por ejemplo 404 mientras el spec 15 no esté en `main`), cuando Valeria abre `#/cartera`, entonces ve sus pacientes y los disponibles, el texto "Contador no disponible" y ningún aviso de error.

**F6 sobre `#/cartera`**
- **CA10 · Escapar.** Dado el alta de un paciente con nombre `<img src=x onerror=alert(1)>` y datos válidos, cuando aparece en la lista y en el aviso de éxito, entonces se ve el texto literal y no se ejecuta nada. Igual con `<script>alert(1)</script>` en la búsqueda (el valor vuelve escapado al campo).
- **CA11 · Delegación.** Dado el código, cuando se ejecuta `git grep -n "addEventListener\|onclick" frontend/js`, entonces el resultado es el mismo que en el spec 10 (nada en `cartera.js`), y cada botón o formulario de `cartera.js` usa `data-accion` o `data-form`.
- **CA12 · Indicador de carga.** Dado la red en "Slow 3G", cuando se abre `#/cartera` o se pulsa "Tomar" o "Agregar paciente", entonces se ve el spinner y los botones de envío están desactivados hasta que llega la respuesta.
- **CA13 · API apagada.** Dado la API detenida, cuando se abre `#/cartera`, entonces se ve "No se pudo conectar con el servidor. Verifique que la API esté encendida." con "Reintentar", y no una pantalla en blanco. Al encender la API y pulsar "Reintentar", la vista carga.
- **CA14 · 375 px.** Dado el modo teléfono a 375 px, cuando se abre `#/cartera`, entonces las columnas se apilan (contador, buscador, Mis pacientes, Agregar y Disponibles) y no hay desplazamiento horizontal.
- **CA15 · Textos.** Todo lo visible en `#/cartera` está en español, trata de usted y dice "paciente", nunca "cliente". El título de la pestaña es "Pacientes · Dientecitos Felices".
- **CA16 · Solo sesión en `localStorage`.** Después de buscar, agregar y tomar, `localStorage` solo tiene la clave `sesion`.

---

## 5. Decisiones tomadas y descartadas

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| Si `GET /reportes/resumen` falla (salvo sesión terminada o API apagada), las listas cargan igual y se ve "Contador no disponible", sin aviso | Contar el largo de la lista mientras tanto; esperar a que exista el spec 15 | Decidido por la integrante. F2 manda que el contador salga del resumen; contar en el navegador lo contradice y además falla con una búsqueda activa. No bloquear permite avanzar antes del spec 15 |
| Dos columnas (`col-lg-7` y `col-lg-5`) que se apilan a 375 px | Todo apilado; pestañas | Decidido por la integrante. Se ven la lista y el alta a la vez en escritorio, y no se agrega estado de pestaña |
| Tras crear o tomar, la búsqueda se limpia y se pide `GET /clientes` sin filtro. Botón "Limpiar" junto al buscador | Conservar la búsqueda | Decidido por la integrante. Así el paciente nuevo siempre se ve en la lista, junto con el contador que subió |
| Aviso verde "Se agregó a <nombre> a su cartera." al crear o tomar | Sin aviso, como describe F2 | Decidido por la integrante. Confirma la acción a 375 px, donde la lista puede quedar fuera de la pantalla |
| El 409 del alta va en `formularios.alta.error`, encima del formulario; el 409 de "Tomar" va al aviso general | Todo al aviso general | F2 pide el 409 del alta "encima del formulario"; para "Tomar" solo pide mostrar el mensaje |
| El texto del aviso de éxito se arma sin `escapar()` | Escaparlo en la vista | El layout del spec 10 ya escapa `aviso.texto`; escapar dos veces mostraría `&lt;` en pantalla |
| Formulario con `novalidate` y sin `required` ni `pattern` | Validación HTML5 en el navegador | El servidor es la única fuente de los mensajes (L7 y F2); `type="email"` sin `novalidate` impediría probar el 422 del correo |
| "Tomar" es un `submit` dentro de un `form data-form="tomar"` por fila | Botón con `data-accion="tomar"` y `data-id` | La base del spec 10 desactiva los `submit` mientras hay peticiones en curso; así no se envía dos veces |
| NIT y teléfono se muestran tal como los devuelve la API | Darles formato (`4521817-K`, `5555-1234`) en la SPA | Evita una segunda normalización en el navegador que podría no coincidir con la sección 10 |
| `cargar()` pide las tres cosas en paralelo | En serie | Menos espera; el resumen tiene su propio `try/catch` y no tumba a las listas |

---

## 6. Riesgos

- **Dependencias de B sin fusionar.** Las rutas de clientes están en `origin/spec-06-api-clientes` y el resumen (spec 15) no tiene spec todavía. Si cambian los mensajes o las formas de respuesta, este spec y sus CA se actualizan. CA1, CA3, CA6 y CA8 quedan pendientes hasta que el spec 15 esté en `main`.
- **Redibujar borra lo escrito.** Cada `cambiar()` reemplaza el `innerHTML`; por eso `alta` y `buscar` guardan sus `valores` **antes** de pedir (riesgo ya anotado en el spec 10).
- **Nombre para el aviso.** Si el disponible ya no está en `cartera.disponibles` al responder, el aviso usa `r.nombre` de la respuesta 200, que siempre viene.
- **CORS y origen.** Si la SPA se abre como `localhost:5500`, se ve como API apagada (S6), no como error de la cartera.
- **CA7 necesita dos sesiones.** `localStorage` es por origen; se usa una ventana privada (o otro navegador) para Diego.
