import { estado, cambiar, suscribir } from './estado.js';
import login from './vistas/login.js';
import cartera from './vistas/cartera.js';
import embudo from './vistas/embudo.js';
import plan from './vistas/plan.js';
import actividades from './vistas/actividades.js';
import resumen from './vistas/resumen.js';

const VISTAS = { login, cartera, embudo, plan, actividades, resumen };

const app = document.getElementById('app');

function render() {
  const vista = VISTAS[estado.ruta];
  app.innerHTML = `<main class="container py-3">${vista.render(estado)}</main>`;
}

suscribir(render);
cambiar({ ruta: 'cartera', params: {} });
