// Casos de NIT de la sección 10 de docs/especificacion.md (y la decisión del spec 06
// sobre puntos y letras). El panel (app/Support/Nit.php) debe dar el mismo resultado.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { compactar, normalizar, esConsumidorFinal, esValido } from '../src/util/nit.js';

test('452 1817-k se guarda como 4521817K y es válido', () => {
  assert.equal(normalizar('452 1817-k'), '4521817K');
  assert.equal(esValido(normalizar('452 1817-k')), true);
});

test('4521817-K se guarda como 4521817K y es válido (repetido del anterior)', () => {
  assert.equal(normalizar('4521817-K'), '4521817K');
  assert.equal(esValido(normalizar('4521817-K')), true);
  assert.equal(normalizar('4521817-K'), normalizar('452 1817-k'));
});

test('00012345679 se guarda como 12345679 y es válido', () => {
  assert.equal(normalizar('00012345679'), '12345679');
  assert.equal(esValido(normalizar('00012345679')), true);
});

test('cf y CF son consumidor final', () => {
  assert.equal(esConsumidorFinal('cf'), true);
  assert.equal(esConsumidorFinal('CF'), true);
  assert.equal(esConsumidorFinal(' cf '), true);
  assert.equal(esConsumidorFinal('4521817K'), false);
  assert.equal(esConsumidorFinal(null), false);
});

test('4521817-1 es inválido (verificador incorrecto)', () => {
  assert.equal(normalizar('4521817-1'), '45218171');
  assert.equal(esValido(normalizar('4521817-1')), false);
});

test('un NIT con puntos o con otra letra que no sea la K final es inválido', () => {
  assert.equal(esValido(normalizar('4521.817-K')), false);
  assert.equal(esValido(normalizar('4521817-A')), false);
});

test('largo: menos de 2 o más de 12 caracteres es inválido', () => {
  assert.equal(esValido(normalizar('0')), false);
  assert.equal(esValido(normalizar('K')), false);
  assert.equal(esValido('1234567890123'), false);
});

test('compactar quita espacios y guiones y pasa a mayúsculas, pero no quita ceros', () => {
  assert.equal(compactar('0452 18-k'), '045218K');
});
