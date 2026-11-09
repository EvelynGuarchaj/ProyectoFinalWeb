// NIT de Guatemala. Mismo resultado que app/Support/Nit.php del panel
// (casos compartidos en la sección 10 de docs/especificacion.md).

// Quita espacios y guiones y pasa a mayúsculas. No quita ceros: la usa la búsqueda ?q=.
export function compactar(texto) {
  return String(texto).replace(/[\s-]/g, '').toUpperCase();
}

// Forma en que se guarda: compacto y sin ceros a la izquierda.
export function normalizar(texto) {
  return compactar(texto).replace(/^0+/, '');
}

// "CF" (consumidor final), sin importar mayúsculas ni espacios alrededor.
export function esConsumidorFinal(texto) {
  return typeof texto === 'string' && texto.trim().toUpperCase() === 'CF';
}

// De 2 a 12 caracteres, cuerpo de dígitos sin cero inicial, verificador 0-9 o K,
// y el verificador coincide con el módulo 11.
export function esValido(compacto) {
  if (typeof compacto !== 'string' || !/^[1-9][0-9]{0,10}[0-9K]$/.test(compacto)) {
    return false;
  }
  const cuerpo = compacto.slice(0, -1);
  const verificador = compacto.slice(-1);
  let suma = 0;
  for (let i = 0; i < cuerpo.length; i++) {
    suma += Number(cuerpo[i]) * (cuerpo.length + 1 - i);
  }
  const esperado = (11 - (suma % 11)) % 11;
  return verificador === (esperado === 10 ? 'K' : String(esperado));
}
