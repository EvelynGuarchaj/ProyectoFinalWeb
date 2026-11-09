// Vista #/actividades (F4): actividades del asesor (llamadas, citas y tareas de los pacientes de su cartera).
// Usa GET /actividades?estado=, GET /clientes, POST /clientes/:id/actividades y PATCH /actividades/:id.
import { estado, cambiar } from '../estado.js';
import { pedir } from '../api.js';
import { escapar, fechaGT } from '../util.js';
import { TIPOS } from '../textos.js';

// Datos de la vista con valores por omisión (spec 20, §2.1).
function datosVista() {
  return { pestana: 'pendiente', lista: [], clientes: [], ...(estado.datos.actividades || {}) };
}

const PESTANAS = ['pendiente', 'completada'];

// Cambia solo estado.datos.actividades, sin tocar lo de las demás vistas.
function guardarDatos(parcial) {
  cambiar({ datos: { ...estado.datos, actividades: { ...datosVista(), ...parcial } } });
}

// Vuelve a pedir la lista de la pestaña activa y, si se pide, también los pacientes del select.
async function recargar(conClientes) {
  const { pestana } = datosVista();
  if (!conClientes) {
    guardarDatos({ lista: await pedir('GET', `/actividades?estado=${pestana}`) });
    return;
  }
  const [lista, clientes] = await Promise.all([
    pedir('GET', `/actividades?estado=${pestana}`),
    pedir('GET', '/clientes')
  ]);
  guardarDatos({ lista, clientes });
}

// ---------- Dibujo ----------

function pestanas(activa) {
  const opciones = [
    { valor: 'pendiente', texto: 'Pendientes' },
    { valor: 'completada', texto: 'Completadas' }
  ];
  const botones = opciones.map(({ valor, texto }) => {
    const esActiva = valor === activa;
    return `<li class="nav-item">
        <button type="button" class="nav-link${esActiva ? ' active' : ''}"${esActiva ? ' aria-current="page"' : ''}
                data-accion="pestana" data-valor="${valor}">${texto}</button>
      </li>`;
  }).join('');
  return `<ul class="nav nav-tabs mb-3">${botones}</ul>`;
}

function fila(actividad, pestana) {
  const vencida = actividad.vencida === true;
  const tipo = Object.hasOwn(TIPOS, actividad.tipo) ? TIPOS[actividad.tipo] : actividad.tipo;
  const fecha = actividad.vence_en
    ? `<span class="${vencida ? 'text-danger' : 'text-muted'}">Vence: ${escapar(fechaGT(actividad.vence_en))}</span>`
    : '<span class="text-muted">Sin fecha</span>';
  const etiquetaVencida = vencida ? ' <span class="badge text-bg-danger">Vencida</span>' : '';
  const paciente = actividad.cliente ? actividad.cliente.nombre : '';
  const completar = pestana === 'pendiente'
    ? `<button type="button" class="btn btn-sm btn-outline-success mt-2"
              data-accion="completar" data-id="${escapar(actividad.id)}">Marcar como completada</button>`
    : '';

  return `<li class="list-group-item${vencida ? ' vencida border-danger' : ''}">
      <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
        <strong class="texto-largo">${escapar(paciente)}</strong>
        <span class="badge text-bg-secondary">${escapar(tipo)}</span>${etiquetaVencida}
      </div>
      <div class="texto-largo mb-1">${escapar(actividad.descripcion)}</div>
      <div class="small">${fecha}</div>
      ${completar}
    </li>`;
}

function lista(datos) {
  if (datos.lista.length === 0) {
    const texto = datos.pestana === 'completada'
      ? 'No tiene actividades completadas.'
      : 'No tiene actividades pendientes.';
    return `<p class="text-muted">${texto}</p>`;
  }
  return `<ul class="list-group">${datos.lista.map((a) => fila(a, datos.pestana)).join('')}</ul>`;
}

// ---------- Vista ----------

export default {
  titulo: 'Actividades',

  // Pide la pestaña guardada (Pendientes la primera vez de la sesión) y los pacientes para el select.
  async cargar(params) {
    const { pestana } = datosVista();
    const [lista, clientes] = await Promise.all([
      pedir('GET', `/actividades?estado=${pestana}`),
      pedir('GET', '/clientes')
    ]);
    guardarDatos({ lista, clientes });
  },

  render(estado) {
    const datos = datosVista();
    return `<div class="row g-3">
      <section class="col-12 col-lg-7">
        <h1 class="h4 mb-3">Mis actividades</h1>
        ${pestanas(datos.pestana)}
        ${lista(datos)}
      </section>
    </div>`;
  },

  acciones: {
    // Cambia de pestaña: solo vuelve a pedir la lista. La pestaña se guarda cuando llega la respuesta.
    async pestana(elemento) {
      const valor = elemento.dataset.valor;
      if (!PESTANAS.includes(valor)) return;
      const lista = await pedir('GET', `/actividades?estado=${valor}`);
      guardarDatos({ pestana: valor, lista });
    },

    // PATCH /actividades/:id. Con 200 sale de Pendientes; con 404 (paciente reasignado) se pone la pantalla al día.
    // El aviso va después de la recarga para que su cambiar() no lo pise.
    async completar(elemento) {
      const id = elemento.dataset.id;
      try {
        await pedir('PATCH', `/actividades/${encodeURIComponent(id)}`, { completada: true });
      } catch (e) {
        if (e.status !== 404) throw e;
        await recargar(true);
        cambiar({ aviso: { tipo: 'danger', texto: e.message } });
        return;
      }
      await recargar(false);
      cambiar({ aviso: { tipo: 'success', texto: 'Actividad completada.' } });
    }
  },

  formularios: {}
};
