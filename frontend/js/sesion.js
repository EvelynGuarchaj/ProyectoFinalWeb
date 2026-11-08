// Único archivo que toca localStorage, y solo con la clave "sesion" (F1).
const CLAVE = 'sesion';

// Devuelve { token, usuario } o null si falta, no es JSON válido o le faltan datos.
export function leerSesion() {
  try {
    const sesion = JSON.parse(localStorage.getItem(CLAVE));
    if (sesion && typeof sesion.token === 'string' && sesion.token && sesion.usuario) {
      return { token: sesion.token, usuario: sesion.usuario };
    }
  } catch {
    // JSON dañado o localStorage no disponible: se trata como sin sesión.
  }
  return null;
}

export function guardarSesion(sesion) {
  localStorage.setItem(CLAVE, JSON.stringify({ token: sesion.token, usuario: sesion.usuario }));
}

export function borrarSesion() {
  localStorage.removeItem(CLAVE);
}
