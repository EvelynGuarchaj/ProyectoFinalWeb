// Rutas de actividades (A5): llamadas, citas (reunion) y tareas de los pacientes del asesor.
// Una actividad es del asesor si su PACIENTE está en su cartera (c.vendedor_id = ?), no por quién
// la registró (a.vendedor_id): así las actividades siguen al paciente cuando el panel lo reasigna
// (sección 5 de docs/especificacion.md).
// SQL a mano con parámetros `?`: ningún dato de la petición se pega en el texto SQL (S3).
// Lo ajeno responde 404, igual que lo inexistente (S5).
import { Router } from 'express';
import { consulta } from '../db.js';
import { peticionMala } from '../util/errores.js';
import * as formato from '../util/formato.js';

const MENSAJES = {
  filtroEstado: 'El filtro estado debe ser pendiente o completada.',
};

// Las tres consultas del listado, como textos fijos y completos (spec 08, sección 2.3).
// Primero las que tienen fecha (ascendente), luego las sin fecha; empates por id.
const SQL_LISTAR_TODAS = `
  SELECT a.id, a.tipo, a.descripcion, a.vence_en, a.completada,
         c.id AS cliente_id, c.nombre AS cliente_nombre
    FROM actividades a JOIN clientes c ON c.id = a.cliente_id
   WHERE c.vendedor_id = ?
   ORDER BY a.vence_en IS NULL, a.vence_en, a.id`;

const SQL_LISTAR_PENDIENTES = `
  SELECT a.id, a.tipo, a.descripcion, a.vence_en, a.completada,
         c.id AS cliente_id, c.nombre AS cliente_nombre
    FROM actividades a JOIN clientes c ON c.id = a.cliente_id
   WHERE c.vendedor_id = ? AND a.completada = 0
   ORDER BY a.vence_en IS NULL, a.vence_en, a.id`;

const SQL_LISTAR_COMPLETADAS = `
  SELECT a.id, a.tipo, a.descripcion, a.vence_en, a.completada,
         c.id AS cliente_id, c.nombre AS cliente_nombre
    FROM actividades a JOIN clientes c ON c.id = a.cliente_id
   WHERE c.vendedor_id = ? AND a.completada = 1
   ORDER BY a.vence_en IS NULL, a.vence_en, a.id`;

// Montado en /api/actividades.
export const rutasActividades = Router();

// GET /api/actividades?estado=pendiente|completada
rutasActividades.get('/', async (req, res) => {
  // El filtro se revisa antes de consultar. Solo la ausencia del parámetro da todas:
  // "", un arreglo (?estado=a&estado=b) o "Pendiente" responden 400.
  const estado = req.query.estado;
  let sql;
  if (estado === undefined) sql = SQL_LISTAR_TODAS;
  else if (estado === 'pendiente') sql = SQL_LISTAR_PENDIENTES;
  else if (estado === 'completada') sql = SQL_LISTAR_COMPLETADAS;
  else throw peticionMala(MENSAJES.filtroEstado);

  const filas = await consulta(sql, [req.usuario.id]);
  const ahora = new Date(); // un solo reloj para toda la respuesta
  res.json(filas.map((fila) => formato.actividad(fila, ahora)));
});
