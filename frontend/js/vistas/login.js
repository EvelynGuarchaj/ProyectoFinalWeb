// Vista #/login (F1): inicio de sesión del asesor con POST /auth/login.
import { estado, cambiar } from '../estado.js';
import { pedir } from '../api.js';
import { guardarSesion } from '../sesion.js';
import { escapar, errorCampo, claseError, mostrarError } from '../util.js';

export default {
  titulo: 'Iniciar sesión',

  async cargar(params) {},

  render(estado) {
    const form = estado.formularios.entrar || { valores: {}, errores: {} };
    const valores = form.valores || {};
    const errores = form.errores || {};

    return `<div class="row justify-content-center">
      <div class="col-12 col-sm-10 col-md-6 col-lg-4">
        <div class="card shadow-sm mt-3">
          <div class="card-body">
            <h1 class="h4 text-center mb-1">Dientecitos Felices</h1>
            <p class="text-center text-muted mb-4">Ingrese con su cuenta de asesor.</p>
            <form data-form="entrar">
              <div class="mb-3">
                <label for="correo" class="form-label">Correo</label>
                <input type="email" id="correo" name="correo" class="form-control${claseError(errores, 'correo')}"
                       value="${escapar(valores.correo)}" autocomplete="username" required>
                ${errorCampo(errores, 'correo')}
              </div>
              <div class="mb-3">
                <label for="clave" class="form-label">Contraseña</label>
                <input type="password" id="clave" name="clave" class="form-control${claseError(errores, 'clave')}"
                       autocomplete="current-password" required>
                ${errorCampo(errores, 'clave')}
              </div>
              <button type="submit" class="btn btn-primary w-100">Ingresar</button>
            </form>
          </div>
        </div>
      </div>
    </div>`;
  },

  acciones: {},

  formularios: {
    async entrar(datos, form) {
      // Se guarda lo escrito ANTES de pedir: cada cambio de estado vuelve a dibujar el formulario.
      // La contraseña nunca se guarda en el estado.
      cambiar({
        formularios: { ...estado.formularios, entrar: { valores: { correo: datos.correo }, errores: {} } },
        aviso: null
      });

      try {
        const respuesta = await pedir('POST', '/auth/login', { correo: datos.correo, clave: datos.clave });
        const sesion = { token: respuesta.token, usuario: respuesta.usuario };
        guardarSesion(sesion);
        cambiar({ sesion, formularios: {} });
        location.hash = '#/cartera';
      } catch (e) {
        if (e.status === 422) {
          cambiar({
            formularios: { ...estado.formularios, entrar: { valores: { correo: datos.correo }, errores: e.errores } }
          });
        } else {
          // 401, 403, API apagada o 500: el mensaje que manda la API (o el de sin conexión).
          mostrarError(e);
        }
      }
    }
  }
};
