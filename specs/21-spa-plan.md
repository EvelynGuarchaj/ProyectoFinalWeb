# 21 · Detalle del plan en la SPA

**Estado:** Implementado
**Depende de:** 10-spa-base (A: `estado.js`, `api.js`, `util.js`, `textos.js` y `app.js` con delegación, indicador de carga y aviso), 11-api-oportunidades (B: detalle, título, etapa y tratamientos del plan; `GET /productos`), 13-api-regla-propia (B: 409 de la cita de evaluación), 19-spa-embudo (B: enlaces `#/plan/<id>` y mismo estilo de vista)
**Fecha:** 2026-11-08
**Dueña:** B
**Rama:** `spec-21-spa-plan`
**Objetivo:** reemplazar la vista provisional `#/plan/:id` por el detalle del plan, donde el asesor edita el título, agrega, cambia y quita tratamientos, mueve el plan de etapa y ve su historial.

**Situación de implementación (2026-11-09):** los seis pasos están implementados y el estado de este spec es Implementado. Se corrigieron en `plan.js` las respuestas tardías y la visualización del detalle de otro ID antes de cargar. Firefox confirmó CA1 y el recorrido de éxito CA4–CA9 (crear, editar título, agregar tratamiento, cambiar cantidad y aceptar por las etapas); CA2 sigue parcial (falta un plan ajeno), CA6 no recorrió quitar, CA11 está simulada y CA14 comprobado automáticamente. Los criterios restantes de navegador siguen según `docs/verificacion.md` §2.4.

La integrante solicitó expresamente actualizar la documentación de B en esta rama, sin cambiar de rama; esto exceptúa para esta revisión la separación documental prevista en el alcance.

**Requisitos que cumple:** F3 de `docs/especificacion.md`, parte del detalle (`#/plan/:id`), y sobre `#/plan/:id` las comprobaciones de F6 (delegación, indicador de carga, API apagada, `escapar()` (S7) y 375 px).

---

## 1. Alcance

**Incluye**
- `frontend/js/vistas/plan.js`: reemplaza la vista provisional del spec 10 con su contenido real. **Es el único archivo que se modifica.**
- Las filas de F3 (detalle) en `docs/verificacion.md` (de B según `docs/reparto.md`) se anotan aparte, en su rama `docs/verificacion`, no dentro de este spec.

**No incluye**
- El tablero `#/embudo` (spec 19).
- Retroceder de etapa, saltar etapas o reabrir un plan cerrado: la sección 5 de la especificación no lo permite.
- Cambiar el paciente de un plan o borrar un plan: el contrato no tiene esas rutas.
- Registrar la cita de evaluación desde el detalle: se hace en `#/actividades` (spec 20, de A).
- Cambios en `app.js`, `api.js`, `estado.js`, `util.js`, `textos.js`, `index.html` o `css/estilos.css` (dueña A). Si hiciera falta uno, se avisa antes y no se hace dentro de este spec.
- Cambios en la API: este spec solo consume endpoints que ya existen.
- Calcular totales, subtotales o probabilidades en el navegador, o validar en el navegador la regla propia o las transiciones: lo decide la API y la SPA muestra su respuesta.
- Pruebas automáticas de la SPA: se verifica en el navegador (sección 4).

---

## 2. Modelo de datos

No hay tablas ni migraciones. Se define la parte del estado que usa la vista y la forma de la pantalla.

### 2.1 Estado de la vista

La vista usa solo `estado.datos.plan` y los formularios `titulo`, `agregar`, `cantidad` y `rechazar` (contrato de `docs/plan.md` §3.3):

