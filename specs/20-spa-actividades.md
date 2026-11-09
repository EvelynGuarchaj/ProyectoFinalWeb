# 20 · Actividades en la SPA

**Estado:** Aprobado
**Depende de:** 10-spa-base (`estado.js`, `api.js`, `util.js` con `fechaGT`, `aISO`, `escapar`, `errorCampo`, `claseError` y `mostrarError`, `textos.js` con `TIPOS`, `app.js` con delegación, indicador de carga y aviso), 08-api-actividades (B: `GET /actividades?estado=`, `POST /clientes/:id/actividades` y `PATCH /actividades/:id`; hoy en la rama `origin/spec-08-api-actividades`, todavía no en `main`; su spec todavía no existe en `specs/`), 06-api-clientes (B: `GET /clientes`; hoy en `origin/spec-06-api-clientes`, todavía no en `main`; su spec todavía no existe en `specs/`)
**Fecha:** 2026-11-08
**Dueña:** A
**Rama:** `spec-20-spa-actividades`
**Objetivo:** reemplazar la vista provisional `#/actividades` por la lista de actividades del asesor (pendientes y completadas), con el alta de actividades y el botón para completarlas, con las fechas en hora de Guatemala.

**Requisitos que cumple:** F4 completo de `docs/especificacion.md`, y sobre `#/actividades` las comprobaciones de F6 que el spec 10 hizo con la vista provisional (delegación, indicador de carga, API apagada, `escapar()` (S7) y 375 px). Ayuda a probar en la SPA la regla propia (R-P2): una cita completada desde aquí es la que habilita el presupuesto.

---

## 1. Alcance

**Incluye**
- `frontend/js/vistas/actividades.js`: reemplaza la vista provisional del spec 10 con su contenido real. **Es el único archivo de código que cambia.**

**No incluye**
- Cambios en `util.js`, `api.js`, `estado.js`, `app.js`, `textos.js` o `css/estilos.css` (spec 10). Si hiciera falta uno, se avisa antes y no se hace dentro de este spec.
- Cambiar `fechaGT()` para que dé `01/12/2026 10:00` (ver Decisiones, D1).
- Corregir el criterio de F4 en `docs/especificacion.md` (`01/12/2026 10:00` → `1 dic 2026, 10:00`) y anotar las filas de F4 en `docs/verificacion.md`: van en un PR de documentación aparte.
- Desmarcar una actividad completada (`{ completada: false }`), editarla o borrarla: F4 no lo pide.
- Filtrar por paciente o por fecha, o paginar.
- Registrar actividades desde `#/cartera` o desde `#/plan/:id`.
- Calcular `vencida` con el reloj del navegador: la SPA usa el campo de la API.
- Cambios en la API (specs 06 y 08, de B) y en `embudo.js` y `plan.js` (B).
- Pruebas automáticas de la SPA: se verifica en el navegador (sección 4).

---

## 2. Modelo de datos

No hay tablas ni migraciones. Se define la parte del estado que usa la vista, las peticiones y la forma de la pantalla.

### 2.1 Estado de la vista

La vista usa solo `estado.datos.actividades` y el formulario `nuevaActividad` (contrato de `docs/plan.md` §3.3):

```js
estado.datos.actividades = {
  pestana: 'pendiente',  // 'pendiente' | 'completada'; si falta, 'pendiente'
  lista: [],             // GET /actividades?estado=<pestana> → [{ id, cliente: { id, nombre }, tipo, descripcion, vence_en, completada, vencida }]
  clientes: []           // GET /clientes → [{ id, nombre, … }] para el select de pacientes
};
estado.formularios.nuevaActividad = {
  valores: { cliente_id, tipo, descripcion, vence_en },  // vence_en tal como lo da el datetime-local: '2026-12-01T10:00' o ''
  errores: {}                                            // 422: { campo: mensaje }; también cliente_id (local)
};
```

- `pestana` vive solo en memoria: se recuerda al salir y volver a `#/actividades` en la misma sesión; al recargar la página o cerrar sesión vuelve a `'pendiente'` (Cerrar sesión ya borra `estado.datos`).
- Nada de esto va a `localStorage` (F1).

