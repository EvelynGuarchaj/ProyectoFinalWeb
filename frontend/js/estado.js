// Único estado de la SPA. Solo se cambia con cambiar(), que vuelve a dibujar.

export const estado = {
  sesion: null,        // { token, usuario: { id, nombre, correo } }
  ruta: 'cartera',     // nombre de la ruta activa
  params: {},          // ej. { id: '5' } en #/plan/5
  cargando: 0,         // peticiones en curso (> 0 muestra el indicador)
  aviso: null,         // { tipo: 'success' | 'danger' | 'warning', texto, reintentar?, conservar? }
  datos: {},           // cada vista usa SOLO su clave: datos.cartera, datos.embudo, datos.plan…
  formularios: {}      // { [nombreForm]: { valores, errores } } para conservar lo escrito
};

const suscritos = [];

export function cambiar(parcial) {
  Object.assign(estado, parcial);
  for (const funcion of suscritos) {
    funcion(estado);
  }
}

export function suscribir(funcion) {
  suscritos.push(funcion);
}
