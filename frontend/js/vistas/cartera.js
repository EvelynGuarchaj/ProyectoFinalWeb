// Vista #/cartera (F2): pacientes del asesor, contador "x de 10" y pacientes disponibles.
// Spec 18. La búsqueda, el contador y las validaciones los hace el servidor; aquí solo se muestran.
import { estado, cambiar } from '../estado.js';
import { pedir } from '../api.js';
import { escapar } from '../util.js';

// ---------- Peticiones ----------

function pedirMios(q) {
  return pedir('GET', q ? `/clientes?q=${encodeURIComponent(q)}` : '/clientes');
}

function pedirDisponibles() {
  return pedir('GET', '/clientes/disponibles');
}

// El contador sale de GET /reportes/resumen. Si falla por otra cosa que la sesión o la API apagada
// (por ejemplo 404 mientras el spec 15 no exista), se devuelve null y la vista dice
// "Contador no disponible", sin aviso. Nunca se cuenta la lista en el navegador.
async function pedirContador() {
  try {
    const resumen = await pedir('GET', '/reportes/resumen');
    return resumen.cartera;
  } catch (e) {
    if (e.sesionTerminada || e.status === 0) throw e;
    return null;
  }
}

// Mezcla cambios en datos.cartera sin tocar las claves de las otras vistas.
function guardarCartera(parcial) {
  const actual = estado.datos.cartera || { mios: [], disponibles: [], resumen: null, q: '' };
  cambiar({ datos: { ...estado.datos, cartera: { ...actual, ...parcial } } });
}

// ---------- Partes de la pantalla ----------

function contadorHtml(resumen) {
  if (!resumen) return '<p class="text-muted mb-3">Contador no disponible</p>';
  return `<p class="mb-3"><span class="badge text-bg-primary fs-6">${escapar(resumen.clientes)} de ${escapar(resumen.maximo)}</span> pacientes</p>`;
}

function miosHtml(cartera) {
  if (cartera.mios.length === 0) {
    const texto = cartera.q ? 'No se encontraron pacientes.' : 'Aún no tiene pacientes en su cartera.';
    return `<p class="text-muted">${texto}</p>`;
  }
  const filas = cartera.mios.map((p) => `<tr>
      <td class="texto-largo">${escapar(p.nombre)}</td>
      <td>${escapar(p.nit)}</td>
      <td>${escapar(p.telefono)}</td>
    </tr>`).join('');
  return `<div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th scope="col">Nombre</th><th scope="col">NIT</th><th scope="col">Teléfono</th></tr></thead>
      <tbody>${filas}</tbody>
    </table>
  </div>`;
}

function disponiblesHtml(cartera) {
  if (cartera.disponibles.length === 0) {
    return '<p class="text-muted mb-0">No hay pacientes disponibles.</p>';
  }
  const filas = cartera.disponibles.map((p) => `<li class="list-group-item d-flex justify-content-between align-items-center gap-2">
      <span class="texto-largo">${escapar(p.nombre)}</span>
    </li>`).join('');
  return `<ul class="list-group list-group-flush">${filas}</ul>`;
}

// ---------- Vista ----------

export default {
  titulo: 'Pacientes',

  async cargar(params) {
    // Las tres en paralelo; el contador tiene su propio manejo de errores y no tumba a las listas.
    const [mios, disponibles, resumen] = await Promise.all([pedirMios(''), pedirDisponibles(), pedirContador()]);
    guardarCartera({ mios, disponibles, resumen, q: '' });
  },

  render(estado) {
    const cartera = estado.datos.cartera;
    if (!cartera) return '';

    return `<div class="row g-3">
      <section class="col-12 col-lg-7">
        <h1 class="h4 mb-1">Mis pacientes</h1>
        ${contadorHtml(cartera.resumen)}
        ${miosHtml(cartera)}
      </section>
      <section class="col-12 col-lg-5">
        <div class="card">
          <div class="card-body">
            <h2 class="h5 card-title">Pacientes disponibles</h2>
            ${disponiblesHtml(cartera)}
          </div>
        </div>
      </section>
    </div>`;
  },

  acciones: {},

  formularios: {}
};