### 2.2 Peticiones

| Momento | Peticiones | Después |
|---|---|---|
| `cargar()` | `GET /actividades?estado=<pestana>` y `GET /clientes`, en paralelo | Guarda `lista` y `clientes`. Si existe `formularios.nuevaActividad`, conserva sus `valores` y le borra los `errores` |
| Pestaña (`data-accion="pestana"`) | `GET /actividades?estado=<data-valor>` | Guarda `pestana` y `lista`. Si `data-valor` no es `pendiente` ni `completada`, no hace nada |
| Nueva actividad, 201 | `GET /actividades?estado=pendiente` | Borra `formularios.nuevaActividad`, pone `pestana: 'pendiente'` y el aviso `success` **"Actividad registrada."** |
| Nueva actividad, 422 | — | `errores` del cuerpo junto a cada campo; los `valores` ya estaban guardados |
| Nueva actividad, 404 | `GET /actividades?estado=<pestana>` y `GET /clientes` | Aviso `danger` con el `error` de la API; conserva los `valores` salvo `cliente_id`, que se vacía |
| Completar, 200 | `GET /actividades?estado=<pestana>` | Aviso `success` **"Actividad completada."** |
| Completar, 404 | `GET /actividades?estado=<pestana>` y `GET /clientes` | Aviso `danger` con el `error` de la API |

- Cualquier otro error (0, 401, 400, 409, 500) se relanza y lo atiende `app.js` con `mostrarError` (el status 0 trae "Reintentar", que vuelve a llamar a `cargar()`).
- El aviso de éxito se pone **después** de la recarga, para que el `cambiar()` de la recarga no lo pise.

**Cuerpo del POST.** `POST /clientes/${encodeURIComponent(cliente_id)}/actividades` con `{ tipo, descripcion }`, y `vence_en: aISO(valores.vence_en)` **solo si** el campo de fecha no está vacío (si está vacío, la clave no se envía). La descripción se envía tal como se escribió: el recorte y el límite de 500 los valida la API.

**Paciente vacío.** Si `cliente_id` llega vacío (el select tiene `required`, pero se puede quitar en las herramientas del navegador), la vista **no** llama a la API: guarda los `valores` con `errores: { cliente_id: 'Elija un paciente.' }`. Es la única validación en el navegador, porque el paciente va en la URL y la API no puede devolver un 422 para él.

**Orden de los pasos al enviar.** 1) `cambiar()` con los `valores` y `errores: {}` **antes** de `pedir` (riesgo del spec 10: cada `cambiar` redibuja); 2) `pedir`; 3) según el código, la fila de la tabla.

### 2.3 Pantalla

`titulo: 'Actividades'`. Rejilla de Bootstrap `row g-3`; en teléfono se apilan, primero la lista.

**Columna izquierda (`col-12 col-lg-7`): la lista**
1. Encabezado "Mis actividades".
2. Pestañas `nav nav-tabs` con dos botones `type="button"`: **"Pendientes"** (`data-accion="pestana" data-valor="pendiente"`) y **"Completadas"** (`data-accion="pestana" data-valor="completada"`). La activa lleva `active` y `aria-current="page"`.
3. Lista `list-group`. Cada actividad es un `list-group-item` con:
   - el nombre del paciente (`escapar(cliente.nombre)`, en negrita, con `.texto-largo`),
   - el tipo en pantalla con `TIPOS[tipo]` (Llamada, **Cita**, Tarea) en un `badge text-bg-secondary`,
   - la descripción (`escapar(descripcion)`, con `.texto-largo`),
   - la fecha: `"Vence: " + fechaGT(vence_en)`, o **"Sin fecha"** si `vence_en` es `null`,
   - si `vencida === true`: el `list-group-item` lleva además `vencida border-danger`, la fecha lleva `text-danger` y aparece el `badge text-bg-danger` **"Vencida"**,
   - en la pestaña Pendientes: el botón `type="button"` **"Marcar como completada"** (`btn btn-sm btn-outline-success`, `data-accion="completar" data-id="${escapar(id)}"`).
