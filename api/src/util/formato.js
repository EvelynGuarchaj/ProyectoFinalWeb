// Arma las respuestas con los campos del contrato (sección 4.3) y ninguno más.
import { PROBABILIDAD } from './etapas.js';

// Fecha de MySQL (Date) a ISO UTC, o null.
function iso(fecha) {
  return fecha == null ? null : fecha.toISOString();
}

// Cliente: el NIT NULL (consumidor final) se devuelve como "CF".
export function cliente(fila) {
  return {
    id: fila.id,
    nombre: fila.nombre,
    nit: fila.nit ?? 'CF',
    telefono: fila.telefono,
    correo: fila.correo,
  };
}

// Actividad: `fila` trae las columnas de la consulta del spec 08 (a.* y cliente_id, cliente_nombre).
// `vencida` se calcula al responder: pendiente, con fecha y esa fecha ya pasó.
// `ahora` se recibe para poder probarla sin depender del reloj.
export function actividad(fila, ahora = new Date()) {
  const completada = Boolean(fila.completada);
  const venceEn = fila.vence_en ?? null;
  return {
    id: fila.id,
    cliente: { id: fila.cliente_id, nombre: fila.cliente_nombre },
    tipo: fila.tipo,
    descripcion: fila.descripcion,
    vence_en: venceEn === null ? null : venceEn.toISOString(),
    completada,
    vencida: !completada && venceEn !== null && venceEn < ahora,
  };
}

// Producto del catálogo. `requiere_evaluacion` llega como 0/1 de MySQL.
export function producto(fila) {
  return {
    id: fila.id,
    nombre: fila.nombre,
    precio: fila.precio,
    requiere_evaluacion: Boolean(fila.requiere_evaluacion),
  };
}

// Oportunidad, resumen: `fila` trae las columnas de la consulta del spec 11
// (o.*, cliente_id, cliente_nombre y total). Los montos llegan como números (decimalNumbers).
export function oportunidadResumen(fila) {
  return {
    id: fila.id,
    cliente: { id: fila.cliente_id, nombre: fila.cliente_nombre },
    titulo: fila.titulo,
    etapa: fila.etapa,
    probabilidad: PROBABILIDAD[fila.etapa],
    total: fila.total,
    fecha_cierre: iso(fila.fecha_cierre),
    motivo_perdida: fila.motivo_perdida,
  };
}

// Oportunidad, detalle: el resumen más sus líneas y su historial.
export function oportunidadDetalle(fila, lineas, historial) {
  return {
    ...oportunidadResumen(fila),
    productos: lineas.map((l) => ({
      id: l.id,
      producto_id: l.producto_id,
      nombre: l.nombre,
      cantidad: l.cantidad,
      precio_unitario: l.precio_unitario,
      subtotal: l.subtotal,
    })),
    historial: historial.map((h) => ({
      etapa_anterior: h.etapa_anterior,
      etapa_nueva: h.etapa_nueva,
      usuario: h.usuario,
      fecha: iso(h.fecha),
    })),
  };
}
