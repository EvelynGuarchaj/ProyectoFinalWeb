// Protege todas las rutas de /api salvo /auth (A2): exige un JWT válido y vuelve a leer
// al usuario en cada petición, para que una cuenta desactivada pierda el acceso de inmediato.
import jwt from 'jsonwebtoken';
import { config } from '../config.js';
import { consulta } from '../db.js';
import { ErrorHttp } from '../util/errores.js';

const PREFIJO = 'Bearer ';

export async function exigirToken(req, res, next) {
  // 1. La cabecera debe ser "Authorization: Bearer <token>".
  const cabecera = req.get('Authorization');
  const token = cabecera?.startsWith(PREFIJO) ? cabecera.slice(PREFIJO.length).trim() : '';
  if (!token) throw new ErrorHttp(401, 'Falta el token de acceso.');

  // 2. Firma, vencimiento y algoritmo. Solo HS256: rechaza `none` y cualquier otro.
  let id;
  try {
    const cuerpo = jwt.verify(token, config.jwt.secreto, { algorithms: ['HS256'] });
    id = /^[1-9][0-9]*$/.test(cuerpo.sub ?? '') ? Number(cuerpo.sub) : NaN;
  } catch {
    id = NaN;
  }
  if (!Number.isSafeInteger(id)) throw new ErrorHttp(401, 'El token no es válido o ya venció.');

  // 3. El usuario se lee en cada petición. Un error de la base sale de aquí como 500, no como 401.
  const filas = await consulta('SELECT id, name, email, rol, activo FROM users WHERE id = ? LIMIT 1', [id]);
  const usuario = filas[0];
  if (!usuario || !usuario.activo || usuario.rol !== 'vendedor') {
    throw new ErrorHttp(401, 'Su cuenta ya no tiene acceso. Ingrese de nuevo.');
  }

  // 4. Lo que las rutas necesitan del asesor, sin columnas internas.
  req.usuario = { id: usuario.id, nombre: usuario.name, correo: usuario.email };
  next();
}
