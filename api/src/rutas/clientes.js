// Rutas de /api/clientes: la cartera de pacientes del asesor (A3).
// El dueño siempre sale de req.usuario.id (lo deja middleware/auth.js), nunca del cuerpo.
// SQL a mano con parámetros `?`: ningún dato de la petición se pega en el texto SQL (S3).
// Lo ajeno responde 404, igual que lo inexistente (S5).
import { Router } from 'express';
import { consulta } from '../db.js';
import { noEncontrado } from '../util/errores.js';
import { idDeRuta, texto } from '../util/validar.js';
import { compactar } from '../util/nit.js';
import * as formato from '../util/formato.js';

// Columnas que se devuelven de un paciente (forma Cliente, sección 4.3).
const SQL_LEER_UNO =
  'SELECT id, nombre, nit, telefono, correo FROM clientes WHERE id = ? AND vendedor_id = ? LIMIT 1';

/**
 * Lee un paciente de la cartera del asesor. Devuelve la fila o `null` si no existe o es de otro.
 * @param {number} id
 * @param {number} vendedorId
 */
async function leerUno(id, vendedorId) {
  const filas = await consulta(SQL_LEER_UNO, [id, vendedorId]);
  return filas[0] ?? null;
}

export const rutasClientes = Router();

// GET /api/clientes?q=  → los pacientes del asesor, ordenados por nombre.
// Con `q`, busca por nombre (q recortado) o por NIT (q compactado, sin quitar ceros).
rutasClientes.get('/', async (req, res) => {
  const vendedorId = req.usuario.id;
  const q = texto(req.query.q, 1, 150);

  let filas;
  if (q === null) {
    filas = await consulta(
      'SELECT id, nombre, nit, telefono, correo FROM clientes WHERE vendedor_id = ? ORDER BY nombre',
      [vendedorId]
    );
  } else {
    filas = await consulta(
      `SELECT id, nombre, nit, telefono, correo FROM clientes
        WHERE vendedor_id = ? AND (nombre LIKE CONCAT('%', ?, '%') OR nit LIKE CONCAT('%', ?, '%'))
        ORDER BY nombre`,
      [vendedorId, q, compactar(q)]
    );
  }

  res.json(filas.map(formato.cliente));
});

// GET /api/clientes/disponibles  → pacientes sin asesor, solo id y nombre.
// Va antes de /:id para que "disponibles" no se tome como un id.
rutasClientes.get('/disponibles', async (req, res) => {
  const filas = await consulta(
    'SELECT id, nombre FROM clientes WHERE vendedor_id IS NULL ORDER BY nombre'
  );
  res.json(filas.map((fila) => ({ id: fila.id, nombre: fila.nombre })));
});

// GET /api/clientes/:id  → un paciente de la cartera del asesor.
rutasClientes.get('/:id', async (req, res) => {
  const id = idDeRuta(req.params.id);
  const fila = await leerUno(id, req.usuario.id);
  if (!fila) throw noEncontrado();
  res.json(formato.cliente(fila));
});
