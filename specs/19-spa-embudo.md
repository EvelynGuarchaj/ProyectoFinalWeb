# 19 · Tablero de planes en la SPA

**Estado:** Aprobado
**Depende de:** 10-spa-base (A: `estado.js`, `api.js`, `util.js`, `textos.js` y `app.js` con delegación, indicador de carga y aviso), 11-api-oportunidades (B: `GET /oportunidades` y `POST /oportunidades`), 06-api-clientes (B: `GET /clientes`)
**Fecha:** 2026-11-08
**Dueña:** B
**Rama:** `spec-19-spa-embudo`
**Objetivo:** reemplazar la vista provisional `#/embudo` por el tablero del asesor: sus planes abiertos por etapa, sus planes cerrados y el formulario para crear un plan nuevo.

**Requisitos que cumple:** F3 de `docs/especificacion.md`, parte del tablero (`#/embudo`), y sobre `#/embudo` las comprobaciones de F6 (delegación, indicador de carga, API apagada, `escapar()` (S7) y 375 px).

---

## 1. Alcance

**Incluye**
- `frontend/js/vistas/embudo.js`: reemplaza la vista provisional del spec 10 con su contenido real. **Es el único archivo que se modifica.**
- Las filas de F3 en `docs/verificacion.md` (de B según `docs/reparto.md`) se anotan aparte, en su rama `docs/verificacion`, no dentro de este spec.

**No incluye**
- El detalle del plan `#/plan/:id` (tratamientos, etapas, historial, rechazo): es el spec 21 (`plan.js`).
- Mover un plan de etapa desde el tablero (arrastrar o botones en la tarjeta): F3 lo hace en el detalle.
- Filtros por etapa (`GET /oportunidades?etapa=`) o búsqueda en el tablero.
- Cambios en `app.js`, `api.js`, `estado.js`, `util.js`, `textos.js`, `index.html` o `css/estilos.css` (dueña A). Si hiciera falta uno, se avisa antes y no se hace dentro de este spec.
- Cambios en la API: este spec solo consume endpoints que ya existen.
- Calcular totales o probabilidades en el navegador: se muestran `total` y `probabilidad` tal como los devuelve la API.
- Pruebas automáticas de la SPA: se verifica en el navegador (sección 4).

---

## 2. Modelo de datos

No hay tablas ni migraciones. Se define la parte del estado que usa la vista y la forma de la pantalla.

### 2.1 Estado de la vista

La vista usa solo `estado.datos.embudo` y el formulario `nuevoPlan` (contrato de `docs/plan.md` §3.3):

```js
estado.datos.embudo = {
  oportunidades: [],   // GET /oportunidades → [{ id, cliente: { id, nombre }, titulo, etapa,
                       //   probabilidad, total, fecha_cierre, motivo_perdida }]
  clientes: []         // GET /clientes → [{ id, nombre, nit, telefono, correo }]
};
estado.formularios.nuevoPlan = {
  valores: { cliente_id, titulo },   // tal como se escribieron (cliente_id como texto del <select>)
  errores: {}                        // 422: { cliente_id?, titulo? }
};
```

- Nada de esto va a `localStorage` (F1).
- La agrupación por etapa se hace en `render()` filtrando `oportunidades` por `etapa`, con `ABIERTAS` y `CERRADAS` de `textos.js`. Dentro de cada grupo se respeta el orden de la API (de la más reciente a la más antigua).

### 2.2 Peticiones

| Momento | Peticiones |
|---|---|
| `cargar()` | `GET /oportunidades` y `GET /clientes`, en paralelo (`Promise.all`). Guarda `{ oportunidades, clientes }` en `datos.embudo` con un solo `cambiar()` |
| Nuevo plan | `POST /oportunidades` con `{ cliente_id, titulo }` |

Si cualquiera de las dos peticiones de `cargar()` falla, el error se lanza y lo atiende `app.js` con `mostrarError` (aviso con "Reintentar" si la API está apagada).

### 2.3 Pantalla

`titulo: 'Planes de tratamiento'`. De arriba hacia abajo:

