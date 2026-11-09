// Rutas de /api/reportes: el resumen del mes del asesor (A6, spec 15).
// Solo responde por el asesor del token (req.usuario.id), nunca por otro.
// Todas las cifras salen de MySQL: JavaScript no suma, no multiplica ni redondea (sección 11.4).
// SQL a mano, textos fijos con parámetros `?` (S3). Nunca se une a `productos`: cuenta el precio
// guardado en la línea del plan, no el del catálogo (sección 11.4, error 2).
import { Router } from 'express';
import { consulta } from '../db.js';
import { peticionMala } from '../util/errores.js';
import { esMesValido, mesActualGuatemala } from '../util/mes.js';
import { MAXIMO_CARTERA } from '../util/cartera.js';

const MENSAJES = {
  mes: 'Escriba el mes con el formato AAAA-MM.',
};

// Ventas del mes: planes ganados cuyo cierre cae en el mes, en hora de Guatemala.
// Se cuenta sin unir las líneas, así un plan con varias líneas cuenta una sola vez.
// Parámetros: [vendedorId, mes]
const SQL_VENTAS = `
  SELECT COUNT(*) AS ventas
    FROM oportunidades o
   WHERE o.vendedor_id = ? AND o.etapa = 'ganada'
     AND DATE_FORMAT(o.fecha_cierre - INTERVAL 6 HOUR, '%Y-%m') = ?`;

// Total vendido en el mes con el precio guardado en cada línea. COALESCE: sin ventas da 0, no null.
// Parámetros: [vendedorId, mes]
const SQL_TOTAL = `
  SELECT COALESCE(SUM(l.cantidad * l.precio_unitario), 0) AS total
    FROM oportunidades o
    JOIN oportunidad_productos l ON l.oportunidad_id = o.id
   WHERE o.vendedor_id = ? AND o.etapa = 'ganada'
     AND DATE_FORMAT(o.fecha_cierre - INTERVAL 6 HOUR, '%Y-%m') = ?`;

// Pronóstico de los planes abiertos, sin filtro de mes. Se redondea la suma, no cada plan.
// Parámetros: [vendedorId]
const SQL_PRONOSTICO = `
  SELECT ROUND(COALESCE(SUM(l.cantidad * l.precio_unitario * CASE o.etapa
           WHEN 'prospecto' THEN 0.10 WHEN 'calificado' THEN 0.25
           WHEN 'propuesta' THEN 0.50 WHEN 'negociacion' THEN 0.75 END), 0), 2) AS pronostico
    FROM oportunidades o
    JOIN oportunidad_productos l ON l.oportunidad_id = o.id
   WHERE o.vendedor_id = ?
     AND o.etapa IN ('prospecto', 'calificado', 'propuesta', 'negociacion')`;

// Pacientes en la cartera del asesor.
// Parámetros: [vendedorId]
const SQL_CARTERA = `
  SELECT COUNT(*) AS clientes FROM clientes WHERE vendedor_id = ?`;

// Montado en /api/reportes (detrás de exigirToken).
export const rutasReportes = Router();

// GET /api/reportes/resumen?mes=AAAA-MM
rutasReportes.get('/resumen', async (req, res) => {
  // El mes se valida antes de tocar la base: un mes inválido nunca ejecuta SQL.
  // Solo la ausencia del parámetro usa el mes actual; vacío, otro formato o repetido → 400.
  let mes;
  if (req.query.mes === undefined) mes = mesActualGuatemala();
  else if (esMesValido(req.query.mes)) mes = req.query.mes;
  else throw peticionMala(MENSAJES.mes);

  const vendedorId = req.usuario.id;
  const [[{ ventas }], [{ total }], [{ pronostico }], [{ clientes }]] = await Promise.all([
    consulta(SQL_VENTAS, [vendedorId, mes]),
    consulta(SQL_TOTAL, [vendedorId, mes]),
    consulta(SQL_PRONOSTICO, [vendedorId]),
    consulta(SQL_CARTERA, [vendedorId]),
  ]);

  // Los valores van tal como llegan de las filas (decimalNumbers: true los entrega como números).
  res.json({
    mes,
    ventas,
    total,
    pronostico,
    cartera: { clientes, maximo: MAXIMO_CARTERA },
  });
});
