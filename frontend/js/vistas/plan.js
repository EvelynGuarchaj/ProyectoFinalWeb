// Vista #/plan/:id (F3): detalle de un plan del asesor. Spec 21. Totales, subtotales y
// probabilidades salen de la API tal cual; las transiciones y la regla propia las decide la API.
import { estado, cambiar } from '../estado.js';
import { pedir } from '../api.js';
import { escapar, quetzales, fechaGT, errorCampo, claseError, mostrarError } from '../util.js';
import { ETAPAS, CERRADAS } from '../textos.js';

// Formularios de esta vista: se borran al cargar otro plan sin tocar los de otras vistas.
const FORMULARIOS_PLAN = ['titulo', 'agregar', 'cantidad', 'rechazar'];

// Marca de "el plan no existe o no es suyo" (404 de GET /oportunidades/:id).
const NO_ENCONTRADO = { noEncontrado: true };

// Ruta del plan activo, con el id de la URL codificado.
function rutaPlan() {
  return '/oportunidades/' + encodeURIComponent(estado.params.id);
}

function estaCerrado(plan) {
  return CERRADAS.includes(plan.etapa);
}

// ---------- Estado de los formularios ----------

// Guarda un formulario sin tocar los de otras vistas. `extra` lleva campos propios (ej. lineaId).
function guardarFormulario(nombre, valores, errores, extra = {}) {
  cambiar({ formularios: { ...estado.formularios, [nombre]: { ...extra, valores, errores } } });
}

// Copia de los formularios sin los nombrados.
function sinFormularios(...nombres) {
  const resto = { ...estado.formularios };
  for (const nombre of nombres) delete resto[nombre];
  return resto;
}

// Paso 1 del patrón común: guarda lo escrito y quita el aviso ANTES de pedir,
// porque cada cambio de estado vuelve a dibujar.
function antesDePedir(nombre, valores, extra = {}) {
  cambiar({
    aviso: null,
    formularios: { ...estado.formularios, [nombre]: { ...extra, valores, errores: {} } }
  });
}

// 200/201: el detalle de la API reemplaza al plan (el catálogo se conserva), se borran los
// formularios indicados y se avisa. Todo en un solo cambiar(). El layout escapa el aviso.
function aplicarDetalle(detalle, texto, ...formularios) {
  cambiar({
    datos: { ...estado.datos, plan: { ...estado.datos.plan, plan: detalle } },
    formularios: sinFormularios(...formularios),
    aviso: { tipo: 'success', texto }
  });
}

// 422: errores junto a cada campo y lo escrito se conserva. 409 y cualquier otro error: aviso.
function atenderError(e, nombre, valores, extra = {}) {
  if (e.status === 422) {
    guardarFormulario(nombre, valores, e.errores, extra);
  } else {
    mostrarError(e); // los valores siguen guardados
  }
}

// ---------- Partes de la pantalla ----------

function noEncontradoHtml() {
  return `<div class="card">
    <div class="card-body">
      <p>No se encontró el plan.</p>
      <a href="#/embudo">Volver a los planes</a>
    </div>
  </div>`;
}

// novalidate y sin required: los mensajes de error los da el servidor.
function tituloFormHtml(estado, plan) {
  const form = estado.formularios.titulo;
  const errores = form ? form.errores : {};
  const valor = form ? form.valores.titulo : plan.titulo;
  return `<form data-form="titulo" novalidate class="mt-3">
    <div class="row g-2 align-items-start">
      <div class="col-12 col-md-9">
        <label for="plan-titulo" class="form-label">Título del plan</label>
        <input type="text" id="plan-titulo" name="titulo" class="form-control${claseError(errores, 'titulo')}"
               value="${escapar(valor)}" autocomplete="off">
        ${errorCampo(errores, 'titulo')}
      </div>
      <div class="col-12 col-md-3 d-grid align-self-md-end">
        <button type="submit" class="btn btn-outline-primary">Guardar título</button>
      </div>
    </div>
  </form>`;
}

function encabezadoHtml(estado, plan) {
  let cierre = '';
  if (estaCerrado(plan)) {
    const motivo = plan.etapa === 'perdida'
      ? `<p class="mb-0 texto-largo">Motivo: ${escapar(plan.motivo_perdida)}</p>`
      : '';
    cierre = `<p class="mb-1">Cerrado el ${fechaGT(plan.fecha_cierre)}</p>${motivo}`;
  } else {
    cierre = tituloFormHtml(estado, plan);
  }
  return `<div class="card mb-3">
    <div class="card-body">
      <h1 class="h4 texto-largo">${escapar(plan.titulo)}</h1>
      <p class="mb-1 texto-largo">Paciente: ${escapar(plan.cliente && plan.cliente.nombre)}</p>
      <p class="mb-1">Etapa: <span class="badge text-bg-primary">${escapar(ETAPAS[plan.etapa])}</span></p>
      <p class="mb-1">Probabilidad: ${escapar(plan.probabilidad)} % · Total: ${quetzales(plan.total)}</p>
      ${cierre}
    </div>
  </div>`;
}

