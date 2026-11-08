// Errores "esperados" de la API: los que tienen un código y un mensaje del contrato
// (sección 4.2 de docs/especificacion.md). Una ruta los lanza con `throw` y
// middleware/errores.js los convierte en la respuesta JSON.

export class ErrorHttp extends Error {
  /**
   * @param {number} status   Código HTTP (400, 401, 403, 404, 409, 422).
   * @param {string} mensaje  Texto para { error }. Se ignora si hay `errores`.
   * @param {Record<string, string>} [errores]  Mensajes por campo para un 422: { campo: 'mensaje' }.
   */
  constructor(status, mensaje, errores) {
    super(mensaje);
    this.name = 'ErrorHttp';
    this.status = status;
    this.mensaje = mensaje;
    this.errores = errores;
  }
}

// 404: el recurso no existe o es de otro asesor (lo ajeno responde igual que lo inexistente).
export const noEncontrado = () => new ErrorHttp(404, 'No se encontró el recurso.');

// 409: la petición choca con el estado actual (repetido, cartera llena, plan cerrado…).
export const conflicto = (mensaje) => new ErrorHttp(409, mensaje);

// 422: los datos violan una regla. `errores` es { campo: 'mensaje' }.
export const invalido = (errores) => new ErrorHttp(422, 'Los datos no son válidos.', errores);

// 400: la petición está mal armada (sin cuerpo, filtro con valor inexistente…).
export const peticionMala = (mensaje) => new ErrorHttp(400, mensaje);
