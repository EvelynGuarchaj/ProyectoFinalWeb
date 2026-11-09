// Casos del mes del resumen, paso 2 del spec 15 (specs/15-api-resumen.md).
// El mes se escribe AAAA-MM y, si no llega, es el mes actual en hora de Guatemala (UTC−6 fijo).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { esMesValido, mesActualGuatemala } from '../src/util/mes.js';

test('meses válidos', () => {
  for (const mes of ['2026-10', '2026-01', '2026-12']) {
    assert.equal(esMesValido(mes), true, mes);
  }
});

test('textos con otro formato son inválidos', () => {
  for (const mes of ['2026-13', '2026-00', '2026-1', '26-10', '', ' 2026-10', '2026-10 ', '2026/10']) {
    assert.equal(esMesValido(mes), false, JSON.stringify(mes));
  }
});

test('lo que no es texto es inválido', () => {
  for (const mes of [undefined, null, 202610, ['2026-10']]) {
    assert.equal(esMesValido(mes), false, JSON.stringify(mes));
  }
});

test('01-nov 03:30 UTC todavía es octubre en Guatemala (venta "Equipo para sucursal")', () => {
  assert.equal(mesActualGuatemala(new Date('2026-11-01T03:30:00Z')), '2026-10');
});

test('01-nov 06:00 UTC ya es noviembre en Guatemala', () => {
  assert.equal(mesActualGuatemala(new Date('2026-11-01T06:00:00Z')), '2026-11');
});

test('el cambio de año también va en hora de Guatemala', () => {
  assert.equal(mesActualGuatemala(new Date('2027-01-01T05:59:59Z')), '2026-12');
});
