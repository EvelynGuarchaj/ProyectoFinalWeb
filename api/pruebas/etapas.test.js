// Casos de etapas de la sección 2.2 del spec 11 (specs/11-api-oportunidades.md).
// Transiciones del embudo según la sección 5 de docs/especificacion.md.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ETAPAS, ABIERTAS, CERRADAS, PROBABILIDAD, transicionPermitida } from '../src/util/etapas.js';

test('avanzar a la siguiente abierta está permitido', () => {
  assert.equal(transicionPermitida('prospecto', 'calificado'), true);
  assert.equal(transicionPermitida('calificado', 'propuesta'), true);
  assert.equal(transicionPermitida('propuesta', 'negociacion'), true);
});

test('negociacion → ganada está permitido', () => {
  assert.equal(transicionPermitida('negociacion', 'ganada'), true);
});

test('cualquier abierta → perdida está permitido', () => {
  for (const actual of ['prospecto', 'calificado', 'propuesta', 'negociacion']) {
    assert.equal(transicionPermitida(actual, 'perdida'), true, `${actual} → perdida`);
  }
});

test('los saltos no están permitidos', () => {
  assert.equal(transicionPermitida('prospecto', 'propuesta'), false);
  assert.equal(transicionPermitida('calificado', 'negociacion'), false);
});

test('los retrocesos no están permitidos', () => {
  assert.equal(transicionPermitida('calificado', 'prospecto'), false);
  assert.equal(transicionPermitida('negociacion', 'propuesta'), false);
});

test('pedir la misma etapa no está permitido', () => {
  assert.equal(transicionPermitida('negociacion', 'negociacion'), false);
  assert.equal(transicionPermitida('prospecto', 'prospecto'), false);
});

test('ganar desde una etapa que no es negociacion no está permitido', () => {
  assert.equal(transicionPermitida('prospecto', 'ganada'), false);
  assert.equal(transicionPermitida('propuesta', 'ganada'), false);
});

test('salir de una etapa cerrada no está permitido', () => {
  assert.equal(transicionPermitida('ganada', 'perdida'), false);
  assert.equal(transicionPermitida('perdida', 'prospecto'), false);
  assert.equal(transicionPermitida('ganada', 'ganada'), false);
});

test('valores que no son una de las seis etapas exactas no están permitidos', () => {
  for (const nueva of ['Calificado', 'volando', '', undefined, null, 3]) {
    assert.equal(transicionPermitida('prospecto', nueva), false, `prospecto → ${String(nueva)}`);
  }
});

test('PROBABILIDAD tiene exactamente los porcentajes del panel', () => {
  assert.deepEqual(PROBABILIDAD, {
    prospecto: 10, calificado: 25, propuesta: 50, negociacion: 75, ganada: 100, perdida: 0,
  });
});

test('ETAPAS va en el orden del ENUM y ABIERTAS + CERRADAS = ETAPAS sin repetidos', () => {
  assert.deepEqual(ETAPAS, ['prospecto', 'calificado', 'propuesta', 'negociacion', 'ganada', 'perdida']);
  const juntas = [...ABIERTAS, ...CERRADAS];
  assert.deepEqual(juntas, ETAPAS);
  assert.equal(new Set(juntas).size, juntas.length);
});
