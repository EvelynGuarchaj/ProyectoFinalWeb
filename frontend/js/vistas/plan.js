// Vista #/plan/:id (F3): detalle de un plan del asesor. Spec 21. Totales, subtotales y
// probabilidades salen de la API tal cual; las transiciones y la regla propia las decide la API.
import { estado, cambiar } from '../estado.js';
import { pedir } from '../api.js';
import { escapar, quetzales, fechaGT } from '../util.js';
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

// ---------- Partes de la pantalla ----------

function noEncontradoHtml() {
  return `<div class="card">
    <div class="card-body">
      <p>No se encontró el plan.</p>
      <a href="#/embudo">Volver a los planes</a>
    </div>
  </div>`;
}

function encabezadoHtml(plan) {
  let cierre = '';
  if (estaCerrado(plan)) {
    const motivo = plan.etapa === 'perdida'
      ? `<p class="mb-0 texto-largo">Motivo: ${escapar(plan.motivo_perdida)}</p>`
      : '';
    cierre = `<p class="mb-1">Cerrado el ${fechaGT(plan.fecha_cierre)}</p>${motivo}`;
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

    return encabezadoHtml(datos.plan);
  },

  acciones: {},

  formularios: {}
};
