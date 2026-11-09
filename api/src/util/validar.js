// Ayudas para revisar lo que llega en la petición. Las rutas las usan antes de tocar la base.
import { noEncontrado, peticionMala } from './errores.js';

/**
 * Exige que la petición traiga un cuerpo JSON con forma de objeto.
 * Sin cuerpo, `null` o un arreglo → 400.
 */
export function exigirCuerpo(req) {
  const cuerpo = req.body;
  if (cuerpo === undefined || cuerpo === null || typeof cuerpo !== 'object' || Array.isArray(cuerpo)) {
    throw peticionMala('Falta el cuerpo de la petición.');
  }
  return cuerpo;
}

/**
 * Convierte el `:id` de la ruta en número. Si no es un entero positivo válido
 * ("abc", "0", "-1", "1.5", "007", demasiado grande) responde 404, igual que un recurso inexistente.
 */
export function idDeRuta(valor) {
  if (typeof valor !== 'string' || !/^[1-9][0-9]*$/.test(valor)) throw noEncontrado();
  const id = Number(valor);
  if (!Number.isSafeInteger(id)) throw noEncontrado();
  return id;
}

/**
 * Si `valor` es texto, lo recorta y lo devuelve cuando su largo está entre `min` y `max`.
 * En cualquier otro caso (no es texto, muy corto o muy largo) devuelve `null`.
 */
export function texto(valor, min, max) {
  if (typeof valor !== 'string') return null;
  const recortado = valor.trim();
  if (recortado.length < min || recortado.length > max) return null;
  return recortado;
}