4. Lista vacía: "No tiene actividades pendientes." o "No tiene actividades completadas.", según la pestaña.

**Columna derecha (`col-12 col-lg-5`): tarjeta "Nueva actividad"**
- Si `clientes` está vacío: en lugar del formulario, el texto "No tiene pacientes en su cartera. Tome uno en Pacientes para registrar actividades." con un enlace `<a href="#/cartera">` a Pacientes.
- Si no, `<form data-form="nuevaActividad">` (sin `novalidate`) con:
  - **Paciente**: `<select name="cliente_id" required>` con la primera opción `<option value="">Elija un paciente</option>` y una opción por cliente (`value` y texto escapados). Queda `selected` la que coincide con `valores.cliente_id`.
  - **Tipo**: `<select name="tipo">` con las tres opciones de `TIPOS` en ese orden (`llamada` → Llamada, `reunion` → Cita, `tarea` → Tarea). Por omisión `llamada`; queda `selected` la de `valores.tipo`.
  - **Descripción**: `<textarea name="descripcion" rows="3">` sin `required` (el servidor es el árbitro, y así el 422 se puede probar directo), con el texto escapado de `valores.descripcion`.
  - **Fecha y hora (opcional)**: `<input type="datetime-local" name="vence_en">` con `value` escapado de `valores.vence_en`, y el texto de ayuda "Hora de Guatemala. Déjela vacía si no tiene fecha."
  - Cada control lleva `claseError(errores, campo)` y debajo `errorCampo(errores, campo)` con su nombre del contrato: `cliente_id`, `tipo`, `descripcion`, `vence_en`.
  - Botón `type="submit"` **"Registrar actividad"** (`app.js` lo desactiva mientras `cargando > 0`).

Los botones "Marcar como completada" y las pestañas son `type="button"`; no se desactivan durante la carga (el spec 10 solo desactiva los `submit`).

### 2.4 Lo que sale de la API y entra en `innerHTML`

`cliente.nombre`, `descripcion`, `id`, el `id` y el `nombre` de cada cliente del select, los `valores` del formulario y los mensajes de error. Todo pasa por `escapar()` (directo o vía `errorCampo`). `tipo` se muestra solo a través de `TIPOS[tipo]`; si llegara un tipo desconocido se muestra `escapar(tipo)`. `fechaGT()` devuelve texto de `Intl`, que también se escapa por uniformidad.

---

## 3. Plan de implementación

Cada paso deja la SPA abriéndose en Live Server (`http://127.0.0.1:5500`) sin errores en la consola. Para probar hace falta la API de la rama de B con las rutas de actividades y clientes (`origin/spec-08-api-actividades`, que ya incluye las de clientes) o `main` cuando B las fusione.

1. **Lista.** `cargar()` con `GET /actividades?estado=pendiente` y `GET /clientes`; `render()` con la columna izquierda: pestañas (solo dibujo), filas con paciente, tipo, descripción, fecha o "Sin fecha", vencidas en rojo con "Vencida" y lista vacía. Comprobación: CA1 y CA3.
2. **Pestañas.** Acción `pestana` y `pestana` guardada en `estado.datos.actividades`; `cargar()` usa la pestaña guardada. El botón "Marcar como completada" solo en Pendientes (todavía sin acción). Comprobación: CA2.
3. **Completar.** Acción `completar` con `PATCH /actividades/:id`, recarga, aviso y 404. Comprobación: CA8 y CA9.
4. **Formulario.** Columna derecha con el formulario, sus valores y errores, y el mensaje sin pacientes. Sin envío todavía. Comprobación: CA10.
5. **Envío.** `formularios.nuevaActividad`: paciente vacío, cuerpo con `aISO` solo si hay fecha, 201, 422 y 404. Comprobación: CA4 a CA7.
6. **Cierre.** `git grep -n "addEventListener\|onclick" frontend/js`, revisión de cada `${…}` del `render` contra la sección 2.4, `git diff main --stat` (solo `actividades.js` y este spec) y las comprobaciones de F6 (CA11 a CA15).

---

## 4. Criterios de aceptación

