# 22 · Resumen del mes en la SPA

**Estado:** Aprobado  
**Depende de:** 10-spa-base (`estado.js`, `api.js`, `util.js`, `app.js` con delegación, indicador de carga y aviso), 15-api-resumen (B: `GET /reportes/resumen?mes=AAAA-MM`; hoy en la rama `origin/spec-15-api-resumen`, todavía no en `main`)
**Fecha:** 2026-11-08
**Dueña:** A
**Rama:** `spec-22-spa-resumen`
**Objetivo:** que el asesor consulte en `#/resumen` su resumen de cualquier mes: planes aceptados, total vendido, pronóstico y cartera.

**Requisitos que cumple:** F5 completo de `docs/especificacion.md`, y sobre `#/resumen` las comprobaciones de F6 (delegación, indicador de carga, API apagada, `escapar()` (S7), 375 px y textos).

---

## 1. Alcance

**Incluye**
- `frontend/js/vistas/resumen.js`: reemplaza la vista provisional del spec 10 con su contenido real. **Es el único archivo de código que cambia.**
- Anotar en `docs/verificacion.md` las filas de F5.

**No incluye**
- Cambios en `api.js`, `util.js`, `estado.js`, `app.js`, `textos.js` o `css/estilos.css` (spec 10). Si hiciera falta uno, se avisa antes y no se hace dentro de este spec.
- Cambios en la API (spec 15, de B).
- Calcular cifras en el navegador: ventas, total, pronóstico y cartera llegan hechos de la API; la SPA solo les da formato.
- Validar el mes en el navegador: el árbitro es la API (400).
- Pruebas automáticas de la SPA: se verifica en el navegador (sección 4).

---

## 2. Modelo de datos

No hay tablas ni migraciones. Se define la parte del estado que usa la vista y la forma de la pantalla.

### 2.1 Estado de la vista

La vista usa solo `estado.datos.resumen` y el formulario `mes` (contrato de `docs/plan.md` §3.3):

```js
estado.datos.resumen = null | {   // respuesta de GET /reportes/resumen, tal cual
  mes: '2026-10',
  ventas: 2,
  total: 9250,
  pronostico: 2512.88,
  cartera: { clientes: 3, maximo: 10 }
};
estado.formularios.mes = { valores: { mes: '2026-10' } };   // lo que muestra el selector
```

- `datos.resumen` guarda siempre las **últimas cifras válidas**. Un 400 no las borra.
- Nada de esto va a `localStorage` (F1).

### 2.2 Peticiones

| Momento | Petición |
|---|---|
| `cargar()` | `GET /reportes/resumen` **sin** `?mes=`. La API usa el mes actual de Guatemala; el selector se llena con el `mes` de la respuesta (F5). La SPA no usa `mesActualGT()` |
| Enviar el formulario `mes` | `GET /reportes/resumen?mes=<encodeURIComponent(mes)>` |

### 2.3 Pantalla

`titulo: 'Resumen del mes'`.

1. Encabezado "Resumen del mes".
2. `<form data-form="mes" class="row g-2 align-items-end">`:
   - `<input type="month" name="mes" id="resumen-mes" class="form-control">` con la etiqueta "Mes" y `value` escapado de `formularios.mes.valores.mes`. Sin `required`, `min` ni `max`.
   - Botón `type="submit"` "Ver resumen".
3. Subtítulo con el mes de las cifras que se ven: "Cifras de octubre de 2026". Sale de `datos.resumen.mes` (no del selector), así nunca se confunden las cifras con el mes escrito. Se arma dentro de `resumen.js` con `Intl.DateTimeFormat('es-GT', { month: 'long', year: 'numeric', timeZone: 'UTC' })` sobre `` `${mes}-01T00:00:00Z` ``, y se escapa. Usar UTC en los dos lados hace que el texto no dependa de la zona de la computadora.
4. Rejilla `row g-3` con cuatro tarjetas Bootstrap (`card h-100`), cada una en `col-12 col-sm-6 col-lg-3`:

| Tarjeta | Valor grande | Debajo |
|---|---|---|
| **Planes aceptados** | `escapar(ventas)` | — |
| **Total vendido** | `quetzales(total)` | — |
| **Pronóstico** | `quetzales(pronostico)` | `<small class="text-muted">de sus planes abiertos, sin importar el mes</small>` |
| **Cartera** | `` `${escapar(cartera.clientes)} de ${escapar(cartera.maximo)}` `` | — |