```js
estado.datos.plan = null                 // mientras carga
estado.datos.plan = { noEncontrado: true } // GET /oportunidades/:id respondió 404
estado.datos.plan = {
  plan: { id, cliente: { id, nombre }, titulo, etapa, probabilidad, total, fecha_cierre,
          motivo_perdida,
          productos: [{ id, producto_id, nombre, cantidad, precio_unitario, subtotal }],
          historial: [{ etapa_anterior, etapa_nueva, usuario, fecha }] },
  productos: [{ id, nombre, precio, requiere_evaluacion }]   // GET /productos (solo activos)
};

estado.formularios.titulo   = { valores: { titulo }, errores: {} };
estado.formularios.agregar  = { valores: { producto_id, cantidad }, errores: {} };
estado.formularios.cantidad = { lineaId, valores: { cantidad }, errores: {} }; // una línea a la vez
estado.formularios.rechazar = { valores: { motivo_perdida }, errores: {} };    // existe = formulario visible
```

- Nada de esto va a `localStorage` (F1).
- `cantidad` guarda el `lineaId` de la línea que se envió: solo esa línea muestra lo escrito y su error; las demás muestran la `cantidad` de la API.
- `rechazar` sirve también para saber si el formulario del motivo está abierto: si existe, se dibuja.

### 2.2 Peticiones

| Momento | Peticiones |
|---|---|
| `cargar(params)` | Primero, con un solo `cambiar()`: `datos.plan = null` y se borran los formularios `titulo`, `agregar`, `cantidad` y `rechazar`. Después `GET /oportunidades/:id` y `GET /productos` en paralelo (`Promise.all`) y se guarda `{ plan, productos }` con un solo `cambiar()` |
| Editar título | `PATCH /oportunidades/:id` con `{ titulo }` → 200 con el detalle |
| Agregar tratamiento | `POST /oportunidades/:id/productos` con `{ producto_id, cantidad }` → 201 con el detalle |
| Cambiar cantidad | `PATCH /oportunidades/:id/productos/:lineaId` con `{ cantidad }` → 200 con el detalle |
| Quitar tratamiento | `DELETE /oportunidades/:id/productos/:lineaId` → 204; después `GET /oportunidades/:id` para tener el detalle nuevo |
| Cambiar de etapa | `PATCH /oportunidades/:id/etapa` con `{ etapa }`, o con `{ etapa: 'perdida', motivo_perdida }` → 200 con el detalle |

- El `:id` sale de `estado.params.id` y va con `encodeURIComponent`.
- Tras un 200 o 201, `datos.plan.plan` se reemplaza por el detalle que devolvió la API; `productos` se conserva.
- Si `GET /oportunidades/:id` responde **404** en `cargar()`, se guarda `{ noEncontrado: true }` y no se muestra aviso. Cualquier otro error de `cargar()` se lanza y lo atiende `app.js` con `mostrarError` (aviso con "Reintentar" si la API está apagada).
- Los ids y cantidades viajan como números JSON: `producto_id` como `Number(...)` o `null` si el `<select>` quedó en "Elija un tratamiento"; `cantidad` como `Number(...)` (vacío → `0`, texto → `NaN` → `null` en JSON; la API responde 422). `titulo` y `motivo_perdida` viajan como texto, sin recortar (los valida la API).

### 2.3 Pantalla

`titulo: 'Detalle del plan'` (pestaña: "Detalle del plan · Dientecitos Felices"). De arriba hacia abajo:

**0. Estados especiales**
- `datos.plan === null`: `''` (el indicador de carga de la base ya se ve).
- `noEncontrado`: "No se encontró el plan." y un enlace "Volver a los planes" a `#/embudo`.

**1. Encabezado** (tarjeta)
- Título del plan (`h1`, escapado, `texto-largo`).
- Paciente: `cliente.nombre`, escapado.
- Etapa: `badge` con `ETAPAS[etapa]`.
- Probabilidad: `escapar(probabilidad)` + `" %"`. Total: `quetzales(total)`.
- Si está cerrado: "Cerrado el `fechaGT(fecha_cierre)`" y, si es `perdida`, "Motivo: `motivo_perdida`", escapado.
- Si está abierto, debajo, el formulario `<form data-form="titulo" novalidate>`: `<input name="titulo">` (etiqueta "Título del plan") con `value` escapado de `formularios.titulo.valores.titulo` o, si no hay formulario guardado, de `plan.titulo`; `claseError`/`errorCampo` en `titulo`; botón submit "Guardar título".

