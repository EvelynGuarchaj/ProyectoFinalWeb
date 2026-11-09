// Etapas del embudo y sus transiciones (sección 5 de docs/especificacion.md, spec 11).

export const ETAPAS = Object.freeze(['prospecto', 'calificado', 'propuesta', 'negociacion', 'ganada', 'perdida']);
export const ABIERTAS = Object.freeze(['prospecto', 'calificado', 'propuesta', 'negociacion']);
export const CERRADAS = Object.freeze(['ganada', 'perdida']);

// Porcentajes enteros, igual que Oportunidad::PROBABILIDAD del panel (spec 01).
export const PROBABILIDAD = Object.freeze({
  prospecto: 10, calificado: 25, propuesta: 50, negociacion: 75, ganada: 100, perdida: 0,
});

// true solo para: la siguiente abierta, negociacion → ganada y cualquier abierta → perdida.
// Todo lo demás (saltos, retrocesos, misma etapa, salir de cerrada, valores raros) es false.
export function transicionPermitida(actual, nueva) {
  const i = ABIERTAS.indexOf(actual);
  if (i === -1) return false;
  if (nueva === 'perdida') return true;
  if (actual === 'negociacion') return nueva === 'ganada';
  return nueva === ABIERTAS[i + 1];
}
