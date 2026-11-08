// Rutas de /api/clientes: la cartera de pacientes del asesor (A3).
// El dueño siempre sale de req.usuario.id (lo deja middleware/auth.js), nunca del cuerpo.
// SQL a mano con parámetros `?`: ningún dato de la petición se pega en el texto SQL (S3).
// Lo ajeno responde 404, igual que lo inexistente (S5).
import { Router } from 'express';
import { consulta, transaccion } from '../db.js';
import { conflicto, invalido, noEncontrado } from '../util/errores.js';
import { exigirCuerpo, idDeRuta, texto } from '../util/validar.js';
import * as nit from '../util/nit.js';
import * as telefono from '../util/telefono.js';
import * as formato from '../util/formato.js';

// Cartera máxima de un asesor (regla del proyecto). El spec 15 la podrá mover a un archivo común.
const MAXIMO_CARTERA = 10;

// Nombres de los índices únicos de `clientes` (convención de Laravel: tabla_columna_unique).
// Si SHOW INDEX FROM clientes muestra otros, se cambian aquí.
const INDICE_NIT = 'clientes_nit_unique';
const INDICE_TELEFONO = 'clientes_telefono_unique';

const MENSAJES = {
  nombre: 'Escriba el nombre del paciente (máximo 150 caracteres).',
  nit: 'Escriba un NIT válido (con su dígito verificador) o CF.',
  telefono: 'Escriba un teléfono de Guatemala de 8 dígitos.',
  correo: 'Escriba un correo válido.',
  nitRepetido: 'Ya existe un paciente con ese NIT.',
  telefonoRepetido: 'Ya existe un paciente con ese teléfono.',
  carteraLlena: `Su cartera ya tiene ${MAXIMO_CARTERA} pacientes, el máximo permitido.`,
};

const FORMATO_CORREO = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// Cada regla devuelve { ok: true, valor } con lo que se guarda, o { ok: false }.
const REGLAS = {
  nombre(valor) {
    const limpio = texto(valor, 1, 150);
    return limpio === null ? { ok: false } : { ok: true, valor: limpio };
  },
  nit(valor) {
    // Debe ser texto: null, un número o el campo ausente fallan. CF se guarda como NULL.
    if (typeof valor !== 'string') return { ok: false };
    if (nit.esConsumidorFinal(valor)) return { ok: true, valor: null };
    const normalizado = nit.normalizar(valor);
    return nit.esValido(normalizado) ? { ok: true, valor: normalizado } : { ok: false };
  },
  telefono(valor) {
    // Debe ser texto: un número JSON perdería ceros o se truncaría sin aviso.
    if (typeof valor !== 'string') return { ok: false };
    const normalizado = telefono.normalizar(valor);
    return telefono.esValido(normalizado) ? { ok: true, valor: normalizado } : { ok: false };
  },
  correo(valor) {
    // Opcional: null, "" o ausente se guardan como NULL. Se guarda recortado, sin pasar a minúsculas.
    if (valor === undefined || valor === null || valor === '') return { ok: true, valor: null };
    if (typeof valor !== 'string') return { ok: false };
    const limpio = valor.trim();
    if (limpio.length > 150 || !FORMATO_CORREO.test(limpio)) return { ok: false };
    return { ok: true, valor: limpio };
  },
};

/**
 * Valida los campos indicados del cuerpo y junta todos los errores en un solo 422.
 * @param {object} cuerpo
 * @param {string[]} campos  Claves de REGLAS que se revisan (POST: todas; PATCH: las presentes).
 * @returns {Record<string, unknown>} Los valores ya normalizados, listos para guardar.
 */
function validarCampos(cuerpo, campos) {
  const valores = {};
  const errores = {};
  for (const campo of campos) {
    const resultado = REGLAS[campo](cuerpo[campo]);
    if (resultado.ok) valores[campo] = resultado.valor;
    else errores[campo] = MENSAJES[campo];
  }
  if (Object.keys(errores).length > 0) throw invalido(errores);
  return valores;
}

/**
 * Revisa NIT y teléfono repetidos antes de guardar (409). Primero el NIT, solo si no es CF.
 * Es un aviso temprano: el árbitro final es el índice único (ver traducirDuplicado).
 * @param {{ nit?: string|null, telefono?: string }} valores
 * @param {number|null} [excluirId]  En el PATCH, el propio paciente no cuenta como repetido.
 */
async function revisarRepetidos(valores, excluirId = null) {
  const sinExcluir = excluirId === null;

  if (typeof valores.nit === 'string') {
    const filas = sinExcluir
      ? await consulta('SELECT id FROM clientes WHERE nit = ? LIMIT 1', [valores.nit])
      : await consulta('SELECT id FROM clientes WHERE nit = ? AND id <> ? LIMIT 1', [valores.nit, excluirId]);
    if (filas.length > 0) throw conflicto(MENSAJES.nitRepetido);
  }
  if (typeof valores.telefono === 'string') {
    const filas = sinExcluir
      ? await consulta('SELECT id FROM clientes WHERE telefono = ? LIMIT 1', [valores.telefono])
      : await consulta('SELECT id FROM clientes WHERE telefono = ? AND id <> ? LIMIT 1', [
          valores.telefono,
          excluirId,
        ]);
    if (filas.length > 0) throw conflicto(MENSAJES.telefonoRepetido);
  }
}

