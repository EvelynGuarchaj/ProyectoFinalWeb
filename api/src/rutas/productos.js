// Catálogo de tratamientos para el asesor (A4): solo los activos, ordenados por nombre.
// `requiere_evaluacion` solo se devuelve; la regla propia que lo usa es del spec 13.
// SQL a mano, texto fijo y sin datos de la petición (S3).
import { Router } from 'express';
import { consulta } from '../db.js';
import * as formato from '../util/formato.js';

const SQL_ACTIVOS = `
  SELECT id, nombre, precio, requiere_evaluacion
    FROM productos
   WHERE activo = 1
   ORDER BY nombre, id`;

// Montado en /api/productos.
export const rutasProductos = Router();

// GET /api/productos
rutasProductos.get('/', async (req, res) => {
  const filas = await consulta(SQL_ACTIVOS);
  res.json(filas.map(formato.producto));
});
