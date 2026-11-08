// Utilidades compartidas por todas las vistas (contrato de docs/plan.md §3.4).
import { cambiar } from './estado.js';

const ZONA = 'America/Guatemala';

const ENTIDADES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

// Todo texto que viene de la API pasa por aquí antes de entrar en un innerHTML (S7).
// Sirve también dentro de atributos (value="…", data-id="…").
export function escapar(texto) {
  if (texto === null || texto === undefined) return '';
  return String(texto).replace(/[&<>"']/g, (c) => ENTIDADES[c]);
}

const formatoQuetzales = new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ' });

// 9250 → 'Q9,250.00'. Algunos motores ponen un espacio (a veces no separable) entre Q y el número;
// se quita para que el texto sea igual en todos los navegadores.
export function quetzales(n) {
  return formatoQuetzales.format(Number(n) || 0).replace(/\s/g, '');
}

const formatoFecha = new Intl.DateTimeFormat('es-GT', {
  timeZone: ZONA,
  day: 'numeric',
  month: 'short',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
  hourCycle: 'h23'
});

// '2026-10-05T16:00:00.000Z' → '5 oct 2026, 10:00' (hora de Guatemala)
export function fechaGT(iso) {
  if (!iso) return '';
  const fecha = new Date(iso);
  if (Number.isNaN(fecha.getTime())) return '';
  return formatoFecha.format(fecha);
}

// Valor de un <input type="datetime-local"> en hora de Guatemala (UTC−6 fijo):
// '2026-12-01T10:00' → '2026-12-01T10:00:00-06:00'
export function aISO(valorLocal) {
  if (!valorLocal) return '';
  return `${valorLocal}:00-06:00`;
}

const formatoMes = new Intl.DateTimeFormat('en-CA', { timeZone: ZONA, year: 'numeric', month: '2-digit' });

// Mes actual en Guatemala: 'AAAA-MM'
export function mesActualGT() {
  const partes = Object.fromEntries(formatoMes.formatToParts(new Date()).map((p) => [p.type, p.value]));
  return `${partes.year}-${partes.month}`;
}

// HTML del mensaje de error de un campo (escapado), o ''.
export function errorCampo(errores, campo) {
  const mensaje = errores && errores[campo];
  return mensaje ? `<div class="invalid-feedback">${escapar(mensaje)}</div>` : '';
}

// ' is-invalid' si el campo tiene error, o ''.
export function claseError(errores, campo) {
  return errores && errores[campo] ? ' is-invalid' : '';
}

// Pone el mensaje del error como aviso. Si la API no responde, el aviso trae "Reintentar".
// El 401 en plena sesión ya dejó su propio aviso ("Su sesión terminó…"), así que no se toca.
export function mostrarError(e) {
  if (e && e.sesionTerminada) return;
  const texto = (e && e.message) || 'Ocurrió un error inesperado. Intente de nuevo más tarde.';
  cambiar({ aviso: { tipo: 'danger', texto, reintentar: e && e.status === 0 } });
}