5. Si `datos.resumen` es `null` (la primera carga falló), no se dibujan subtítulo ni tarjetas; se ve el formulario y el texto "No se pudo cargar el resumen." El aviso del error lo pone `app.js`.

A 375 px las tarjetas quedan una debajo de otra y el formulario a una columna.

### 2.4 Carga y formulario

**`cargar()`**
1. `r = await pedir('GET', '/reportes/resumen')`.
2. `cambiar({ datos: { ...estado.datos, resumen: r }, formularios: { ...estado.formularios, mes: { valores: { mes: r.mes } } } })`.
3. Si falla, pone `datos.resumen = null` y vuelve a lanzar el error: `app.js` lo muestra con `mostrarError` (con "Reintentar" si la API está apagada).

**`formularios.mes(datos)`**
1. `mes = datos.mes` (tal como viene del input; vacío si se borró). Guarda `formularios.mes.valores.mes = mes` **antes** de pedir, para que el redibujado no borre lo elegido.
2. `r = await pedir('GET', '/reportes/resumen?mes=' + encodeURIComponent(mes))`.
3. **200**: `datos.resumen = r`, `formularios.mes.valores.mes = r.mes` y `aviso: null` (quita un aviso de 400 anterior).
4. **400**: `mostrarError(e)`: aviso rojo con el mensaje de la API ("Escriba el mes con el formato AAAA-MM."). Las tarjetas siguen con las últimas cifras válidas y su subtítulo dice de qué mes son.
5. Otro error (0, 500, sesión terminada): `mostrarError(e)`.

No hay `acciones` propias: el formulario es lo único interactivo.

---

## 3. Plan de implementación

Cada paso deja la SPA abriéndose en Live Server (`http://127.0.0.1:5500`) sin errores en la consola. Requiere la API con `GET /reportes/resumen` (spec 15 en `main`, o la rama `spec-15-api-resumen` corriendo en local para probar).

1. **Carga y tarjetas.** `cargar()` sin `mes`, `render` con el formulario, el subtítulo y las cuatro tarjetas. Comprobar CA2 y CA1 (eligiendo octubre).
2. **Formulario.** `formularios.mes` con 200 y 400. Comprobar CA1, CA3, CA4 y CA5.
3. **Cierre.** `git grep -n "addEventListener\|onclick" frontend/js`, revisar cada `${…}` de `resumen.js` (todo dato de la API pasa por `escapar()` o `quetzales()`), las pruebas de F6 sobre `#/resumen` (CA6 a CA11) y anotar F5 en `docs/verificacion.md`.

---

## 4. Criterios de aceptación

Semilla: `php artisan migrate:fresh --seeder=VerificacionSeeder`, API encendida (`cd api && npm run dev`) y la SPA en `http://127.0.0.1:5500`, con sesión de Valeria.

**F5 · Resumen del mes**
- **CA1 · Cifras de control.** Dado Valeria, cuando elige octubre de 2026 y pulsa "Ver resumen", entonces ve: Planes aceptados **2**, Total vendido **Q9,250.00**, Pronóstico **Q2,512.88** y Cartera **3 de 10**, con el subtítulo "Cifras de octubre de 2026". Con noviembre de 2026 ve 1, Q3,600.00, Q2,512.88 y 3 de 10.
- **CA2 · Mes inicial de la API.** Dado la vista recién abierta, cuando carga, entonces la pestaña Red muestra `GET /reportes/resumen` **sin** `mes` y el selector muestra el `mes` que devolvió la API (hoy, noviembre de 2026).
- **CA3 · Mes sin ventas.** Dado Valeria, cuando elige enero de 2020, entonces ve Planes aceptados 0 y Total vendido Q0.00, mientras Pronóstico (Q2,512.88) y Cartera (3 de 10) no cambian.
- **CA4 · Nota del pronóstico.** La tarjeta Pronóstico muestra debajo "de sus planes abiertos, sin importar el mes".
- **CA5 · Mes inválido (400).** Dado octubre de 2026 en pantalla, cuando se borra el selector y se pulsa "Ver resumen" (la pestaña Red muestra `GET /reportes/resumen?mes=`), entonces se ve el aviso rojo "Escriba el mes con el formato AAAA-MM." y las tarjetas siguen con las cifras de octubre y su subtítulo. Al elegir un mes válido, el aviso desaparece. *(En un navegador donde `type="month"` es un campo de texto, también se prueba escribiendo `2026-13`.)*
- **CA6 · Otra zona horaria.** Dado la computadora en otra zona horaria (por ejemplo, en las herramientas del navegador, Sensores → Ubicación "Tokyo"), cuando se abre la vista y se elige octubre de 2026, entonces se ven exactamente las mismas cifras y el mismo subtítulo.