**2. Tratamientos** (tarjeta, encabezado "Tratamientos")
- `list-group` con un `list-group-item` por línea de `plan.productos`, en el orden de la API:
  - Nombre (escapado, `texto-largo`).
  - **Cerrado:** "`quetzales(precio_unitario)` × `escapar(cantidad)` = `quetzales(subtotal)`".
  - **Abierto:** "Precio guardado: `quetzales(precio_unitario)`", "Subtotal: `quetzales(subtotal)`", un `<form data-form="cantidad" novalidate>` con `<input type="hidden" name="lineaId" value="<id de la línea>">`, `<input type="number" name="cantidad" min="1" max="999" step="1">` (etiqueta "Cantidad", `value` de `formularios.cantidad.valores.cantidad` si `formularios.cantidad.lineaId` es esta línea, o de la `cantidad` de la API), `claseError`/`errorCampo` solo en esa línea, botón submit "Cambiar cantidad"; y el botón `type="button"` "Quitar" con `data-accion="quitar" data-id="<id de la línea>"`.
- Sin líneas: "Este plan aún no tiene tratamientos."
- Al pie de la tarjeta: "Total: `quetzales(total)`".
- **Abierto:** debajo, el formulario "Agregar tratamiento" `<form data-form="agregar" novalidate>` en una fila `row g-2` que a 375 px se apila:
  - `<select name="producto_id">` (etiqueta "Tratamiento") con la primera opción `value=""` "Elija un tratamiento" y una opción por cada producto de `productos` cuyo `id` **no** está en `plan.productos[].producto_id`. Texto: nombre escapado + " · " + `quetzales(precio)` y, si `requiere_evaluacion`, " · Requiere evaluación". Queda `selected` la que coincide con `formularios.agregar.valores.producto_id`.
  - `<input type="number" name="cantidad" min="1" max="999" step="1">` (etiqueta "Cantidad"), con `value` de `formularios.agregar.valores.cantidad` o `1` si no hay formulario guardado.
  - `claseError`/`errorCampo` en `producto_id` y `cantidad`; botón submit "Agregar".
  - Si no queda ningún producto por agregar: en lugar del formulario, "Ya agregó todos los tratamientos del catálogo."

**3. Etapa** (tarjeta, encabezado "Cambiar de etapa"; solo si está abierto)
- Cada botón de etapa es un `<form data-form="etapa">` con `<input type="hidden" name="etapa">` y un botón submit, para que la base lo desactive mientras hay peticiones en curso:
  - En `prospecto`, `calificado` y `propuesta`: "Avanzar a `ETAPAS[siguiente]`" con la siguiente de `ABIERTAS`.
  - En `negociacion`: "Marcar como aceptado" (`etapa = ganada`).
- En cualquier etapa abierta, el botón `type="button"` "Marcar como rechazado" con `data-accion="mostrarRechazo"`. Si `formularios.rechazar` existe, en su lugar se ve `<form data-form="rechazar" novalidate>` con `<textarea name="motivo_perdida">` (etiqueta "Motivo del rechazo", contenido escapado de `valores.motivo_perdida`), `claseError`/`errorCampo` en `motivo_perdida`, botón submit "Confirmar rechazo" y botón `type="button"` "Cancelar" con `data-accion="cancelarRechazo"`.
- Los botones se apilan a 375 px (`d-grid gap-2` y `d-md-flex` desde md).

**4. Historial** (tarjeta, encabezado "Historial")
- `list-group` con una fila por registro de `plan.historial`, en el orden de la API: "`ETAPAS[etapa_anterior]` → `ETAPAS[etapa_nueva]`" ("Creado" en lugar de la anterior si es `null`), el `usuario` escapado y `fechaGT(fecha)`.

Los textos largos usan la clase `texto-largo` de la base (spec 10) para que no desborden a 375 px.

### 2.4 Formularios y acciones

