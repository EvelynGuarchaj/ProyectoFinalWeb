// Vista #/embudo (F3): tablero del asesor con sus planes abiertos por etapa, sus planes cerrados
// y el formulario para crear un plan. Spec 19. Totales y probabilidades salen de la API tal cual.
import { estado, cambiar } from '../estado.js';
import { pedir } from '../api.js';
import { escapar, quetzales, fechaGT } from '../util.js';
import { ETAPAS, ABIERTAS, CERRADAS } from '../textos.js';

// Texto de lista vacía de cada etapa cerrada.
const SIN_CERRADOS = {
  ganada: 'Sin planes aceptados.',
  perdida: 'Sin planes rechazados.'
};

// ---------- Partes de la pantalla ----------

// Planes de una etapa, en el orden en que los devuelve la API (de la más reciente a la más antigua).
function deEtapa(oportunidades, etapa) {
  return oportunidades.filter((o) => o.etapa === etapa);
}

function tarjetaAbiertaHtml(o) {
  return `<div class="card mb-2">
    <div class="card-body p-2">
      <a href="#/plan/${escapar(o.id)}" class="fw-semibold d-block texto-largo">${escapar(o.titulo)}</a>
      <div class="small text-muted texto-largo">${escapar(o.cliente && o.cliente.nombre)}</div>
      <div class="small">${quetzales(o.total)} · ${escapar(o.probabilidad)} %</div>
    </div>
  </div>`;
}

function columnaAbiertaHtml(oportunidades, etapa) {
  const planes = deEtapa(oportunidades, etapa);
  const contenido = planes.length
    ? planes.map(tarjetaAbiertaHtml).join('')
    : '<p class="text-muted small">Sin planes en esta etapa.</p>';
  return `<div class="col-12 col-md-6 col-xl-3 columna-embudo">
    <h3 class="h6 d-flex justify-content-between align-items-center">
      <span>${escapar(ETAPAS[etapa])}</span>
      <span class="badge text-bg-secondary">${escapar(planes.length)}</span>
    </h3>
    ${contenido}
  </div>`;
}

function abiertosHtml(oportunidades) {
  return `<h2 class="h5 mb-3">Planes abiertos</h2>
    <div class="row g-3 mb-4">
      ${ABIERTAS.map((etapa) => columnaAbiertaHtml(oportunidades, etapa)).join('')}
    </div>`;
}

function filaCerradaHtml(o) {
  const motivo = o.etapa === 'perdida'
    ? `<div class="small texto-largo">Motivo: ${escapar(o.motivo_perdida)}</div>`
    : '';
  return `<li class="list-group-item">
    <a href="#/plan/${escapar(o.id)}" class="fw-semibold d-block texto-largo">${escapar(o.titulo)}</a>
    <div class="small text-muted texto-largo">${escapar(o.cliente && o.cliente.nombre)}</div>
    <div class="small">${quetzales(o.total)} · Cerrado el ${fechaGT(o.fecha_cierre)}</div>
    ${motivo}
  </li>`;
}

function columnaCerradaHtml(oportunidades, etapa) {
  const planes = deEtapa(oportunidades, etapa);
  const contenido = planes.length
    ? `<ul class="list-group">${planes.map(filaCerradaHtml).join('')}</ul>`
    : `<p class="text-muted small">${SIN_CERRADOS[etapa]}</p>`;
  return `<div class="col-12 col-md-6 columna-embudo">
    <h3 class="h6 d-flex justify-content-between align-items-center">
      <span>${escapar(ETAPAS[etapa])}</span>
      <span class="badge text-bg-secondary">${escapar(planes.length)}</span>
    </h3>
    ${contenido}
  </div>`;
}

function cerradosHtml(oportunidades) {
  return `<h2 class="h5 mb-3">Planes cerrados</h2>
    <div class="row g-3">
      ${CERRADAS.map((etapa) => columnaCerradaHtml(oportunidades, etapa)).join('')}
    </div>`;
}

// ---------- Vista ----------

export default {
  titulo: 'Planes de tratamiento',

  async cargar(params) {
    // Las dos en paralelo; si una falla, el error lo atiende app.js (mostrarError).
    const [oportunidades, clientes] = await Promise.all([
      pedir('GET', '/oportunidades'),
      pedir('GET', '/clientes')
    ]);
    // Un solo cambiar(): la vista usa solo su clave datos.embudo.
    cambiar({ datos: { ...estado.datos, embudo: { oportunidades, clientes } } });
  },

  render(estado) {
    const embudo = estado.datos.embudo;
    if (!embudo) return '';

    return `<h1 class="h4 mb-3">Planes de tratamiento</h1>
      ${abiertosHtml(embudo.oportunidades)}
      ${cerradosHtml(embudo.oportunidades)}`;
  },

  acciones: {},

  formularios: {}
};