**1. Tarjeta "Nuevo plan"**
- Si `clientes` está vacío: en lugar del formulario, el texto **"Primero agregue pacientes a su cartera"** con un enlace a `#/cartera`.
- Si no: `<form data-form="nuevoPlan" novalidate>` con una fila `row g-2` que a 375 px se apila:
  - `<select name="cliente_id">` (etiqueta "Paciente") con una primera opción `value=""` "Elija un paciente" y una opción por paciente de `clientes` (`value` = id, texto = nombre escapado). Queda `selected` la opción cuyo id coincide con `formularios.nuevoPlan.valores.cliente_id`.
  - `<input type="text" name="titulo">` (etiqueta "Título del plan") con su `value` escapado desde `formularios.nuevoPlan.valores.titulo`.
  - Cada campo con `claseError` y `errorCampo` (`cliente_id` y `titulo`).
  - Botón `type="submit"` "Crear plan".

**2. Planes abiertos.** `<div class="row g-3">` con 4 columnas `col-12 col-md-6 col-xl-3`, una por cada etapa de `ABIERTAS`, en ese orden:
- Encabezado: nombre de pantalla de `ETAPAS[etapa]` y un `badge` con el número de planes de esa etapa.
- Una tarjeta (`card`) por plan:
  - Título como enlace `<a href="#/plan/<id>">`, escapado.
  - Paciente: `cliente.nombre`, escapado.
  - Total: `quetzales(total)`.
  - Probabilidad: `escapar(probabilidad)` + `" %"`.
- Columna vacía: "Sin planes en esta etapa."
- Los textos largos usan la clase `texto-largo` de la base (spec 10) para que no desborden a 375 px.

**3. Planes cerrados.** Encabezado "Planes cerrados" y `<div class="row g-3">` con 2 columnas `col-12 col-md-6`, una por cada etapa de `CERRADAS` (Aceptado y Rechazado), cada una con su `badge` de número:
- Una fila (`list-group-item`) por plan: título (enlace a `#/plan/<id>`), paciente, `quetzales(total)` y "Cerrado el `fechaGT(fecha_cierre)`".
- En Rechazado, además: "Motivo: `motivo_perdida`", escapado.
- Lista vacía: "Sin planes aceptados." / "Sin planes rechazados."

### 2.4 Formulario

**`formularios.nuevoPlan(datos)`**
1. Guarda `valores: { cliente_id: datos.cliente_id, titulo: datos.titulo }` y `errores: {}` **antes** de pedir, para que el redibujo no borre lo escrito.
2. `pedir('POST', '/oportunidades', { cliente_id, titulo })`. La API solo acepta `cliente_id` como entero JSON: se envía `Number(datos.cliente_id)`, o `null` si el `<select>` quedó en "Elija un paciente". `titulo` se envía como texto, sin recortar (lo valida la API).
3. **201**: borra `formularios.nuevoPlan` y pone `location.hash = '#/plan/' + r.id`.
4. **422**: guarda `errores: e.errores` y conserva los `valores`.
5. Otro error (0, 500, sesión terminada): `mostrarError(e)`; los `valores` siguen guardados.

No hay `acciones`: el tablero no tiene botones fuera del formulario.

---

## 3. Plan de implementación

Cada paso deja la SPA abriéndose en Live Server (`http://127.0.0.1:5500`) sin errores en la consola. Requiere la API encendida (`cd api && npm run dev`) con las rutas de clientes y oportunidades.

1. **Carga.** `cargar()` con las dos peticiones en paralelo y `render()` mínimo que muestra cuántos planes llegaron. Comprobar en la pestaña Red las dos peticiones.
2. **Planes abiertos.** Las 4 columnas con encabezado, número, tarjetas y texto de columna vacía. Comprobar CA1.
3. **Planes cerrados.** Las dos listas con fecha y motivo. Comprobar CA2 y CA3.
4. **Nuevo plan.** Tarjeta con el formulario, el caso sin pacientes, y `formularios.nuevoPlan` con 201, 422 y otros errores. Comprobar CA4, CA5, CA6 y CA7.
5. **Cierre.** `git grep -n "addEventListener\|onclick" frontend/js`, revisar cada `${…}` de `embudo.js` (todo dato de la API pasa por `escapar()` o `quetzales()`/`fechaGT()`), y las pruebas de F6 sobre `#/embudo` (CA8 a CA13).

