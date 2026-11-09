// Regla propia del sector (sección 2 de docs/especificacion.md, spec 13).
// Un plan con un tratamiento que requiere evaluación no llega a propuesta, negociacion
// ni ganada si el paciente no tiene al menos una `reunion` completada.
// Las dos funciones reciben la conexión de la transacción (`cx`); nunca usan consulta().

import { conflicto } from './errores.js';

export const MENSAJE_EVALUACION =
  'El paciente necesita una cita de evaluación completada antes de recibir el presupuesto.';

// Cuentan también las líneas de productos inactivos: la regla mira el plan, no el catálogo.
const SQL_PLAN_REQUIERE_EVALUACION = `
  SELECT 1 FROM oportunidad_productos l JOIN productos p ON p.id = l.producto_id
   WHERE l.oportunidad_id = ? AND p.requiere_evaluacion = 1 LIMIT 1`;

// De cualquier asesor (sección 2.4).
const SQL_PACIENTE_CON_EVALUACION = `
  SELECT 1 FROM actividades
   WHERE cliente_id = ? AND tipo = 'reunion' AND completada = 1 LIMIT 1`;

// true si el paciente tiene al menos una `reunion` completada, la haya registrado quien sea.
export async function pacienteTieneEvaluacion(cx, clienteId) {
  const [filas] = await cx.execute(SQL_PACIENTE_CON_EVALUACION, [clienteId]);
  return filas.length > 0;
}

// No devuelve nada si el plan puede avanzar; lanza 409 si no.
// Si el plan no tiene líneas que requieren evaluación, no consulta `actividades`.
export async function verificarEvaluacion(cx, oportunidadId, clienteId) {
  const [lineas] = await cx.execute(SQL_PLAN_REQUIERE_EVALUACION, [oportunidadId]);
  if (lineas.length === 0) return;
  if (!(await pacienteTieneEvaluacion(cx, clienteId))) {
    throw conflicto(MENSAJE_EVALUACION);
  }
}
