// Casos del paso 1 del spec 13 (specs/13-api-regla-propia.md).
// Regla propia: sección 2 de docs/especificacion.md. Sin base: una conexión falsa
// responde según el texto SQL y anota cada llamada.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ErrorHttp } from '../src/util/errores.js';
import {
  MENSAJE_EVALUACION,
  pacienteTieneEvaluacion,
  verificarEvaluacion,
} from '../src/util/reglaPropia.js';

// Conexión falsa: `lineaConEvaluacion` y `citaCompletada` deciden si cada consulta trae una fila.
// Los valores imitan a MySQL: `SELECT 1` devuelve la fila { 1: 1 }.
function conexionFalsa({ lineaConEvaluacion = false, citaCompletada = false } = {}) {
  const llamadas = [];
  return {
    llamadas,
    async execute(sql, params) {
      llamadas.push({ sql, params });
      if (sql.includes('FROM oportunidad_productos')) {
        return [lineaConEvaluacion ? [{ 1: 1 }] : [], []];
      }
      if (sql.includes('FROM actividades')) {
        return [citaCompletada ? [{ 1: 1 }] : [], []];
      }
      throw new Error(`SQL inesperado: ${sql}`);
    },
  };
}

const consultoActividades = (cx) => cx.llamadas.some((l) => l.sql.includes('FROM actividades'));

test('plan sin líneas de evaluación: no lanza y no consulta actividades', async () => {
  const cx = conexionFalsa({ lineaConEvaluacion: false });
  await verificarEvaluacion(cx, 7, 3);
  assert.equal(cx.llamadas.length, 1);
  assert.deepEqual(cx.llamadas[0].params, [7]);
  assert.equal(consultoActividades(cx), false);
});

test('plan con línea de evaluación y paciente sin cita completada: 409 con el mensaje exacto', async () => {
  const cx = conexionFalsa({ lineaConEvaluacion: true, citaCompletada: false });
  await assert.rejects(verificarEvaluacion(cx, 7, 3), (error) => {
    assert.ok(error instanceof ErrorHttp);
    assert.equal(error.status, 409);
    assert.equal(error.mensaje, MENSAJE_EVALUACION);
    return true;
  });
  assert.equal(consultoActividades(cx), true);
});

test('plan con línea de evaluación y paciente con cita completada: no lanza', async () => {
  const cx = conexionFalsa({ lineaConEvaluacion: true, citaCompletada: true });
  await verificarEvaluacion(cx, 7, 3);
  assert.equal(cx.llamadas.length, 2);
});

test('pacienteTieneEvaluacion da true con una fila y pasa clienteId como parámetro', async () => {
  const cx = conexionFalsa({ citaCompletada: true });
  assert.equal(await pacienteTieneEvaluacion(cx, 42), true);
  assert.equal(cx.llamadas.length, 1);
  assert.deepEqual(cx.llamadas[0].params, [42]);
});

test('pacienteTieneEvaluacion da false sin filas', async () => {
  const cx = conexionFalsa({ citaCompletada: false });
  assert.equal(await pacienteTieneEvaluacion(cx, 42), false);
  assert.deepEqual(cx.llamadas[0].params, [42]);
});

test('el mensaje es el de la sección 2.3', () => {
  assert.equal(
    MENSAJE_EVALUACION,
    'El paciente necesita una cita de evaluación completada antes de recibir el presupuesto.',
  );
});