---

## 4. Criterios de aceptación

Semilla: `php artisan migrate:fresh --seeder=VerificacionSeeder`, API encendida y la SPA en `http://127.0.0.1:5500`, con sesión de Valeria salvo que se diga otra cosa.

**F3 · Tablero**
- **CA1 · Abiertos por etapa.** Dado Valeria, cuando abre `#/embudo`, entonces ve 4 columnas en este orden: Interesado (1: Seguimiento anual, Comercial El Quetzal, Q0.00, 10 %), Evaluado (0, "Sin planes en esta etapa."), Presupuesto enviado (1: Capacitación de personal, Ferretería Xela, Q900.00, 50 %) y En negociación (1: Ampliación a bodega, Comercial El Quetzal, Q2,750.50, 75 %).
- **CA2 · Cerrados.** Dado Valeria, cuando baja a "Planes cerrados", entonces ve Aceptado (3): Renovación de planes, Equipo para sucursal y Soporte del hogar; y Rechazado (1): Plan familiar, María Coyoy, Q2,199.99, con "Motivo: Eligió a la competencia".
- **CA3 · Hora de Guatemala.** Dado el tablero de Valeria, cuando se mira "Equipo para sucursal", entonces su fecha de cierre es "31 oct 2026, 21:30", no el 1 de noviembre. "Renovación de planes" dice "5 oct 2026, 10:00".
- **CA4 · Enlace al detalle.** Dado cualquier tarjeta, abierta o cerrada, cuando se pulsa su título, entonces la ruta pasa a `#/plan/<id>` de ese plan.
- **CA5 · Crear plan.** Dado el formulario Nuevo plan con el paciente Ferretería Xela y el título "Limpieza semestral", cuando se envía, entonces la pestaña Red muestra `POST /oportunidades` con `{"cliente_id": <id>, "titulo": "Limpieza semestral"}`, responde 201 y la ruta pasa a `#/plan/<id nuevo>`. Al volver a `#/embudo`, el plan aparece primero en Interesado y el contador de esa columna subió en 1.
- **CA6 · 422 junto a cada campo.** Dado el formulario Nuevo plan con un paciente elegido y el título vacío, cuando se envía, entonces aparece "Escriba el título (máximo 150 caracteres)." bajo el título (`is-invalid` e `invalid-feedback`) y el paciente elegido sigue seleccionado. Sin paciente y con título "Prueba", aparece "Elija un paciente de su cartera." bajo el paciente y "Prueba" sigue escrito.
- **CA7 · Sin pacientes.** Dado un asesor sin pacientes en su cartera (por ejemplo, uno recién creado en el panel), cuando abre `#/embudo`, entonces no ve el formulario, ve "Primero agregue pacientes a su cartera" con un enlace que lleva a `#/cartera`, y las columnas aparecen vacías.

**F6 sobre `#/embudo`**
- **CA8 · Escapar.** Dado un paciente llamado `<img src=x onerror=alert(1)>` (creado desde `#/cartera`) y un plan suyo con título `<script>alert(1)</script>`, cuando aparecen en el tablero y en el `<select>`, entonces se ve el texto literal y no se ejecuta nada. Igual con un título así devuelto en los `valores` tras un 422.
- **CA9 · Delegación.** Dado el código, cuando se ejecuta `git grep -n "addEventListener\|onclick" frontend/js`, entonces el resultado es el mismo que en el spec 10 (nada en `embudo.js`), y el formulario de `embudo.js` usa `data-form`.
- **CA10 · Indicador de carga.** Dado la red en "Slow 3G", cuando se abre `#/embudo` o se envía Nuevo plan, entonces se ve el spinner y el botón "Crear plan" está desactivado hasta que llega la respuesta.
- **CA11 · API apagada.** Dado la API detenida, cuando se abre `#/embudo`, entonces se ve "No se pudo conectar con el servidor. Verifique que la API esté encendida." con "Reintentar", y no una pantalla en blanco. Al encender la API y pulsar "Reintentar", el tablero carga.
- **CA12 · 375 px.** Dado el modo teléfono a 375 px, cuando se abre `#/embudo`, entonces el formulario, las 4 columnas y las 2 listas de cerrados se apilan a una columna y no hay desplazamiento horizontal, ni siquiera con un título de 150 caracteres sin espacios.
- **CA13 · Textos.** Todo lo visible en `#/embudo` está en español, trata de usted y usa "paciente" y "plan", nunca "cliente" ni "oportunidad". El título de la pestaña es "Planes de tratamiento · Dientecitos Felices".
- **CA14 · Solo sesión en `localStorage`.** Después de abrir el tablero y crear un plan, `localStorage` solo tiene la clave `sesion`.

