// Rutas de oportunidades (A4): los planes de tratamiento del asesor, sus líneas y su historial.
// Un plan es del asesor si `o.vendedor_id = ?` es el de la sesión. Lo ajeno responde 404,
// igual que lo inexistente (S5).
// SQL a mano con parámetros `?`: textos fijos y completos, ningún dato de la petición se pega
// en el texto SQL (S3).
import { Router } from 'express';
import { consulta } from '../db.js';
import { noEncontrado, peticionMala } from '../util/errores.js';
import { idDeRuta } from '../util/validar.js';
import { ETAPAS } from '../util/etapas.js';
import * as formato from '../util/formato.js';

const MENSAJES = {
  filtroEtapa: 'El filtro etapa no es válido.',
};

// ── Lecturas ────────────────────────────────────────────────────────────────

// Resumen: dos textos fijos, sin filtro y con filtro de etapa.
// El total es una subconsulta: un plan sin líneas da 0, no null.
const SQL_LISTAR_TODAS = `
  SELECT o.id, o.titulo, o.etapa, o.fecha_cierre, o.motivo_perdida,
         c.id AS cliente_id, c.nombre AS cliente_nombre,
         (SELECT COALESCE(SUM(l.cantidad * l.precio_unitario), 0)
            FROM oportunidad_productos l WHERE l.oportunidad_id = o.id) AS total
    FROM oportunidades o JOIN clientes c ON c.id = o.cliente_id
   WHERE o.vendedor_id = ?
   ORDER BY o.created_at DESC, o.id DESC`;

const SQL_LISTAR_POR_ETAPA = `
  SELECT o.id, o.titulo, o.etapa, o.fecha_cierre, o.motivo_perdida,
         c.id AS cliente_id, c.nombre AS cliente_nombre,
         (SELECT COALESCE(SUM(l.cantidad * l.precio_unitario), 0)
            FROM oportunidad_productos l WHERE l.oportunidad_id = o.id) AS total
    FROM oportunidades o JOIN clientes c ON c.id = o.cliente_id
   WHERE o.vendedor_id = ? AND o.etapa = ?
   ORDER BY o.created_at DESC, o.id DESC`;

// Cabecera del detalle: las mismas columnas, solo si el plan es del asesor.
const SQL_CABECERA = `
  SELECT o.id, o.titulo, o.etapa, o.fecha_cierre, o.motivo_perdida,
         c.id AS cliente_id, c.nombre AS cliente_nombre,
         (SELECT COALESCE(SUM(l.cantidad * l.precio_unitario), 0)
            FROM oportunidad_productos l WHERE l.oportunidad_id = o.id) AS total
    FROM oportunidades o JOIN clientes c ON c.id = o.cliente_id
   WHERE o.id = ? AND o.vendedor_id = ?
   LIMIT 1`;

// Líneas: JOIN a productos solo por el nombre. El precio que vale es el guardado en la línea.
const SQL_LINEAS = `
  SELECT l.id, l.producto_id, p.nombre, l.cantidad, l.precio_unitario,
         l.cantidad * l.precio_unitario AS subtotal
    FROM oportunidad_productos l JOIN productos p ON p.id = l.producto_id
   WHERE l.oportunidad_id = ?
   ORDER BY l.id`;

// Historial: el nombre de quien hizo el cambio, nunca su id.
const SQL_HISTORIAL = `
  SELECT h.etapa_anterior, h.etapa_nueva, u.name AS usuario, h.created_at AS fecha
    FROM historial_etapas h JOIN users u ON u.id = h.usuario_id
   WHERE h.oportunidad_id = ?
   ORDER BY h.created_at, h.id`;

/**
 * Lee el detalle de un plan del asesor: cabecera, líneas e historial.
 * `ejecutar(sql, params)` devuelve filas: fuera de una transacción es `consulta`;
 * dentro, `ejecutarCon(cx)`, para leer exactamente lo escrito en la misma transacción.
 * Devuelve `null` si el plan no existe o es ajeno.
 */
async function leerDetalle(ejecutar, id, vendedorId) {
  const cabeceras = await ejecutar(SQL_CABECERA, [id, vendedorId]);
  const fila = cabeceras[0];
  if (!fila) return null;
  const lineas = await ejecutar(SQL_LINEAS, [id]);
  const historial = await ejecutar(SQL_HISTORIAL, [id]);
  return formato.oportunidadDetalle(fila, lineas, historial);
}

// Envoltorio de la conexión de una transacción con la misma forma que `consulta`.
function ejecutarCon(cx) {
  return async (sql, parametros = []) => {
    const [filas] = await cx.execute(sql, parametros);
    return filas;
  };
}

// ── Rutas ───────────────────────────────────────────────────────────────────

// Montado en /api/oportunidades.
export const rutasOportunidades = Router();

// GET /api/oportunidades?etapa=
rutasOportunidades.get('/', async (req, res) => {
  // El filtro se revisa antes de consultar. Solo la ausencia del parámetro da todas:
  // "", un arreglo (?etapa=a&etapa=b) o "Ganada" responden 400.
  const etapa = req.query.etapa;
  let filas;
  if (etapa === undefined) {
    filas = await consulta(SQL_LISTAR_TODAS, [req.usuario.id]);
  } else if (typeof etapa === 'string' && ETAPAS.includes(etapa)) {
    filas = await consulta(SQL_LISTAR_POR_ETAPA, [req.usuario.id, etapa]);
  } else {
    throw peticionMala(MENSAJES.filtroEtapa);
  }
  res.json(filas.map(formato.oportunidadResumen));
});

// GET /api/oportunidades/:id
rutasOportunidades.get('/:id', async (req, res) => {
  const id = idDeRuta(req.params.id);
  const detalle = await leerDetalle(consulta, id, req.usuario.id);
  if (!detalle) throw noEncontrado();
  res.json(detalle);
});