Patrón común de los formularios:
1. Guarda `valores` (y `errores: {}`) en `estado.formularios.<nombre>` **antes** de pedir, junto con `aviso: null`, para que el redibujo no borre lo escrito.
2. Pide a la API.
3. **200/201:** reemplaza `datos.plan.plan` por el detalle devuelto, borra ese formulario y pone un aviso `success`.
4. **422:** guarda `errores: e.errores` y conserva los `valores`.
5. **409 y cualquier otro error** (0, 404, 500, sesión terminada): `mostrarError(e)`, que pone `e.message` como aviso `danger` (el 409 trae el mensaje de la API); los `valores` siguen guardados.

| Nombre | Tipo | Cuerpo / efecto | Aviso de éxito |
|---|---|---|---|
| `titulo` | formulario | `{ titulo }` | "Se guardó el título." |
| `agregar` | formulario | `{ producto_id, cantidad }` | "Se agregó el tratamiento al plan." |
| `cantidad` | formulario | `{ cantidad }` a la línea `datos.lineaId`; guarda también `lineaId` | "Se cambió la cantidad." |
| `etapa` | formulario | `{ etapa: datos.etapa }`; con éxito borra también `rechazar` | "El plan pasó a `ETAPAS[etapa]`." |
| `rechazar` | formulario | `{ etapa: 'perdida', motivo_perdida }` | "El plan pasó a Rechazado." |
| `quitar` | acción | `DELETE` de la línea `elemento.dataset.id`; con 204, `GET /oportunidades/:id` y se guarda el detalle. No hace nada si `estado.cargando > 0` | "Se quitó el tratamiento del plan." |
| `mostrarRechazo` | acción | crea `formularios.rechazar = { valores: { motivo_perdida: '' }, errores: {} }` | — |
| `cancelarRechazo` | acción | borra `formularios.rechazar` | — |

Los formularios se guardan y se borran sin tocar los de otras vistas (`{ ...estado.formularios }`). El texto del aviso no se escapa al armarlo: el layout ya pasa `aviso.texto` por `escapar()`.

Excepciones del formulario `etapa` al patrón común:
- **No se guarda en `estado.formularios`:** no tiene nada escrito que conservar, y `cargar()` no lo borraría al pasar a otro plan. Antes de pedir solo se quita el aviso (`aviso: null`).
- **El 422 va como aviso:** la API responde 422 con la clave `etapa` (transición no permitida, o aceptado sin tratamientos). Como el botón no tiene campo donde mostrar el error, se pone como aviso `danger` con el texto de `e.errores.etapa` (o `e.message` si no viene).

---

## 3. Plan de implementación

Cada paso deja la SPA abriéndose en Live Server (`http://127.0.0.1:5500`) sin errores en la consola. Requiere la API encendida (`cd api && npm run dev`).

1. **Carga y encabezado.** `cargar()` con la limpieza previa, las dos peticiones en paralelo y el caso 404; `render()` con los estados especiales y el encabezado (también fecha de cierre y motivo). Comprobar en la pestaña Red las dos peticiones y CA2 y CA11.
2. **Tratamientos e historial en lectura.** Lista de tratamientos de un plan cerrado, total al pie y la tarjeta Historial. Comprobar CA1 y CA3.
3. **Título.** Formulario `titulo` con 200, 422 y 409, y los avisos de éxito. Comprobar CA4.
4. **Tratamientos en un plan abierto.** Formularios `agregar` y `cantidad`, acción `quitar` y el texto "Ya agregó todos…". Comprobar CA5, CA6 y CA7.
5. **Etapas.** Formulario `etapa`, acciones `mostrarRechazo` y `cancelarRechazo` y formulario `rechazar`. Comprobar CA8, CA9, CA10 y CA12.
6. **Cierre.** `git grep -n "addEventListener\|onclick" frontend/js`, revisar cada `${…}` de `plan.js` (todo dato de la API pasa por `escapar()` o `quetzales()`/`fechaGT()`), y las pruebas de F6 sobre `#/plan/:id` (CA13 a CA18).

---

## 4. Criterios de aceptación

Semilla: `php artisan migrate:fresh --seeder=VerificacionSeeder` salvo que se diga otra cosa, API encendida y la SPA en `http://127.0.0.1:5500`, con sesión de Valeria.

