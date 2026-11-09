// Arma las respuestas con los campos del contrato (sección 4.3) y ninguno más.

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