---

## 5. Decisiones tomadas y descartadas

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| `cargar()` pide `GET /oportunidades` y `GET /clientes` en paralelo y guarda `{ oportunidades, clientes }` con un solo `cambiar()` | En serie; dos claves en `datos` | Decidido por la integrante. Menos espera, y el contrato dice que cada vista usa solo su clave (`datos.embudo`) |
| Agrupar por etapa en el navegador desde una sola lista | Una petición por etapa con `?etapa=` | Una sola petición; agrupar no es una regla de negocio y los totales y probabilidades siguen saliendo de la API |
| Formulario "Nuevo plan" arriba, en una tarjeta | Abajo, después de los cerrados | Decidido por la integrante. Es lo primero que se ve, también a 375 px |
| Cerrados en dos listas (Aceptado y Rechazado) de `col-12 col-md-6` | Una sola tabla con columna Etapa | Decidido por la integrante. Mismo patrón visual que las columnas abiertas y sin `table-responsive` |
| 4 columnas `col-12 col-md-6 col-xl-3` | Desplazamiento horizontal tipo kanban | Decidido por la integrante. F6 prohíbe el desplazamiento horizontal a 375 px |
| Probabilidad mostrada desde `probabilidad` de la API | Leer `PROBABILIDAD` de `textos.js` | La API es la fuente; `textos.js` queda para textos de pantalla |
| `cliente_id` se envía como `Number(...)` o `null` | Enviar el texto del `<select>` | La API solo acepta enteros JSON (spec 11); `null` produce el 422 "Elija un paciente de su cartera." |
| Con 201 se va a `#/plan/<id>` sin recargar el tablero | Recargar y quedarse en el tablero | F3 pide abrir el detalle; al volver, `cargar()` trae la lista nueva |
| Sin pacientes se oculta el formulario pero se muestra el tablero | Ocultar todo | Un asesor puede tener planes de pacientes que ya no están en su cartera; el tablero sigue siendo útil |
| Formulario con `novalidate` y sin `required` | Validación HTML5 | El servidor es la única fuente de los mensajes; `required` impediría probar CA6 |
| Solo se modifica `embudo.js` | Ajustar `estilos.css` o `textos.js` | Decidido por la integrante: son archivos de A. Si algo falta, se avisa |

---

## 6. Riesgos

- **Redibujar borra lo escrito.** Cada `cambiar()` reemplaza el `innerHTML`; por eso `nuevoPlan` guarda sus `valores` **antes** de pedir (igual que en el spec 18).
- **Títulos largos a 375 px.** Un título de 150 caracteres sin espacios puede desbordar la tarjeta. Se usa la clase `texto-largo` de la base; si no bastara, se le pide el ajuste a A en `estilos.css` y no se toca aquí.
- **`total` como número o texto.** Si la API devolviera `total` como texto decimal, `quetzales()` lo convierte con `Number()`; no se suma nada en el navegador.
- **Estado viejo del formulario.** `formularios.nuevoPlan` sobrevive al salir de la vista; si se vuelve con un error viejo, se sigue viendo. Se acepta: es el mismo comportamiento que el alta de la cartera.
- **CORS y origen.** Si la SPA se abre como `localhost:5500`, se ve como API apagada (S6), no como error del tablero.

---

## Lo que **no** entra en este spec

- El detalle del plan `#/plan/:id` (spec 21).
- Mover planes de etapa desde el tablero, filtros o búsqueda.
- Cualquier cambio en archivos de A o en la API.