// Formulario de cantidad de una línea. Solo la línea que se envió (formularios.cantidad.lineaId)
// muestra lo escrito y su error; las demás muestran la cantidad de la API.
function cantidadFormHtml(estado, linea) {
  const form = estado.formularios.cantidad;
  const esEsta = form && String(form.lineaId) === String(linea.id);
  const errores = esEsta ? form.errores : {};
  const valor = esEsta ? form.valores.cantidad : linea.cantidad;
  const idCampo = `cantidad-${escapar(linea.id)}`;
  return `<form data-form="cantidad" novalidate class="mt-2">
    <input type="hidden" name="lineaId" value="${escapar(linea.id)}">
    <div class="row g-2 align-items-start">
      <div class="col-12 col-md-4">
        <label for="${idCampo}" class="form-label small">Cantidad</label>
        <input type="number" id="${idCampo}" name="cantidad" min="1" max="999" step="1"
               class="form-control form-control-sm${claseError(errores, 'cantidad')}" value="${escapar(valor)}">
        ${errorCampo(errores, 'cantidad')}
      </div>
      <div class="col-12 col-md-8 d-grid gap-2 d-md-flex align-self-md-end">
        <button type="submit" class="btn btn-sm btn-outline-primary">Cambiar cantidad</button>
        <button type="button" class="btn btn-sm btn-outline-danger" data-accion="quitar"
                data-id="${escapar(linea.id)}">Quitar</button>
      </div>
    </div>
  </form>`;
}

// Una línea de tratamiento. Cerrado: solo lectura. Abierto: precio guardado, subtotal y su formulario.
function lineaHtml(estado, linea, cerrado) {
  const detalle = cerrado
    ? `<div class="small">${quetzales(linea.precio_unitario)} × ${escapar(linea.cantidad)} = ${quetzales(linea.subtotal)}</div>`
    : `<div class="small">Precio guardado: ${quetzales(linea.precio_unitario)}</div>
       <div class="small">Subtotal: ${quetzales(linea.subtotal)}</div>
       ${cantidadFormHtml(estado, linea)}`;
  return `<li class="list-group-item">
    <div class="fw-semibold texto-largo">${escapar(linea.nombre)}</div>
    ${detalle}
  </li>`;
}

// Formulario "Agregar tratamiento": el <select> solo ofrece los tratamientos que aún no están en el plan.
function agregarHtml(estado, plan, productos) {
  const enPlan = new Set((plan.productos || []).map((l) => String(l.producto_id)));
  const disponibles = (productos || []).filter((p) => !enPlan.has(String(p.id)));
  if (disponibles.length === 0) {
    return '<p class="text-muted mb-0">Ya agregó todos los tratamientos del catálogo.</p>';
  }

  const form = estado.formularios.agregar;
  const valores = form ? form.valores : { producto_id: '', cantidad: 1 };
  const errores = form ? form.errores : {};
  const elegido = String(valores.producto_id ?? '');
  const opciones = disponibles.map((p) => {
    const seleccionada = String(p.id) === elegido ? ' selected' : '';
    const evaluacion = p.requiere_evaluacion ? ' · Requiere evaluación' : '';
    return `<option value="${escapar(p.id)}"${seleccionada}>${escapar(p.nombre)} · ${quetzales(p.precio)}${evaluacion}</option>`;
  }).join('');

  return `<h3 class="h6">Agregar tratamiento</h3>
    <form data-form="agregar" novalidate>
      <div class="row g-2 align-items-start">
        <div class="col-12 col-md-7">
          <label for="agregar-producto" class="form-label">Tratamiento</label>
          <select id="agregar-producto" name="producto_id" class="form-select texto-largo${claseError(errores, 'producto_id')}">
            <option value="">Elija un tratamiento</option>
            ${opciones}
          </select>
          ${errorCampo(errores, 'producto_id')}
        </div>
        <div class="col-12 col-md-3">
          <label for="agregar-cantidad" class="form-label">Cantidad</label>
          <input type="number" id="agregar-cantidad" name="cantidad" min="1" max="999" step="1"
                 class="form-control${claseError(errores, 'cantidad')}" value="${escapar(valores.cantidad)}">
          ${errorCampo(errores, 'cantidad')}
        </div>
        <div class="col-12 col-md-2 d-grid align-self-md-end">
          <button type="submit" class="btn btn-primary">Agregar</button>
        </div>
      </div>
    </form>`;
}

function tratamientosHtml(estado, datos) {
  const plan = datos.plan;
  const cerrado = estaCerrado(plan);
  const lineas = plan.productos || [];
  const lista = lineas.length
    ? `<ul class="list-group list-group-flush">${lineas.map((l) => lineaHtml(estado, l, cerrado)).join('')}</ul>`
    : '<div class="card-body"><p class="text-muted mb-0">Este plan aún no tiene tratamientos.</p></div>';
  const agregar = cerrado
    ? ''
    : `<div class="card-body border-top">${agregarHtml(estado, plan, datos.productos)}</div>`;
  return `<div class="card mb-3">
    <div class="card-header"><h2 class="h5 mb-0">Tratamientos</h2></div>
    ${lista}
    <div class="card-footer fw-semibold">Total: ${quetzales(plan.total)}</div>
    ${agregar}
  </div>`;
}