/**
 * Convierte un error 1062 (ER_DUP_ENTRY) de clientes en el 409 del campo que chocó.
 * Cualquier otro error, o un 1062 de otro índice, se vuelve a lanzar (sale como 500).
 */
function traducirDuplicado(err) {
  if (err?.code === 'ER_DUP_ENTRY') {
    const mensaje = String(err.sqlMessage ?? '');
    if (mensaje.includes(INDICE_NIT)) return conflicto(MENSAJES.nitRepetido);
    if (mensaje.includes(INDICE_TELEFONO)) return conflicto(MENSAJES.telefonoRepetido);
  }
  return err;
}

/**
 * Dentro de una transacción: bloquea la fila del asesor y revisa que su cartera tenga espacio.
 * El candado en `users` va primero (orden de candados: users → clientes → oportunidades),
 * así dos peticiones del mismo asesor cuentan una después de la otra.
 */
async function bloquearCartera(cx, vendedorId) {
  await cx.execute('SELECT id FROM users WHERE id = ? FOR UPDATE', [vendedorId]);
  const [filas] = await cx.execute('SELECT COUNT(*) AS total FROM clientes WHERE vendedor_id = ?', [
    vendedorId,
  ]);
  if (Number(filas[0].total) >= MAXIMO_CARTERA) throw conflicto(MENSAJES.carteraLlena);
}

// Columnas que se devuelven de un paciente (forma Cliente, sección 4.3).
const SQL_LEER_UNO =
  'SELECT id, nombre, nit, telefono, correo FROM clientes WHERE id = ? AND vendedor_id = ? LIMIT 1';

/**
 * Lee un paciente de la cartera del asesor. Devuelve la fila o `null` si no existe o es de otro.
 * @param {number} id
 * @param {number} vendedorId
 */
async function leerUno(id, vendedorId) {
  const filas = await consulta(SQL_LEER_UNO, [id, vendedorId]);
  return filas[0] ?? null;
}

export const rutasClientes = Router();

// GET /api/clientes?q=  → los pacientes del asesor, ordenados por nombre.
// Con `q`, busca por nombre (q recortado) o por NIT (q compactado, sin quitar ceros).
rutasClientes.get('/', async (req, res) => {
  const vendedorId = req.usuario.id;
  const q = texto(req.query.q, 1, 150);

  let filas;
  if (q === null) {
    filas = await consulta(
      'SELECT id, nombre, nit, telefono, correo FROM clientes WHERE vendedor_id = ? ORDER BY nombre',
      [vendedorId]
    );
  } else {
    filas = await consulta(
      `SELECT id, nombre, nit, telefono, correo FROM clientes
        WHERE vendedor_id = ? AND (nombre LIKE CONCAT('%', ?, '%') OR nit LIKE CONCAT('%', ?, '%'))
        ORDER BY nombre`,
      [vendedorId, q, nit.compactar(q)]
    );
  }

  res.json(filas.map(formato.cliente));
});

// POST /api/clientes  → crea un paciente en la cartera del asesor.
// Cualquier campo que el contrato no lista (vendedor_id, id…) se ignora.
rutasClientes.post('/', async (req, res) => {
  const cuerpo = exigirCuerpo(req);
  const vendedorId = req.usuario.id;

  const valores = validarCampos(cuerpo, ['nombre', 'nit', 'telefono', 'correo']);
  await revisarRepetidos(valores);

  let resultado;
  try {
    resultado = await transaccion(async (cx) => {
      await bloquearCartera(cx, vendedorId);
      const [r] = await cx.execute(
        `INSERT INTO clientes (vendedor_id, nombre, nit, telefono, correo, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW())`,
        [vendedorId, valores.nombre, valores.nit, valores.telefono, valores.correo]
      );
      return r;
    });
  } catch (err) {
    // transaccion() ya hizo ROLLBACK.
    throw traducirDuplicado(err);
  }

  res.status(201).json(formato.cliente({ id: resultado.insertId, ...valores }));
});

// GET /api/clientes/disponibles  → pacientes sin asesor, solo id y nombre.
// Va antes de /:id para que "disponibles" no se tome como un id.
rutasClientes.get('/disponibles', async (req, res) => {
  const filas = await consulta(
    'SELECT id, nombre FROM clientes WHERE vendedor_id IS NULL ORDER BY nombre'
  );
  res.json(filas.map((fila) => ({ id: fila.id, nombre: fila.nombre })));
});

// GET /api/clientes/:id  → un paciente de la cartera del asesor.
rutasClientes.get('/:id', async (req, res) => {
  const id = idDeRuta(req.params.id);
  const fila = await leerUno(id, req.usuario.id);
  if (!fila) throw noEncontrado();
  res.json(formato.cliente(fila));
});
