// Validación de fechas que llegan a la API (spec 08, sección 2.1).
// Se exige ISO 8601 con zona horaria: la API guarda en UTC y no adivina la zona.

// AAAA-MM-DDTHH:MM, luego :SS opcional, .mmm opcional (solo si hay segundos)
// y la zona obligatoria: Z o ±HH:MM.
const FORMA = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2})(?:\.\d{1,3})?)?(?:Z|[+-](\d{2}):(\d{2}))$/;

function esBisiesto(anio) {
  return (anio % 4 === 0 && anio % 100 !== 0) || anio % 400 === 0;
}

function diasDelMes(anio, mes) {
  if (mes === 2) return esBisiesto(anio) ? 29 : 28;
  return [4, 6, 9, 11].includes(mes) ? 30 : 31;
}

/**
 * `true` solo si `texto` es una fecha ISO 8601 con zona que existe de verdad.
 * Primero revisa la forma y los rangos de cada parte; solo después usa `new Date`,
 * porque `new Date` acepta textos sin zona y "corrige" fechas como el 30 de febrero.
 */
export function esISOConZona(texto) {
  if (typeof texto !== 'string') return false;

  const partes = FORMA.exec(texto);
  if (!partes) return false;

  const [, a, m, d, h, min, s, zh, zm] = partes;
  const anio = Number(a);
  const mes = Number(m);
  const dia = Number(d);

  if (mes < 1 || mes > 12) return false;
  if (dia < 1 || dia > diasDelMes(anio, mes)) return false;
  if (Number(h) > 23 || Number(min) > 59) return false;
  if (s !== undefined && Number(s) > 59) return false;
  if (zh !== undefined && (Number(zh) > 14 || Number(zm) > 59)) return false;

  const fecha = new Date(texto);
  if (Number.isNaN(fecha.getTime())) return false;

  // Rango de DATETIME en MySQL, ya en UTC.
  const anioUTC = fecha.getUTCFullYear();
  return anioUTC >= 1000 && anioUTC <= 9999;
}
