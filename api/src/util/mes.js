// Mes del resumen (spec 15): formato AAAA-MM y mes actual en hora de Guatemala.
// Guatemala es UTC−6 fijo, sin horario de verano: lo mismo que `- INTERVAL 6 HOUR` en el SQL.

export const FORMATO_MES = /^\d{4}-(0[1-9]|1[0-2])$/;

// true solo si es un texto con el formato AAAA-MM (mes 01 a 12). Sin recortar espacios.
export function esMesValido(texto) {
  return typeof texto === 'string' && FORMATO_MES.test(texto);
}

// Mes actual en Guatemala como AAAA-MM. Recibe `ahora` para probar sin depender del reloj.
export function mesActualGuatemala(ahora = new Date()) {
  return new Date(ahora.getTime() - 6 * 3600 * 1000).toISOString().slice(0, 7);
}