**F3 · Detalle**
- **CA1 · Plan aceptado.** Dado el detalle de "Renovación de planes", cuando se abre, entonces se ve la etapa Aceptado, "Cerrado el 5 oct 2026, 10:00", Plan avanzado "Q2,600.00 × 2 = Q5,200.00", total Q5,650.00 y 5 líneas de historial, la primera con "Creado → Interesado"; y no hay ningún formulario ni botón de etapa, ni "Quitar".
- **CA2 · No encontrado.** Dado `#/plan/999999` o `#/plan/abc`, o el id de un plan de otro asesor, cuando se abre, entonces se ve "No se encontró el plan." con un enlace que lleva a `#/embudo`, sin aviso rojo.
- **CA3 · Plan rechazado.** Dado "Plan familiar", cuando se abre, entonces se ve Rechazado, la fecha de cierre en hora de Guatemala, "Motivo: Eligió a la competencia" y ningún formulario ni botón.
- **CA4 · Título.** Dado un plan abierto, cuando se guarda un título nuevo, entonces la pestaña Red muestra `PATCH /oportunidades/:id` con `{"titulo": …}`, el encabezado muestra el título nuevo y aparece "Se guardó el título."; con el título vacío aparece "Escriba el título (máximo 150 caracteres)." junto al campo y lo escrito se conserva.
- **CA5 · Agregar.** Dado un plan nuevo en Interesado, cuando se agrega "Plan avanzado" con cantidad 2, entonces el cuerpo enviado es `{"producto_id": <número>, "cantidad": 2}`, la línea aparece con su precio guardado y su subtotal, el total sube, "Plan avanzado" ya no está en el `<select>` y se ve "Se agregó el tratamiento al plan.". Sin tratamiento elegido aparece "Elija un tratamiento activo del catálogo." junto al `<select>`; con cantidad 0 aparece "La cantidad debe ser un número entero de 1 a 999." junto a la cantidad y el tratamiento elegido se conserva.
- **CA6 · Cantidad y quitar.** Dado el plan de CA5, cuando se cambia la cantidad a 3, entonces se ve el subtotal y el total nuevos que devuelve la API; con cantidad 1000 el error aparece solo junto a esa línea y el 1000 sigue escrito. Cuando se pulsa "Quitar", la pestaña Red muestra el `DELETE` (204) y después el `GET` del detalle, la línea desaparece y vuelve a estar en el `<select>`.
- **CA7 · Repetido (409).** Dado un plan con "Plan avanzado" y otra pestaña con el mismo plan abierto antes de agregarlo, cuando desde esa pestaña se agrega "Plan avanzado", entonces se ve el aviso "Ese tratamiento ya está en el plan; cambie su cantidad." y lo elegido se conserva.
- **CA8 · Botones de etapa.** Dado un plan en Interesado, cuando se abre, entonces se ven "Avanzar a Evaluado" y "Marcar como rechazado", y **no** "Marcar como aceptado". En En negociación se ven "Marcar como aceptado" y "Marcar como rechazado", y ningún "Avanzar a…".
- **CA9 · Recorrido completo.** Dado el plan de CA5 con un tratamiento, cuando se avanza a Evaluado, Presupuesto enviado y En negociación y se marca como aceptado, entonces cada paso muestra el aviso "El plan pasó a …", el plan queda Aceptado sin formularios ni botones, con su fecha de cierre, y el historial muestra Creado → Interesado y los 4 pasos con el nombre de Valeria y la hora de Guatemala.
- **CA10 · Rechazar.** Dado un plan abierto, cuando se pulsa "Marcar como rechazado", entonces aparece el formulario del motivo; "Cancelar" lo cierra sin pedir nada. Con el motivo vacío o solo espacios aparece "Escriba el motivo (de 1 a 255 caracteres)." junto al motivo y el formulario sigue abierto; con "Prefiere otra clínica" el plan queda Rechazado con ese motivo.
- **CA11 · Al cambiar de plan.** Dado el detalle de un plan con un error 422 a la vista, cuando se pulsa "Planes" y se abre otro plan, entonces no se ve el plan anterior ni su error en ningún momento.
- **CA12 · Regla propia (409).** Dado `php artisan migrate:fresh --seed` y la sesión de Andrea Gómez, cuando abre "Ortodoncia completa" (Sofía Ramírez, en Evaluado, solo con una cita pendiente) y pulsa "Avanzar a Presupuesto enviado", entonces se ve el aviso "El paciente necesita una cita de evaluación completada antes de recibir el presupuesto." y el plan sigue en Evaluado. En el `<select>` de agregar, "Ortodoncia con brackets" e "Implante dental" dicen "Requiere evaluación".

