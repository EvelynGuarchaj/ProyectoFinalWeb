// Vista #/cartera (F2): pacientes del asesor, contador "x de 10" y pacientes disponibles.
// Spec 18. La búsqueda, el contador y las validaciones los hace el servidor; aquí solo se muestran.
import { estado, cambiar } from '../estado.js';
import { pedir } from '../api.js';
import { escapar, errorCampo, claseError, mostrarError } from '../util.js';

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

// Quita formularios del estado sin tocar los de otras vistas.
function sinFormularios(...nombres) {
  const resto = { ...estado.formularios };
  for (const nombre of nombres) delete resto[nombre];
  return resto;
}

// ---------- Partes de la pantalla ----------

function contadorHtml(resumen) {
  if (!resumen) return '<p class="text-muted mb-3">Contador no disponible</p>';
  return `<p class="mb-3"><span class="badge text-bg-primary fs-6">${escapar(resumen.clientes)} de ${escapar(resumen.maximo)}</span> pacientes</p>`;
}

function buscadorHtml(estado, cartera) {
  const valores = (estado.formularios.buscar && estado.formularios.buscar.valores) || {};
  const limpiar = cartera.q
    ? '<button type="button" class="btn btn-outline-secondary" data-accion="limpiarBusqueda">Limpiar</button>'
    : '';
  return `<form data-form="buscar" role="search" class="mb-3">
    <label for="buscar-q" class="form-label">Buscar por nombre o NIT</label>
    <div class="input-group">
      <input type="search" id="buscar-q" name="q" class="form-control" value="${escapar(valores.q)}">
      <button type="submit" class="btn btn-outline-primary">Buscar</button>
      ${limpiar}
    </div>
  </form>`;
}

function campoHtml(id, etiqueta, tipo, valores, errores, extra = '', ayuda = '') {
  const ayudaHtml = ayuda ? `<div id="alta-${id}-ayuda" class="form-text">${ayuda}</div>` : '';
  const describe = ayuda ? ` aria-describedby="alta-${id}-ayuda"` : '';
  return `<div class="mb-3">
    <label for="alta-${id}" class="form-label">${etiqueta}</label>
    <input type="${tipo}" id="alta-${id}" name="${id}" class="form-control${claseError(errores, id)}"
           value="${escapar(valores[id])}" ${extra}${describe}>
    ${errorCampo(errores, id)}
    ${ayudaHtml}
  </div>`;
}

function altaHtml(estado) {
  const form = estado.formularios.alta || {};
  const valores = form.valores || {};
  const errores = form.errores || {};
  // El 409 (NIT o teléfono repetido, cartera llena) va encima del formulario.
  const error = form.error
    ? `<div class="alert alert-danger texto-largo" role="alert">${escapar(form.error)}</div>`
    : '';
  // novalidate: los mensajes de error los da el servidor (L7), no el navegador.
  return `<div class="card mb-3">
    <div class="card-body">
      <h2 class="h5 card-title">Agregar paciente</h2>
      ${error}
      <form data-form="alta" novalidate>
        ${campoHtml('nombre', 'Nombre', 'text', valores, errores, 'autocomplete="off"')}
        ${campoHtml('nit', 'NIT', 'text', valores, errores, 'autocomplete="off"', 'Con su dígito verificador, o CF.')}
        ${campoHtml('telefono', 'Teléfono', 'tel', valores, errores, 'inputmode="tel" autocomplete="off"')}
        ${campoHtml('correo', 'Correo (opcional)', 'email', valores, errores, 'autocomplete="off"')}
        <button type="submit" class="btn btn-primary w-100">Agregar paciente</button>
      </form>
    </div>
  </div>`;
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
    // Al entrar, la búsqueda empieza vacía: se borra también el texto del buscador para que
    // el campo y la lista coincidan (el spec 10 conserva estado.formularios al navegar).
    cambiar({ formularios: sinFormularios('buscar') });
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
        ${buscadorHtml(estado, cartera)}
        ${miosHtml(cartera)}
      </section>
      <section class="col-12 col-lg-5">
        ${altaHtml(estado)}
        <div class="card">
          <div class="card-body">
            <h2 class="h5 card-title">Pacientes disponibles</h2>
            ${disponiblesHtml(cartera)}
          </div>
        </div>
      </section>
    </div>`;
  },

  acciones: {
    async limpiarBusqueda() {
      cambiar({ formularios: sinFormularios('buscar') });
      const mios = await pedirMios('');
      guardarCartera({ mios, q: '' });
    }
  },

  formularios: {
    // La búsqueda la hace el servidor: GET /clientes?q=… (sin q si queda vacío).
    async buscar(datos) {
      const q = (datos.q || '').trim();
      // Se guarda lo escrito ANTES de pedir: cada cambio de estado vuelve a dibujar.
      cambiar({ formularios: { ...estado.formularios, buscar: { valores: { q } } } });
      const mios = await pedirMios(q);
      guardarCartera({ mios, q });
    },

    async alta(datos) {
      const valores = {
        nombre: datos.nombre || '',
        nit: datos.nit || '',
        telefono: datos.telefono || '',
        correo: datos.correo || ''
      };
      // Se guarda lo escrito ANTES de pedir: cada cambio de estado vuelve a dibujar.
      cambiar({ formularios: { ...estado.formularios, alta: { valores, errores: {}, error: null } } });

      let paciente;
      try {
        // Correo vacío viaja como "": la API lo guarda como null (spec 06).
        paciente = await pedir('POST', '/clientes', valores);
      } catch (e) {
        if (e.status === 422) {
          cambiar({ formularios: { ...estado.formularios, alta: { valores, errores: e.errores, error: null } } });
        } else if (e.status === 409) {
          cambiar({ formularios: { ...estado.formularios, alta: { valores, errores: {}, error: e.message } } });
        } else {
          mostrarError(e); // API apagada, 500 o sesión terminada; lo escrito sigue guardado
        }
        return;
      }

      // 201: formulario vacío, búsqueda limpia, lista y contador de nuevo.
      cambiar({ formularios: sinFormularios('alta', 'buscar') });
      const [mios, resumen] = await Promise.all([pedirMios(''), pedirContador()]);
      guardarCartera({ mios, resumen, q: '' });
      // Sin escapar aquí: el layout ya pasa aviso.texto por escapar().
      cambiar({ aviso: { tipo: 'success', texto: `Se agregó a ${paciente.nombre} a su cartera.` } });
    }
  }
};
