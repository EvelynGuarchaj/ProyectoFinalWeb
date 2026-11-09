// Casos de teléfono de la sección 10 de docs/especificacion.md.
// El panel (app/Support/Telefono.php) debe dar el mismo resultado.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { normalizar, esValido } from '../src/util/telefono.js';

test('+502 5555-1234 se guarda como 55551234 y es válido', () => {
  assert.equal(normalizar('+502 5555-1234'), '55551234');
  assert.equal(esValido(normalizar('+502 5555-1234')), true);
});

test('(502) 7761.2345 se guarda como 77612345 y es válido', () => {
  assert.equal(normalizar('(502) 7761.2345'), '77612345');
  assert.equal(esValido(normalizar('(502) 7761.2345')), true);
});

test('50255551234 se guarda como 55551234 y es válido', () => {
  assert.equal(normalizar('50255551234'), '55551234');
  assert.equal(esValido(normalizar('50255551234')), true);
});

test('5021 2345 se guarda como 50212345 y es válido (no se quita el 502)', () => {
  assert.equal(normalizar('5021 2345'), '50212345');
  assert.equal(esValido(normalizar('5021 2345')), true);
});

test('1234-5678 es inválido (empieza con 1)', () => {
  assert.equal(normalizar('1234-5678'), '12345678');
  assert.equal(esValido(normalizar('1234-5678')), false);
});

test('5555123 es inválido (7 dígitos)', () => {
  assert.equal(normalizar('5555123'), '5555123');
  assert.equal(esValido(normalizar('5555123')), false);
});
