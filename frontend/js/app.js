// Base de la SPA: rutas por hash, layout, render y delegación de eventos (docs/plan.md §3).
import { estado, cambiar, suscribir } from './estado.js';
import { escapar, mostrarError } from './util.js';
import { leerSesion } from './sesion.js';
import login from './vistas/login.js';
import cartera from './vistas/cartera.js';
import embudo from './vistas/embudo.js';
import plan from './vistas/plan.js';
import actividades from './vistas/actividades.js';
import resumen from './vistas/resumen.js';

const VISTAS = { login, cartera, embudo, plan, actividades, resumen };

// Enlaces de la barra de navegación (texto de pantalla → ruta).
const MENU = [
  { ruta: 'cartera', texto: 'Pacientes' },
  { ruta: 'embudo', texto: 'Planes' },
  { ruta: 'actividades', texto: 'Actividades' },
  { ruta: 'resumen', texto: 'Resumen' }
];

const app = document.getElementById('app');

// ---------- Rutas ----------

// '#/plan/5' → { ruta: 'plan', params: { id: '5' } }. Si no es una ruta conocida, ruta = null.
function leerHash(hash) {
  const partes = hash.replace(/^#\/?/, '').split('/').filter(Boolean);
  const [nombre, id, ...sobra] = partes;
  if (nombre === 'plan') {
    return id && sobra.length === 0 ? { ruta: 'plan', params: { id } } : { ruta: null, params: {} };
  }
  if (nombre && Object.hasOwn(VISTAS, nombre) && partes.length === 1) {
    return { ruta: nombre, params: {} };
  }
  return { ruta: null, params: {} };
}

async function navegar() {
  const { ruta, params } = leerHash(location.hash);

  // Ruta vacía o inexistente → #/cartera.
  if (!ruta) {
    location.hash = '#/cartera';
    return;
  }

  // El aviso es de la pantalla anterior; solo sobrevive una navegación si pide conservarse.
  const aviso = estado.aviso && estado.aviso.conservar ? { ...estado.aviso, conservar: false } : null;
  cambiar({ ruta, params, aviso });

  const vista = VISTAS[ruta];
  document.title = `${vista.titulo} · Dientecitos Felices`;
  try {
    await vista.cargar(params);
  } catch (e) {
    mostrarError(e);
  }
}

// ---------- Layout y render ----------

function barra() {
  if (!estado.sesion) return '';
  const nombre = estado.sesion.usuario ? estado.sesion.usuario.nombre : '';
  const enlaces = MENU.map(({ ruta, texto }) => {
    const activa = estado.ruta === ruta || (ruta === 'embudo' && estado.ruta === 'plan');
    return `<li class="nav-item">
        <a class="nav-link${activa ? ' active' : ''}" href="#/${ruta}"${activa ? ' aria-current="page"' : ''}>${texto}</a>
      </li>`;
  }).join('');

  return `<nav class="navbar navbar-expand-md navbar-dark bg-primary">
    <div class="container">
      <a class="navbar-brand" href="#/cartera">Dientecitos Felices</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu"
              aria-controls="menu" aria-expanded="false" aria-label="Mostrar el menú">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="menu">
        <ul class="navbar-nav me-auto">${enlaces}</ul>
        <div class="d-flex flex-column flex-md-row align-items-md-center gap-2 py-2 py-md-0">
          <span class="navbar-text texto-largo">${escapar(nombre)}</span>
          <button type="button" class="btn btn-outline-light btn-sm" data-accion="salir">Cerrar sesión</button>
        </div>
      </div>
    </div>
  </nav>`;
}

function indicador() {
  if (estado.cargando <= 0) return '';
  return `<div class="indicador-carga">
    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando…</span></div>
  </div>`;
}

function avisoHtml() {
  const aviso = estado.aviso;
  if (!aviso) return '';
  const reintentar = aviso.reintentar
    ? '<button type="button" class="btn btn-sm btn-light ms-2" data-accion="reintentar">Reintentar</button>'
    : '';
  return `<div class="alert alert-${escapar(aviso.tipo)} d-flex align-items-start" role="alert">
    <div class="flex-grow-1 texto-largo">${escapar(aviso.texto)}${reintentar}</div>
    <button type="button" class="btn-close ms-2" aria-label="Cerrar" data-accion="cerrarAviso"></button>
  </div>`;
}

function render() {
  const vista = VISTAS[estado.ruta];
  app.innerHTML = `${barra()}${indicador()}
    <main class="container py-3">${avisoHtml()}${vista.render(estado)}</main>`;

  // Mientras haya peticiones en curso, no se puede volver a enviar un formulario.
  if (estado.cargando > 0) {
    for (const boton of app.querySelectorAll('button[type="submit"]')) {
      boton.disabled = true;
    }
  }
}

// ---------- Delegación de eventos ----------

// Busca una función propia del objeto (no heredada, como 'toString').
function propia(objeto, nombre) {
  return objeto && Object.hasOwn(objeto, nombre) ? objeto[nombre] : undefined;
}

const ACCIONES_GLOBALES = {
  cerrarAviso() {
    cambiar({ aviso: null });
  },
  // Vuelve a pedir los datos de la vista activa (aviso de API apagada).
  async reintentar() {
    cambiar({ aviso: null });
    await VISTAS[estado.ruta].cargar(estado.params);
  }
};

app.addEventListener('click', async (evento) => {
  const elemento = evento.target.closest('[data-accion]');
  if (!elemento || !app.contains(elemento)) return;
  const nombre = elemento.dataset.accion;
  const funcion = propia(ACCIONES_GLOBALES, nombre) || propia(VISTAS[estado.ruta].acciones, nombre);
  if (!funcion) return;
  try {
    await funcion(elemento, evento);
  } catch (e) {
    mostrarError(e);
  }
});

app.addEventListener('submit', async (evento) => {
  const form = evento.target.closest('form[data-form]');
  if (!form) return;
  evento.preventDefault();
  const funcion = propia(VISTAS[estado.ruta].formularios, form.dataset.form);
  if (!funcion) return;
  const datos = Object.fromEntries(new FormData(form));
  try {
    await funcion(datos, form);
  } catch (e) {
    mostrarError(e);
  }
});

// ---------- Arranque ----------

suscribir(render);
cambiar({ sesion: leerSesion() });
window.addEventListener('hashchange', navegar);
navegar();
