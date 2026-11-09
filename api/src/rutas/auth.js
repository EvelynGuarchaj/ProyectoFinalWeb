// POST /api/auth/login: el asesor inicia sesión y recibe un JWT (A1).
// Es la única ruta que no exige token. La API solo verifica hashes, nunca los crea (S1).
import { Router } from 'express';
import bcrypt from 'bcryptjs';
import jwt from 'jsonwebtoken';
import { config } from '../config.js';
import { consulta } from '../db.js';
import { ErrorHttp, invalido } from '../util/errores.js';
import { exigirCuerpo, texto } from '../util/validar.js';

// Hash bcrypt de un texto aleatorio que nadie conoce, con el mismo costo que Laravel (12).
// Si el correo no existe se compara contra él igual, para que la respuesta tarde lo mismo
// y el tiempo no revele qué correos están registrados.
const HASH_FALSO = '$2y$12$ehDzI95aLMsCMMZKwS1msuIMBLlP80MrIEQXDggwcVYLuxwFcaklO';

const credencialesIncorrectas = () => new ErrorHttp(401, 'Correo o contraseña incorrectos.');

export const rutasAuth = Router();

rutasAuth.post('/login', async (req, res) => {
  const cuerpo = exigirCuerpo(req);

  // El correo se recorta; la clave no, porque puede tener espacios a propósito.
  const correo = texto(cuerpo.correo, 1, 255);
  const clave = cuerpo.clave;

  const errores = {};
  if (correo === null) errores.correo = 'Escriba su correo.';
  if (typeof clave !== 'string' || clave.length < 1) errores.clave = 'Escriba su contraseña.';
  if (Object.keys(errores).length > 0) throw invalido(errores);

  // La intercalación de MySQL ya ignora mayúsculas en el correo.
  const filas = await consulta(
    'SELECT id, name, email, password, rol, activo FROM users WHERE email = ? LIMIT 1',
    [correo]
  );
  const usuario = filas[0];

  if (!usuario) {
    await bcrypt.compare(clave, HASH_FALSO);
    throw credencialesIncorrectas();
  }
  if (!(await bcrypt.compare(clave, usuario.password))) throw credencialesIncorrectas();

  // El rol va antes que `activo`: a un coordinador o administrador lo mandamos al panel.
  if (usuario.rol !== 'vendedor') {
    throw new ErrorHttp(403, 'Esta cuenta no es de asesor. Use el panel de la clínica.');
  }
  if (!usuario.activo) {
    throw new ErrorHttp(403, 'Su cuenta está desactivada. Comuníquese con el administrador.');
  }

  // El token solo lleva el id: el middleware vuelve a leer al usuario en cada petición.
  const token = jwt.sign({}, config.jwt.secreto, {
    algorithm: 'HS256',
    subject: String(usuario.id),
    expiresIn: config.jwt.expira,
  });

  res.json({
    token,
    usuario: { id: usuario.id, nombre: usuario.name, correo: usuario.email },
  });
});
