// Último middleware de app.js: todo error termina aquí y sale como JSON, nunca como HTML.
// Express 5 trae aquí también los errores de las funciones async (no hace falta try/catch en las rutas).
import { ErrorHttp } from '../util/errores.js';

const MENSAJE_500 = 'Ocurrió un error inesperado. Intente de nuevo más tarde.';

// eslint-disable-next-line no-unused-vars
export function manejarErrores(err, req, res, next) {
  // Si la respuesta ya empezó a enviarse, no se puede cambiar: Express cierra la conexión.
  if (res.headersSent) return next(err);

  // express.json() no pudo leer el cuerpo.
  if (err.type === 'entity.parse.failed') {
    return res.status(400).json({ error: 'El cuerpo de la petición no es JSON válido.' });
  }

  // Error del contrato lanzado por una ruta o un middleware.
  if (err instanceof ErrorHttp) {
    const cuerpo = err.errores ? { errores: err.errores } : { error: err.mensaje };
    return res.status(err.status).json(cuerpo);
  }

  // Cualquier otra cosa (SQL, base caída, bug): el detalle solo en la consola del servidor.
  console.error(err);
  return res.status(500).json({ error: MENSAJE_500 });
}
