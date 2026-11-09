// Rutas de actividades (A5): llamadas, citas (reunion) y tareas de los pacientes del asesor.
// Una actividad es del asesor si su PACIENTE está en su cartera (c.vendedor_id = ?), no por quién
// la registró (a.vendedor_id): así las actividades siguen al paciente cuando el panel lo reasigna
// (sección 5 de docs/especificacion.md).
// SQL a mano con parámetros `?`: ningún dato de la petición se pega en el texto SQL (S3).
// Lo ajeno responde 404, igual que lo inexistente (S5).
import { Router } from 'express';
import { consulta } from '../db.js';
import { invalido, noEncontrado, peticionMala } from '../util/errores.js';
import { exigirCuerpo, idDeRuta, texto } from '../util/validar.js';
import { esISOConZona } from '../util/fechas.js';
import * as formato from '../util/formato.js';

const MENSAJES = {
  filtroEstado: 'El filtro estado debe ser pendiente o completada.',
  tipo: 'Elija el tipo: llamada, reunion o tarea.',
  descripcion: 'Escriba la descripción (máximo 500 caracteres).',
  venceEn: 'Escriba la fecha en ISO 8601 con zona horaria, por ejemplo 2026-12-01T10:00:00-06:00.',
};

// Los tres valores del ENUM de `actividades.tipo`. Se comparan exactos: sin recortar ni minúsculas.
const TIPOS = ['llamada', 'reunion', 'tarea'];

// Lectura de una actividad, solo si su paciente es de la cartera del asesor.
const SQL_LEER_UNA = `
  SELECT a.id, a.tipo, a.descripcion, a.vence_en, a.completada,
         c.id AS cliente_id, c.nombre AS cliente_nombre
    FROM actividades a JOIN clientes c ON c.id = a.cliente_id
   WHERE a.id = ? AND c.vendedor_id = ?
   LIMIT 1`;

// El paciente debe ser de la cartera del asesor.
const SQL_CLIENTE_PROPIO = 'SELECT id FROM clientes WHERE id = ? AND vendedor_id = ? LIMIT 1';

// INSERT … SELECT atómico: si el paciente dejó de ser suyo, inserta 0 filas.
const SQL_INSERTAR = `
  INSERT INTO actividades (cliente_id, vendedor_id, tipo, descripcion, vence_en, completada, created_at, updated_at)
  SELECT c.id, ?, ?, ?, ?, 0, NOW(), NOW()
    FROM clientes c
   WHERE c.id = ? AND c.vendedor_id = ?`;

// Devuelve la fila de la actividad o `undefined` si no existe o es ajena.
async function leerUna(id, vendedorId) {
  const filas = await consulta(SQL_LEER_UNA, [id, vendedorId]);
  return filas[0];
}

/**
 * Revisa el cuerpo del POST. Junta todos los errores en un solo 422.
 * Solo lee `tipo`, `descripcion` y `vence_en`; cualquier otro campo se ignora.
 */
function validarNueva(cuerpo) {
  const errores = {};

  const tipo = cuerpo.tipo;
  if (typeof tipo !== 'string' || !TIPOS.includes(tipo)) errores.tipo = MENSAJES.tipo;

  const descripcion = texto(cuerpo.descripcion, 1, 500);
  if (descripcion === null) errores.descripcion = MENSAJES.descripcion;

  // Ausente, null o "" significan "sin fecha".
  let venceEn = null;
  const valor = cuerpo.vence_en;
  if (valor !== undefined && valor !== null && valor !== '') {
    if (esISOConZona(valor)) venceEn = new Date(valor);
    else errores.vence_en = MENSAJES.venceEn;
  }

  if (Object.keys(errores).length > 0) throw invalido(errores);
  return { tipo, descripcion, venceEn };
}

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

// Montado también en /api/clientes, después de rutasClientes (que no tiene esta ruta).
// Vive aquí para no tocar rutas/clientes.js (spec 06).
export const rutasActividadesDeCliente = Router();

// POST /api/clientes/:id/actividades
rutasActividadesDeCliente.post('/:id/actividades', async (req, res) => {
  const clienteId = idDeRuta(req.params.id);
  const cuerpo = exigirCuerpo(req);
  const vendedorId = req.usuario.id;

  // Lo ajeno responde 404 antes de validar: no se dice nada del paciente de otro.
  const propios = await consulta(SQL_CLIENTE_PROPIO, [clienteId, vendedorId]);
  if (propios.length === 0) throw noEncontrado();

  const { tipo, descripcion, venceEn } = validarNueva(cuerpo);

  // El que registra siempre es el asesor de la sesión, nunca un vendedor_id del cuerpo.
  const resultado = await consulta(SQL_INSERTAR, [vendedorId, tipo, descripcion, venceEn, clienteId, vendedorId]);
  // Si el panel le quitó el paciente entre la lectura y el INSERT, no se insertó nada.
  if (resultado.affectedRows === 0) throw noEncontrado();

  const fila = await leerUna(resultado.insertId, vendedorId);
  if (!fila) throw noEncontrado();
  res.status(201).json(formato.actividad(fila));
});