Semilla: `php artisan migrate:fresh --seeder=VerificacionSeeder`, API encendida (`cd api && npm run dev`) y la SPA en `http://127.0.0.1:5500` con la sesión de Valeria. Las peticiones se ven en la pestaña Red del navegador.

Con esa semilla, Valeria tiene: pendientes "Presentar opciones de soporte" (María Coyoy, reunion, vence 2026-09-01 15:00 UTC, ya pasó) y "Enviar propuesta de capacitación" (Ferretería Xela, tarea, vence 2030-01-15 15:00 UTC); completada "Confirmar fecha de instalación" (Comercial El Quetzal, llamada, 2026-10-08 15:00 UTC).

**F4 · Actividades**
- **CA1 · Pendientes al abrir.** Dado Valeria, cuando abre `#/actividades` por primera vez en la sesión, entonces el navegador pide `GET /actividades?estado=pendiente` y `GET /clientes`, la pestaña "Pendientes" está activa y se ven dos filas en este orden: María Coyoy · **Cita** · "Presentar opciones de soporte" · "Vence: 1 sep 2026, 09:00" en rojo con la etiqueta **"Vencida"**; y Ferretería Xela · Tarea · "Enviar propuesta de capacitación" · "Vence: 15 ene 2030, 09:00" sin rojo ni "Vencida". Las dos tienen el botón "Marcar como completada". No aparece la actividad de Farmacia Los Altos (es de Diego).
- **CA2 · Completadas.** Dado la vista abierta, cuando se pulsa "Completadas", entonces el navegador pide `GET /actividades?estado=completada`, se ve Comercial El Quetzal · Llamada · "Confirmar fecha de instalación" · "Vence: 8 oct 2026, 09:00", ninguna fila lleva "Vencida" ni el botón "Marcar como completada". Al ir a `#/cartera` y volver a `#/actividades`, la pestaña activa sigue siendo "Completadas"; al recargar la página, vuelve a "Pendientes".
- **CA3 · Sin fecha.** Dado una actividad pendiente creada sin fecha, cuando se ve en la lista, entonces muestra "Sin fecha", sin rojo, y aparece después de las que tienen fecha (orden de la API).
- **CA4 · Crear una cita con fecha.** Dado el formulario, cuando Valeria elige Ferretería Xela, el tipo "Cita", la descripción "Evaluación inicial" y la fecha 1 de diciembre de 2026 a las 10:00, y pulsa "Registrar actividad", entonces el cuerpo de la petición a `POST /clientes/<id de Ferretería Xela>/actividades` es `{"tipo":"reunion","descripcion":"Evaluación inicial","vence_en":"2026-12-01T10:00:00-06:00"}`, la API responde 201, el formulario queda vacío, la pestaña activa es "Pendientes", se ve el aviso "Actividad registrada." y la nueva fila muestra "Cita" y "Vence: 1 dic 2026, 10:00" (formato de `fechaGT`, ver D1). En la base, `actividades.tipo = 'reunion'` y `vence_en = '2026-12-01 16:00:00'`.
- **CA5 · Otra zona horaria.** Dado la computadora (o el navegador, con "Sensores → Ubicación/zona horaria" de las herramientas de Chrome) en `Europe/Madrid`, cuando se crea la misma actividad de CA4, entonces se envía el mismo `"vence_en":"2026-12-01T10:00:00-06:00"` y la fila muestra la misma "Vence: 1 dic 2026, 10:00".
- **CA6 · Sin fecha no envía `vence_en`.** Dado el formulario con paciente, tipo y descripción y la fecha vacía, cuando se envía, entonces el cuerpo no contiene la clave `vence_en` y la API responde 201.
- **CA7 · 422 junto al campo.** Dado el formulario con paciente María Coyoy, tipo "Tarea", la fecha 1 de diciembre de 2026 a las 10:00 y sin descripción, cuando se envía, entonces aparece "Escriba la descripción (máximo 500 caracteres)." bajo la descripción con `is-invalid`, y el paciente, el tipo y la fecha siguen elegidos. Dado el select de paciente sin `required` (quitado en las herramientas del navegador) y en "Elija un paciente", cuando se envía, entonces no sale ninguna petición POST y aparece "Elija un paciente." bajo el select, con lo demás conservado.
- **CA8 · Completar.** Dado "Enviar propuesta de capacitación" en Pendientes, cuando Valeria pulsa "Marcar como completada", entonces el navegador envía `PATCH /actividades/<id>` con el cuerpo `{"completada":true}`, se ve el aviso "Actividad completada.", la fila ya no está en "Pendientes" y aparece en "Completadas".
- **CA9 · 404 tras reasignar.** Dado `#/actividades` abierto por Valeria, cuando Sergio reasigna María Coyoy a Diego en el panel y Valeria pulsa "Marcar como completada" en "Presentar opciones de soporte", entonces se ve el aviso "No se encontró el recurso.", la lista se recarga sin esa fila y María Coyoy ya no está en el select de pacientes. Lo mismo al registrar una actividad para un paciente reasignado: aviso, select actualizado sin paciente elegido, y la descripción, el tipo y la fecha se conservan.
- **CA10 · Sin pacientes.** Dado un asesor sin pacientes (por ejemplo, Valeria tras liberar a sus 3 pacientes en el panel), cuando abre `#/actividades`, entonces en lugar del formulario se ve "No tiene pacientes en su cartera. Tome uno en Pacientes para registrar actividades." con el enlace a `#/cartera`, y las pestañas muestran "No tiene actividades pendientes." / "No tiene actividades completadas.".

