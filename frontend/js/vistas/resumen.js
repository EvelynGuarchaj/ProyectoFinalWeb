// Vista #/resumen (F5): resumen del asesor para cualquier mes.
// Spec 22. Las cifras las calcula la API; aquí solo se les da formato.
import { estado, cambiar } from '../estado.js';
import { pedir } from '../api.js';
import { escapar, quetzales } from '../util.js';

// 'Cifras de octubre de 2026'. Se usa UTC en los dos lados para que el texto
// no dependa de la zona horaria de la computadora.
const formatoMes = new Intl.DateTimeFormat('es-GT', { month: 'long', year: 'numeric', timeZone: 'UTC' });

function nombreMes(mes) {
  const fecha = new Date(`${mes}-01T00:00:00Z`);
  if (Number.isNaN(fecha.getTime())) return mes;
  return formatoMes.format(fecha);
}

function tarjeta(titulo, valor, nota = '') {
  return `
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="card h-100">
        <div class="card-body">
          <h2 class="h6 card-title text-muted">${titulo}</h2>
          <p class="fs-3 fw-semibold mb-0">${valor}</p>
          ${nota ? `<small class="text-muted">${nota}</small>` : ''}
        </div>
      </div>
    </div>`;
}

export default {
  titulo: 'Resumen del mes',

  // Sin ?mes=: la API usa el mes actual de Guatemala y el selector toma el mes que devuelve (F5).
  async cargar() {
    try {
      const r = await pedir('GET', '/reportes/resumen');
      cambiar({
        datos: { ...estado.datos, resumen: r },
        formularios: { ...estado.formularios, mes: { valores: { mes: r.mes } } }
      });
    } catch (e) {
      cambiar({ datos: { ...estado.datos, resumen: null } });
      throw e; // app.js lo muestra con mostrarError (con "Reintentar" si la API está apagada)
    }
  },

  render(estado) {
    const r = estado.datos.resumen;
    const valores = (estado.formularios.mes && estado.formularios.mes.valores) || { mes: '' };

    const formulario = `
      <form data-form="mes" class="row g-2 align-items-end mb-3">
        <div class="col-12 col-sm-auto">
          <label for="resumen-mes" class="form-label">Mes</label>
          <input type="month" name="mes" id="resumen-mes" class="form-control" value="${escapar(valores.mes)}">
        </div>
        <div class="col-12 col-sm-auto">
          <button type="submit" class="btn btn-primary w-100">Ver resumen</button>
        </div>
      </form>`;

    if (!r) {
      return `
        <h1 class="h4 mb-3">Resumen del mes</h1>
        ${formulario}
        <p class="text-muted">No se pudo cargar el resumen.</p>`;
    }

    return `
      <h1 class="h4 mb-3">Resumen del mes</h1>
      ${formulario}
      <p class="text-muted mb-3">Cifras de ${escapar(nombreMes(r.mes))}</p>
      <div class="row g-3">
        ${tarjeta('Planes aceptados', escapar(r.ventas))}
        ${tarjeta('Total vendido', quetzales(r.total))}
        ${tarjeta('Pronóstico', quetzales(r.pronostico), 'de sus planes abiertos, sin importar el mes')}
        ${tarjeta('Cartera', `${escapar(r.cartera.clientes)} de ${escapar(r.cartera.maximo)}`)}
      </div>`;
  },

  acciones: {},

  // formularios.mes llega en el Paso 2.
  formularios: {}
};