function filaHistorialHtml(h) {
  const anterior = h.etapa_anterior === null ? 'Creado' : ETAPAS[h.etapa_anterior];
  return `<li class="list-group-item">
    <div>${escapar(anterior)} → ${escapar(ETAPAS[h.etapa_nueva])}</div>
    <div class="small text-muted texto-largo">${escapar(h.usuario)} · ${fechaGT(h.fecha)}</div>
  </li>`;
}

function historialHtml(plan) {
  const filas = plan.historial || [];
  return `<div class="card mb-3">
    <div class="card-header"><h2 class="h5 mb-0">Historial</h2></div>
    <ul class="list-group list-group-flush">${filas.map(filaHistorialHtml).join('')}</ul>
  </div>`;
}

// ---------- Vista ----------

export default {
  titulo: 'Detalle del plan',

  async cargar(params) {
    // Un solo cambiar(): nunca se ve el plan anterior ni sus errores mientras llega el nuevo.
    const formularios = { ...estado.formularios };
    for (const nombre of FORMULARIOS_PLAN) delete formularios[nombre];
    cambiar({ datos: { ...estado.datos, plan: null }, formularios });

    // Las dos en paralelo. Solo el 404 del plan se dibuja como "No se encontró el plan.";
    // cualquier otro error lo atiende app.js (mostrarError).
    const [plan, productos] = await Promise.all([
      pedir('GET', rutaPlan()).catch((e) => {
        if (e.status === 404) return NO_ENCONTRADO;
        throw e;
      }),
      pedir('GET', '/productos')
    ]);

    const datosPlan = plan === NO_ENCONTRADO ? NO_ENCONTRADO : { plan, productos };
    cambiar({ datos: { ...estado.datos, plan: datosPlan } });
  },

  render(estado) {
    const datos = estado.datos.plan;
    if (!datos) return ''; // cargando: el indicador de la base ya se ve
    if (datos.noEncontrado) return noEncontradoHtml();

    const plan = datos.plan;
    return `${encabezadoHtml(estado, plan)}
      ${tratamientosHtml(estado, datos)}
      ${historialHtml(plan)}`;
  },

  acciones: {
    async quitar(elemento) {
      // Un doble clic no manda un segundo DELETE (respondería 404).
      if (estado.cargando > 0) return;
      const lineaId = elemento.dataset.id;
      cambiar({ aviso: null });
      // Los errores (404, 409, API apagada) los atiende app.js con mostrarError.
      await pedir('DELETE', `${rutaPlan()}/productos/${encodeURIComponent(lineaId)}`);
      // 204 sin cuerpo: se pide el detalle nuevo (el catálogo no cambia).
      const detalle = await pedir('GET', rutaPlan());
      aplicarDetalle(detalle, 'Se quitó el tratamiento del plan.');
    }
  },

  formularios: {
    async titulo(datos) {
      // El título viaja tal cual, sin recortar: lo valida la API.
      const valores = { titulo: datos.titulo ?? '' };
      antesDePedir('titulo', valores);

      let detalle;
      try {
        detalle = await pedir('PATCH', rutaPlan(), { titulo: valores.titulo });
      } catch (e) {
        atenderError(e, 'titulo', valores);
        return;
      }
      aplicarDetalle(detalle, 'Se guardó el título.', 'titulo');
    },

    async agregar(datos) {
      const valores = { producto_id: datos.producto_id ?? '', cantidad: datos.cantidad ?? '' };
      antesDePedir('agregar', valores);

      let detalle;
      try {
        // Números JSON: sin tratamiento viaja null; cantidad vacía → 0, texto → NaN → null (422).
        detalle = await pedir('POST', `${rutaPlan()}/productos`, {
          producto_id: valores.producto_id === '' ? null : Number(valores.producto_id),
          cantidad: Number(valores.cantidad)
        });
      } catch (e) {
        atenderError(e, 'agregar', valores);
        return;
      }
      aplicarDetalle(detalle, 'Se agregó el tratamiento al plan.', 'agregar');
    },

    async cantidad(datos) {
      const lineaId = datos.lineaId;
      const valores = { cantidad: datos.cantidad ?? '' };
      antesDePedir('cantidad', valores, { lineaId });

      let detalle;
      try {
        detalle = await pedir('PATCH', `${rutaPlan()}/productos/${encodeURIComponent(lineaId)}`, {
          cantidad: Number(valores.cantidad)
        });
      } catch (e) {
        atenderError(e, 'cantidad', valores, { lineaId });
        return;
      }
      aplicarDetalle(detalle, 'Se cambió la cantidad.', 'cantidad');
    }
  }
};