**F6 sobre `#/plan/:id`**
- **CA13 · Escapar.** Dado un paciente llamado `<img src=x onerror=alert(1)>` y un plan suyo con título `<script>alert(1)</script>`, cuando se abre el detalle, se envía en el título ese texto seguido de 150 letras (422, lo escrito vuelve al campo) y se rechaza el plan con ese mismo texto como motivo, entonces todo se ve como texto literal y no se ejecuta nada.
- **CA14 · Delegación.** Dado el código, cuando se ejecuta `git grep -n "addEventListener\|onclick" frontend/js`, entonces el resultado es el mismo que en el spec 10 (nada en `plan.js`), y `plan.js` usa solo `data-form` y `data-accion`.
- **CA15 · Indicador de carga.** Dado la red en "Slow 3G", cuando se abre el detalle o se envía cualquier formulario, entonces se ve el spinner y los botones submit están desactivados hasta que llega la respuesta; un doble clic en "Quitar" envía un solo `DELETE`.
- **CA16 · API apagada.** Dado la API detenida, cuando se abre `#/plan/<id>`, entonces se ve "No se pudo conectar con el servidor. Verifique que la API esté encendida." con "Reintentar", y no una pantalla en blanco. Al encender la API y pulsar "Reintentar", el detalle carga. Con la API detenida, enviar un formulario muestra el mismo aviso y conserva lo escrito.
- **CA17 · 375 px.** Dado el modo teléfono a 375 px, cuando se abre un plan abierto con tratamientos, entonces el encabezado, las líneas, los formularios, los botones de etapa y el historial se apilan a una columna y no hay desplazamiento horizontal, ni siquiera con un título y un motivo de 150 caracteres sin espacios.
- **CA18 · Textos y `localStorage`.** Todo lo visible en `#/plan/:id` está en español, trata de usted y usa "paciente", "plan" y "tratamiento", nunca "cliente", "oportunidad" ni "producto". Después de usar el detalle, `localStorage` solo tiene la clave `sesion`.

---

