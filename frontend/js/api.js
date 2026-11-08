// Peticiones a la API (contrato de docs/plan.md §3.4).
import { estado, cambiar } from './estado.js';
import { borrarSesion } from './sesion.js';

const BASE = 'http://localhost:3000/api';

const MENSAJE_SIN_CONEXION = 'No se pudo conectar con el servidor. Verifique que la API esté encendida.';
const MENSAJE_GENERICO = 'Ocurrió un error inesperado. Intente de nuevo más tarde.';
const MENSAJE_SESION_TERMINADA = 'Su sesión terminó. Ingrese de nuevo.';

export class ErrorApi extends Error {
  constructor(status, message, errores = {}, sesionTerminada = false) {
    super(message);
    this.name = 'ErrorApi';
    this.status = status;           // 0 = la API no responde
    this.errores = errores;         // { campo: mensaje } en los 422
    this.sesionTerminada = sesionTerminada;
  }
}

// pedir('GET', '/clientes?q=ana') · pedir('POST', '/clientes', { nombre, ... })
export async function pedir(metodo, ruta, cuerpo) {
  const token = estado.sesion ? estado.sesion.token : null;
  const opciones = {
    method: metodo,
    headers: { 'Content-Type': 'application/json' }
  };
  if (token) opciones.headers.Authorization = `Bearer ${token}`;
  if (cuerpo !== undefined) opciones.body = JSON.stringify(cuerpo);

  cambiar({ cargando: estado.cargando + 1 });
  try {
    let respuesta;
    try {
      respuesta = await fetch(BASE + ruta, opciones);
    } catch {
      // Sin respuesta: API apagada, red caída o bloqueo de CORS.
      throw new ErrorApi(0, MENSAJE_SIN_CONEXION);
    }

    if (respuesta.status === 204) return null;

    let datos = null;
    try {
      datos = await respuesta.json();
    } catch {
      datos = null;
    }

    if (respuesta.ok) return datos;

    // 401 en plena sesión (no el del propio inicio de sesión): la sesión terminó.
    if (respuesta.status === 401 && token && ruta !== '/auth/login') {
      borrarSesion();
      cambiar({
        sesion: null,
        datos: {},
        formularios: {},
        aviso: { tipo: 'warning', texto: MENSAJE_SESION_TERMINADA, conservar: true }
      });
      location.hash = '#/login';
      throw new ErrorApi(401, MENSAJE_SESION_TERMINADA, {}, true);
    }

    const mensaje = (datos && datos.error) || (datos && datos.errores ? 'Revise los datos del formulario.' : MENSAJE_GENERICO);
    throw new ErrorApi(respuesta.status, mensaje, (datos && datos.errores) || {});
  } finally {
    cambiar({ cargando: estado.cargando - 1 });
  }
}