**F6 · Calidad de la interfaz sobre `#/actividades`**
- **CA11 · Delegación.** Dado el código, cuando se ejecuta `git grep -n "addEventListener\|onclick" frontend/js`, entonces el resultado es el mismo que en el spec 10 (solo `app.js`); `actividades.js` no aparece.
- **CA12 · Indicador de carga.** Dado la red en "Slow 3G", cuando se envía el formulario, entonces se ve el spinner y el botón "Registrar actividad" está desactivado hasta que llega la respuesta, y lo escrito no se borra mientras tanto.
- **CA13 · API apagada.** Dado la API detenida, cuando se abre `#/actividades` o se envía el formulario, entonces se ve "No se pudo conectar con el servidor. Verifique que la API esté encendida." con "Reintentar", y no una pantalla en blanco; lo escrito en el formulario se conserva. Al encender la API y pulsar "Reintentar", la lista aparece.
- **CA14 · Escapar.** Dado una actividad creada con la descripción `<img src=x onerror=alert(1)>` y un paciente con el nombre `<script>alert(1)</script>` (creado en el panel), cuando se ven en la lista y en el select, entonces aparecen como texto literal y no se ejecuta nada.
- **CA15 · 375 px.** Dado el modo teléfono a 375 px, cuando se abre `#/actividades` con una descripción larga sin espacios, entonces no hay desplazamiento horizontal y el formulario queda debajo de la lista.
- **CA16 · Textos.** Todo lo visible está en español y trata de usted; el tipo `reunion` nunca aparece en pantalla (siempre "Cita"); el título de la pestaña es "Actividades · Dientecitos Felices".
- **CA17 · Un solo archivo.** `git diff main --stat` muestra solo `frontend/js/vistas/actividades.js` y `specs/20-spa-actividades.md`.

---

## 5. Decisiones tomadas y descartadas

