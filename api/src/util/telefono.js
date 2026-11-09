// Teléfono de Guatemala. Mismo resultado que app/Support/Telefono.php del panel
// (casos compartidos en la sección 10 de docs/especificacion.md).

// Quita espacios, guiones, puntos y paréntesis. "+502" se quita siempre;
// "502" sin "+" solo si después quedan exactamente 8 caracteres.
export function normalizar(texto) {
  const limpio = String(texto).replace(/[\s\-.()]/g, '');
  if (limpio.startsWith('+502')) {
    return limpio.slice(4);
  }
  if (limpio.startsWith('502') && limpio.length === 11) {
    return limpio.slice(3);
  }
  return limpio;
}

// 8 dígitos y el primero de 2 a 9.
export function esValido(texto) {
  return typeof texto === 'string' && /^[2-9][0-9]{7}$/.test(texto);
}
