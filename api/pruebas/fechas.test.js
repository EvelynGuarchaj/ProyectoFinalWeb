// Casos de fecha de la sección 2.2 del spec 08 (specs/08-api-actividades.md).
// La API solo acepta fechas ISO 8601 con zona horaria; sin zona → 422.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { esISOConZona } from '../src/util/fechas.js';

// Las cuatro decididas por la integrante.
test('sin zona es inválida', () => {
  assert.equal(esISOConZona('2026-12-01T10:00'), false);
});

test('con Z es válida', () => {
  assert.equal(esISOConZona('2026-12-01T10:00:00Z'), true);
});

test('con -06:00 es válida y equivale a las 16:00 UTC', () => {
  assert.equal(esISOConZona('2026-12-01T10:00:00-06:00'), true);
  assert.equal(new Date('2026-12-01T10:00:00-06:00').toISOString(), '2026-12-01T16:00:00.000Z');
});

test('el 30 de febrero no existe', () => {
  assert.equal(esISOConZona('2026-02-30T10:00:00Z'), false);
});

// Las demás reglas de la sección 2.1.
test('sin segundos y con milisegundos de 1 a 3 dígitos es válida', () => {
  assert.equal(esISOConZona('2026-12-01T10:00Z'), true);
  assert.equal(esISOConZona('2026-12-01T10:00:00.5Z'), true);
  assert.equal(esISOConZona('2026-12-01T10:00:00.123+05:30'), true);
});

test('el 29 de febrero de un año bisiesto es válido', () => {
  assert.equal(esISOConZona('2024-02-29T10:00:00Z'), true);
});

test('mes 13, hora 24, zona +15:00 o espacio en lugar de T son inválidos', () => {
  assert.equal(esISOConZona('2026-13-01T10:00:00Z'), false);
  assert.equal(esISOConZona('2026-12-01T24:00:00Z'), false);
  assert.equal(esISOConZona('2026-12-01T10:00:00+15:00'), false);
  assert.equal(esISOConZona('2026-12-01 10:00:00Z'), false);
});

test('texto vacío, null o un número son inválidos', () => {
  assert.equal(esISOConZona(''), false);
  assert.equal(esISOConZona(null), false);
  assert.equal(esISOConZona(20261201), false);
});