| # | Decisión | Alternativa descartada | Por qué |
|---|---|---|---|
| D1 | Las fechas se muestran con `fechaGT()`: "1 dic 2026, 10:00". El criterio de F4 que dice "01/12/2026 10:00" se corrige en un PR de documentación aparte | Un formato propio `dd/mm/aaaa hh:mm` en `actividades.js`; cambiar `fechaGT()` en `util.js` | Decidido por la integrante: mismo formato en todas las vistas y sin tocar `util.js` (spec 10, compartido con B). **Hasta que se fusione ese PR, la especificación y la SPA no coinciden en este texto**; lo que importa de F4 (hora de Guatemala, mismo resultado en cualquier zona) sí se cumple |
| D2 | Solo cambia `js/vistas/actividades.js` | Ajustar `estilos.css` o `textos.js` | Decisión cerrada de la integrante. La clase `.vencida` de `estilos.css` y `TIPOS` de `textos.js` ya existen (spec 10) |
| D3 | Dos columnas como `#/cartera`: lista a la izquierda, formulario a la derecha; en teléfono, primero la lista | Formulario arriba o abajo a todo el ancho | Decidido por la integrante: misma forma que el spec 18 |
| D4 | La pestaña se guarda en `estado.datos.actividades.pestana` (memoria) | Pedir siempre Pendientes al entrar; guardarla en `localStorage` | Decisión cerrada de la integrante. `localStorage` solo guarda la sesión. La primera vez de cada sesión la pestaña es Pendientes, como pide F4 |
| D5 | Con 201: formulario vacío, pestaña Pendientes, recarga y aviso "Actividad registrada." | Recargar la pestaña actual sin aviso; conservar paciente y tipo | Decidido por la integrante: la actividad nueva siempre es pendiente, así se ve en cuanto se crea |
| D6 | Sin pacientes en la cartera, mensaje con enlace a `#/cartera` en lugar del formulario | Formulario con el select deshabilitado | Decidido por la integrante: explica qué hacer en vez de mostrar un formulario que no se puede usar |
| D7 | Select de paciente con `required` y, si aun así llega vacío, error local "Elija un paciente." sin llamar a la API | Solo `required` (se iría a `/clientes//actividades` y daría "Ruta no encontrada.") | Decidido por la integrante: el paciente va en la URL y la API no tiene un 422 para él |
| D8 | Descripción sin `required` | `required` o `maxlength="500"` en el navegador | El servidor es el árbitro (como en los specs 10 y 18) y el 422 de F4 se prueba sin quitar atributos |
| D9 | Con 200 al completar, recarga y aviso "Actividad completada." | Solo recargar | Decidido por la integrante: confirma la acción, igual que al crear |
| D10 | Con 404 al crear o completar: aviso con el error de la API y recarga de la lista y de `GET /clientes` | Solo el aviso | Decidido por la integrante: si el coordinador reasignó al paciente, la pantalla queda al día |
| D11 | `vencida` viene de la API | Calcularla con `Date.now()` | F4 lo pide: la hora del navegador puede estar mal |
| D12 | `vence_en` solo se envía si se escribió una fecha | Enviar `vence_en: ''` o `null` | `aISO('')` da `''`, y una cadena vacía la API la rechaza con 422; la clave ausente significa "sin fecha" |
| D13 | Tipo por omisión "Llamada", sin opción vacía | Opción "Elija el tipo" | Siempre hay un tipo válido elegido; el 422 de `tipo` queda para peticiones fuera de la SPA |

---

## 6. Riesgos

| Riesgo | Mitigación |
|---|---|
| Las rutas de B (specs 06 y 08) no están en `main` | Se prueba con la API de `origin/spec-08-api-actividades`. Este spec no se marca Implementado hasta probarlo con `main` |
| Redibujar borra lo escrito | Los `valores` se guardan en `estado.formularios.nuevaActividad` antes de `pedir` (paso 1 del envío, 2.2). CA12 lo comprueba |
| `fechaGT` da "oct" u "oct." según el motor | Los criterios comprueban día, mes, año y hora, no la puntuación exacta (igual que CA12 del spec 10) |
| Un aviso de éxito pisado por la recarga | El aviso se pone después de la recarga (2.2) |
| Especificación y SPA no coinciden en el formato de fecha mientras no se fusione el PR de documentación (D1) | Se anota en el PR de este spec y se abre el PR de docs a la vez |
| Doble clic en "Marcar como completada" | El segundo `PATCH` sobre una actividad ya completada responde 200 igual; no hay efecto visible distinto |

---

## Lo que **no** entra en este spec

- Cambios en `util.js`, `estilos.css`, `textos.js`, `app.js` u otros archivos de la base.
- El PR de documentación (criterio de F4 y filas de `docs/verificacion.md`).
- Desmarcar, editar o borrar actividades; filtros o paginación.
- Registrar actividades desde la cartera o el detalle del plan.
- Cambios en la API y en `embudo.js` y `plan.js` (B).