## 5. Decisiones tomadas y descartadas

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| `cargar()` pide `GET /oportunidades/:id` y `GET /productos` en paralelo y guarda `{ plan, productos }` en `datos.plan` | En serie; dos claves en `datos` | Decidido por la integrante. Menos espera y el contrato dice que cada vista usa solo su clave |
| `cargar()` limpia `datos.plan` y los formularios del plan antes de pedir | Mantener lo anterior hasta que llegue la respuesta | Decidido por la integrante. Al pasar de un plan a otro nunca se ve un plan ajeno ni un error viejo |
| El 404 de `cargar()` se guarda como `{ noEncontrado: true }` y se dibuja "No se encontró el plan." | Dejar que `app.js` muestre el aviso "No se encontró el recurso." | F3 pide ese texto y un enlace al embudo |
| Formulario del título siempre visible en un plan abierto | Botón "Editar título" que lo abre | Decidido por la integrante. Una acción y un estado menos |
| "Marcar como rechazado" abre el formulario con `formularios.rechazar` y lo cierra "Cancelar" | `<details>` de HTML | Decidido por la integrante. El estado sobrevive a cada redibujo y a un 422; un `<details>` se cerraría al redibujar |
| Aviso `success` tras cada cambio correcto | Solo redibujar | Decidido por la integrante. Confirma la acción también a 375 px, donde el cambio puede quedar fuera de la pantalla |
| Los botones de etapa son `form data-form="etapa"` con botón submit | `data-accion` en un botón | La base desactiva los submit mientras hay peticiones: un doble clic no manda dos cambios de etapa (mismo criterio que "Tomar" en el spec 18) |
| El 422 del formulario `etapa` se muestra como aviso `danger` con `e.errores.etapa` | Guardar el error en `formularios.etapa` (patrón común), o dibujarlo dentro de la tarjeta "Cambiar de etapa" | Decidido por la integrante durante la implementación. Con el patrón común el asesor no vería nada, porque el botón no tiene campo; así se ve igual que un 409 |
| El formulario `etapa` no se guarda en `estado.formularios` | Guardarlo como los demás formularios | No tiene nada escrito que conservar, y `cargar()` no lo limpiaría al cambiar de plan |
| "Quitar" es `data-accion="quitar"` y no hace nada si `estado.cargando > 0` | Un formulario por línea | Decidido por la integrante (`data-accion`); la guarda evita un segundo `DELETE` que respondería 404 |
| Tras un 200/201 se usa el detalle que devuelve la API; tras el 204 de quitar, solo `GET /oportunidades/:id` | Volver a llamar `cargar()` completo | Decidido por la integrante. `cargar()` borraría los formularios y el catálogo no cambia por quitar una línea |
| 409 y otros errores con `mostrarError(e)` | Un mensaje propio por formulario | F3 pide los 409 como aviso con el mensaje de la API; `mostrarError` ya hace eso |
| `formularios.cantidad` guarda el `lineaId` y solo esa línea muestra lo escrito y su error | Un formulario de estado por línea | Solo se envía una línea a la vez; un solo formulario basta y no llena el estado |
| El `<select>` de agregar quita los tratamientos que ya están en el plan | Mostrar todos y esperar el 409 | F3 lo pide; el 409 queda para el caso de dos pestañas (CA7) |
| La etiqueta "Requiere evaluación" sale de `requiere_evaluacion` de `GET /productos`; la regla la decide la API | Avisar en el navegador antes de pedir | La regla vive en el servidor (sección 2 de la especificación) |
| Formularios con `novalidate` y sin `required` | Validación HTML5 | El servidor es la única fuente de los mensajes |
| Solo se modifica `plan.js` | Ajustar `estilos.css` o `textos.js` | Decidido por la integrante: son archivos de A. Si algo falta, se avisa |

---

## 6. Riesgos

- **Redibujar borra lo escrito.** Cada `cambiar()` reemplaza el `innerHTML`; por eso cada formulario guarda sus `valores` **antes** de pedir (igual que en los specs 18 y 19).
- **Respuesta tardía de otro plan.** Cada carga y operación captura la generación de carga, ID y sesión; antes de aplicar resultados o errores comprueba que siguen activos. Quitar conserva la ruta original y no pide el detalle si el asesor ya salió. `render()` oculta datos de otro ID incluso antes de que la base llame a `cargar()`. Verificado con respuestas diferidas en memoria; pendiente el navegador. La base de A redibuja antes de cargar, por lo que volver al mismo ID requiere comprobación integrada; no se modificó `app.js`.
- **Plan reasignado o cerrado en otra pestaña.** Un cambio sobre un plan que ya no es suyo responde 404 y uno cerrado 409; los dos se ven como aviso y el detalle queda como estaba hasta que se recargue.
- **`total` o `precio_unitario` como texto.** `quetzales()` los convierte con `Number()`; no se suma nada en el navegador.
- **Muchos formularios a 375 px.** Si un plan tiene muchas líneas, la página se alarga; no se usa desplazamiento horizontal. Si hiciera falta un ajuste de estilos, se le pide a A.
- **CORS y origen.** Si la SPA se abre como `localhost:5500`, se ve como API apagada (S6), no como error del detalle.

---

## Lo que **no** entra en este spec

- El tablero `#/embudo` (spec 19).
- Retrocesos, saltos de etapa o reabrir planes cerrados.
- Registrar citas desde el detalle (spec 20).
- Cualquier cambio en archivos de A o en la API.