**F6 sobre `#/resumen`**
- **CA7 · Delegación.** Dado el código, cuando se ejecuta `git grep -n "addEventListener\|onclick" frontend/js`, entonces el resultado es el mismo que en el spec 10 (nada en `resumen.js`), y el formulario usa `data-form="mes"`.
- **CA8 · Indicador de carga.** Dado la red en "Slow 3G", cuando se abre `#/resumen` o se pulsa "Ver resumen", entonces se ve el spinner y el botón "Ver resumen" está desactivado hasta que llega la respuesta.
- **CA9 · API apagada.** Dado la API detenida, cuando se abre `#/resumen`, entonces se ve "No se pudo conectar con el servidor. Verifique que la API esté encendida." con "Reintentar" y el texto "No se pudo cargar el resumen.", no una pantalla en blanco. Al encender la API y pulsar "Reintentar", la vista carga.
- **CA10 · 375 px.** Dado el modo teléfono a 375 px, cuando se abre `#/resumen`, entonces las cuatro tarjetas se apilan y no hay desplazamiento horizontal.
- **CA11 · Textos y `localStorage`.** Todo lo visible está en español, trata de usted y usa "Planes aceptados", nunca "ventas". El título de la pestaña es "Resumen del mes · Dientecitos Felices". Después de consultar varios meses, `localStorage` solo tiene la clave `sesion`.

---

## 5. Decisiones tomadas y descartadas

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| Al abrir, `GET /reportes/resumen` sin `mes` y el selector toma el `mes` de la respuesta | Llenar el selector con `mesActualGT()` y pedir `?mes=` con ese valor | Decidido por la integrante al ver el choque con F5, que lo pide así y tiene un CA sobre ello. El mes actual lo decide un solo lado (la API) |
| El mes se consulta con un `form data-form="mes"` y el botón "Ver resumen" | Pedir en el evento `change` del input, como sugiere F5 ("al cambiar el mes") | Decidido por la integrante. F6 solo permite los escuchadores delegados de `click` y `submit` de `app.js`; un `change` necesitaría otro escuchador. Además el `submit` lo desactiva la base mientras hay una petición |
| Un 400 va al aviso general con el mensaje de la API y las tarjetas conservan las últimas cifras válidas | Borrar las tarjetas; mensaje junto al campo | Decidido por la integrante ("aviso con su mensaje"). El subtítulo con el mes de las cifras evita confundirlas con el mes escrito |
| Sin validación del mes en el navegador (`required`, `min`, `max`) | Validar con HTML5 o una expresión regular | La API es el árbitro y así el 400 se puede probar |
| Subtítulo "Cifras de <mes> de <año>" armado con `Intl` en UTC dentro de `resumen.js` | Agregar una función a `util.js`; no mostrar el mes | `util.js` es del spec 10 y este spec solo toca `resumen.js`. UTC en los dos lados no depende de la zona de la computadora (CA6) |
| Montos con `quetzales()` y conteos con `escapar()` | Formatear a mano | F5 obliga a `quetzales`; S7 obliga a `escapar` todo dato de la API, aunque sea número |
| El 200 quita el aviso (`aviso: null`) | Dejarlo hasta la siguiente navegación | Tras corregir el mes, un aviso rojo viejo confundiría |

---

## 6. Riesgos

- **Spec 15 sin fusionar.** `GET /reportes/resumen` está en `origin/spec-15-api-resumen`. Si cambian la forma de la respuesta o el texto del 400, este spec y sus CA se actualizan. Hasta que esté en `main`, la vista responde 404 "Ruta no encontrada." y se ve como error al cargar.
- **`type="month"` en otros navegadores.** En los que no lo soportan es un campo de texto y el asesor puede escribir cualquier cosa; la API lo rechaza con 400 (CA5), así que no hay daño.
- **Redibujar borra lo escrito.** Cada `cambiar()` reemplaza el `innerHTML`; por eso el formulario guarda `valores.mes` **antes** de pedir.
- **CORS y origen.** Si la SPA se abre como `localhost:5500`, se ve como API apagada (S6), no como error del resumen.
