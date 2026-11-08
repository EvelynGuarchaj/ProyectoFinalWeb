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
