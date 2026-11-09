// Rutas de oportunidades (A4): los planes de tratamiento del asesor, sus líneas y su historial.
// Un plan es del asesor si `o.vendedor_id = ?` es el de la sesión. Lo ajeno responde 404,
// igual que lo inexistente (S5).
// SQL a mano con parámetros `?`: textos fijos y completos, ningún dato de la petición se pega
// en el texto SQL (S3).
import { Router } from 'express';
import { consulta, transaccion } from '../db.js';
import { conflicto, invalido, noEncontrado, peticionMala } from '../util/errores.js';
import { exigirCuerpo, idDeRuta, texto } from '../util/validar.js';
import { ABIERTAS, CERRADAS, ETAPAS, transicionPermitida } from '../util/etapas.js';
import * as formato from '../util/formato.js';
import { MENSAJE_EVALUACION, pacienteTieneEvaluacion, verificarEvaluacion } from '../util/reglaPropia.js';

// Etapas que exigen la cita de evaluación si el plan tiene un tratamiento que la requiere
// (sección 2.3). `perdida` no está: un plan se puede rechazar siempre (R-P7).
const ETAPAS_CON_EVALUACION = Object.freeze(['propuesta', 'negociacion', 'ganada']);
// Etapas en las que agregar un tratamiento que requiere evaluación exige la cita.
// En prospecto y calificado se agrega sin cita; ganada y perdida ya dan 409 por cerrado.
const ETAPAS_AGREGAR_CON_EVALUACION = Object.freeze(['propuesta', 'negociacion']);

const MENSAJES = {
  filtroEtapa: 'El filtro etapa no es válido.',
  clienteId: 'Elija un paciente de su cartera.',
  titulo: 'Escriba el título (máximo 150 caracteres).',
  cerrado: 'El plan ya está cerrado y no se puede modificar.',
  sinTratamientos: 'Agregue al menos un tratamiento antes de aceptar el plan.',
  motivoPerdida: 'Escriba el motivo (de 1 a 255 caracteres).',
  cambioSimultaneo: 'El plan cambió al mismo tiempo en otra petición. Recárguelo.',
  productoId: 'Elija un tratamiento activo del catálogo.',
  cantidad: 'La cantidad debe ser un número entero de 1 a 999.',
  repetido: 'Ese tratamiento ya está en el plan; cambie su cantidad.',
};

// Cantidad de una línea: entero JSON de 1 a 999.
function esCantidadValida(valor) {
  return Number.isSafeInteger(valor) && valor >= 1 && valor <= 999;
}

// Marcador del mensaje de transición cuando `etapa` falta o no es texto.
const SIN_ETAPA = '(sin etapa)';

// Índices únicos según la convención de Laravel (spec 01). Si `SHOW INDEX` muestra otros
// nombres, se cambian aquí.
const INDICE_HISTORIAL = 'historial_etapas_oportunidad_id_etapa_nueva_unique';
const INDICE_LINEA = 'oportunidad_productos_oportunidad_id_producto_id_unique';

// ¿Es un 1062 (ER_DUP_ENTRY) de ese índice único?
function esDuplicadoDe(err, indice) {
  return err?.code === 'ER_DUP_ENTRY' && String(err.sqlMessage ?? '').includes(indice);
}

// `cliente_id`, `producto_id` y `cantidad` solo se aceptan como enteros JSON:
// "5", 5.5, null o la clave ausente no lo son.
function esEnteroPositivo(valor) {
  return Number.isSafeInteger(valor) && valor > 0;
}

// ── Lecturas ────────────────────────────────────────────────────────────────

// Resumen: dos textos fijos, sin filtro y con filtro de etapa.
// El total es una subconsulta: un plan sin líneas da 0, no null.
const SQL_LISTAR_TODAS = `
  SELECT o.id, o.titulo, o.etapa, o.fecha_cierre, o.motivo_perdida,
         c.id AS cliente_id, c.nombre AS cliente_nombre,
         (SELECT COALESCE(SUM(l.cantidad * l.precio_unitario), 0)
            FROM oportunidad_productos l WHERE l.oportunidad_id = o.id) AS total
    FROM oportunidades o JOIN clientes c ON c.id = o.cliente_id
   WHERE o.vendedor_id = ?
   ORDER BY o.created_at DESC, o.id DESC`;

const SQL_LISTAR_POR_ETAPA = `
  SELECT o.id, o.titulo, o.etapa, o.fecha_cierre, o.motivo_perdida,
         c.id AS cliente_id, c.nombre AS cliente_nombre,
         (SELECT COALESCE(SUM(l.cantidad * l.precio_unitario), 0)
            FROM oportunidad_productos l WHERE l.oportunidad_id = o.id) AS total
    FROM oportunidades o JOIN clientes c ON c.id = o.cliente_id
   WHERE o.vendedor_id = ? AND o.etapa = ?
   ORDER BY o.created_at DESC, o.id DESC`;

// Cabecera del detalle: las mismas columnas, solo si el plan es del asesor.
const SQL_CABECERA = `
  SELECT o.id, o.titulo, o.etapa, o.fecha_cierre, o.motivo_perdida,
         c.id AS cliente_id, c.nombre AS cliente_nombre,
         (SELECT COALESCE(SUM(l.cantidad * l.precio_unitario), 0)
            FROM oportunidad_productos l WHERE l.oportunidad_id = o.id) AS total
    FROM oportunidades o JOIN clientes c ON c.id = o.cliente_id
   WHERE o.id = ? AND o.vendedor_id = ?
   LIMIT 1`;

// Líneas: JOIN a productos solo por el nombre. El precio que vale es el guardado en la línea.
const SQL_LINEAS = `
  SELECT l.id, l.producto_id, p.nombre, l.cantidad, l.precio_unitario,
         l.cantidad * l.precio_unitario AS subtotal
    FROM oportunidad_productos l JOIN productos p ON p.id = l.producto_id
   WHERE l.oportunidad_id = ?
   ORDER BY l.id`;

// Historial: el nombre de quien hizo el cambio, nunca su id.
const SQL_HISTORIAL = `
  SELECT h.etapa_anterior, h.etapa_nueva, u.name AS usuario, h.created_at AS fecha
    FROM historial_etapas h JOIN users u ON u.id = h.usuario_id
   WHERE h.oportunidad_id = ?
   ORDER BY h.created_at, h.id`;

/**
 * Lee el detalle de un plan del asesor: cabecera, líneas e historial.
 * `ejecutar(sql, params)` devuelve filas: fuera de una transacción es `consulta`;
 * dentro, `ejecutarCon(cx)`, para leer exactamente lo escrito en la misma transacción.
 * Devuelve `null` si el plan no existe o es ajeno.
 */
async function leerDetalle(ejecutar, id, vendedorId) {
  const cabeceras = await ejecutar(SQL_CABECERA, [id, vendedorId]);
  const fila = cabeceras[0];
  if (!fila) return null;
  const lineas = await ejecutar(SQL_LINEAS, [id]);
  const historial = await ejecutar(SQL_HISTORIAL, [id]);
  return formato.oportunidadDetalle(fila, lineas, historial);
}

// Envoltorio de la conexión de una transacción con la misma forma que `consulta`.
function ejecutarCon(cx) {
  return async (sql, parametros = []) => {
    const [filas] = await cx.execute(sql, parametros);
    return filas;
  };
}

// ── Escrituras ──────────────────────────────────────────────────────────────

// El paciente debe ser de la cartera del asesor.
const SQL_CLIENTE_PROPIO = 'SELECT id FROM clientes WHERE id = ? AND vendedor_id = ? LIMIT 1';

// INSERT … SELECT atómico: si el panel le quitó el paciente en ese instante, inserta 0 filas.
// La etapa inicial siempre es prospecto; el dueño siempre es el asesor de la sesión.
const SQL_INSERTAR = `
  INSERT INTO oportunidades (cliente_id, vendedor_id, titulo, etapa, created_at, updated_at)
  SELECT c.id, ?, ?, 'prospecto', UTC_TIMESTAMP(), UTC_TIMESTAMP()
    FROM clientes c
   WHERE c.id = ? AND c.vendedor_id = ?`;

// Un registro al crear y uno por cambio de etapa.
const SQL_HISTORIAL_INSERTAR = `
  INSERT INTO historial_etapas (oportunidad_id, usuario_id, etapa_anterior, etapa_nueva, created_at)
  VALUES (?, ?, ?, ?, UTC_TIMESTAMP())`;

// Candado de toda modificación: primer paso dentro de la transacción. Pone en fila las
// peticiones al mismo plan; la segunda lee el estado que dejó la primera (S8).
const SQL_CANDADO = 'SELECT id, etapa, cliente_id FROM oportunidades WHERE id = ? AND vendedor_id = ? FOR UPDATE';

const SQL_CAMBIAR_TITULO = 'UPDATE oportunidades SET titulo = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?';

const SQL_CONTAR_LINEAS = 'SELECT COUNT(*) AS lineas FROM oportunidad_productos WHERE oportunidad_id = ?';

// Cambio de etapa: dos textos fijos, avanzar (a una abierta) y cerrar (ganada o perdida).
const SQL_AVANZAR = 'UPDATE oportunidades SET etapa = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?';

const SQL_CERRAR = `
  UPDATE oportunidades
     SET etapa = ?, motivo_perdida = ?, fecha_cierre = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
   WHERE id = ?`;

// Tratamientos del plan.
const SQL_PRODUCTO_ACTIVO = 'SELECT id, requiere_evaluacion FROM productos WHERE id = ? AND activo = 1 LIMIT 1';

const SQL_LINEA_REPETIDA =
  'SELECT id FROM oportunidad_productos WHERE oportunidad_id = ? AND producto_id = ? LIMIT 1';

// INSERT … SELECT: el precio sale de productos.precio, nunca del cuerpo.
// Si el producto se desactivó en ese instante, inserta 0 filas.
const SQL_INSERTAR_LINEA = `
  INSERT INTO oportunidad_productos (oportunidad_id, producto_id, cantidad, precio_unitario, created_at, updated_at)
  SELECT ?, p.id, ?, p.precio, UTC_TIMESTAMP(), UTC_TIMESTAMP()
    FROM productos p
   WHERE p.id = ? AND p.activo = 1`;

// Líneas: siempre con `oportunidad_id = ?` y después del candado de su plan.
const SQL_LINEA_DEL_PLAN = 'SELECT id FROM oportunidad_productos WHERE id = ? AND oportunidad_id = ? LIMIT 1';

// Solo la cantidad: precio_unitario no cambia nunca.
const SQL_CAMBIAR_CANTIDAD = `
  UPDATE oportunidad_productos SET cantidad = ?, updated_at = UTC_TIMESTAMP()
   WHERE id = ? AND oportunidad_id = ?`;

const SQL_BORRAR_LINEA = 'DELETE FROM oportunidad_productos WHERE id = ? AND oportunidad_id = ?';

/**
 * Dentro de una transacción: bloquea el plan del asesor y devuelve su fila `{ id, etapa, cliente_id }`,
 * o `undefined` si no existe o es ajeno.
 */
async function bloquearPlan(cx, id, vendedorId) {
  const [filas] = await cx.execute(SQL_CANDADO, [id, vendedorId]);
  return filas[0];
}

// Un plan cerrado no cambia (sección 3.10): 409.
function exigirAbierto(plan) {
  if (CERRADAS.includes(plan.etapa)) throw conflicto(MENSAJES.cerrado);
}

// Candado completo de los planes: 404 si no hay fila, 409 si está cerrado.
async function bloquearPlanAbierto(cx, id, vendedorId) {
  const plan = await bloquearPlan(cx, id, vendedorId);
  if (!plan) throw noEncontrado();
  exigirAbierto(plan);
  return plan;
}

/**
 * Candado de una línea: primero los 404 (plan y luego línea), después el 409 de plan cerrado.
 * Así lo inexistente o ajeno responde igual en cualquier estado del plan.
 */
async function bloquearLinea(cx, id, lineaId, vendedorId) {
  const plan = await bloquearPlan(cx, id, vendedorId);
  if (!plan) throw noEncontrado();
  const [lineas] = await cx.execute(SQL_LINEA_DEL_PLAN, [lineaId, id]);
  if (lineas.length === 0) throw noEncontrado();
  exigirAbierto(plan);
}

/**
 * Revisa el cuerpo del POST. Junta todos los errores en un solo 422.
 * Solo lee `cliente_id` y `titulo`; `etapa`, `vendedor_id` y cualquier otro campo se ignoran.
 */
async function validarNueva(cuerpo, vendedorId) {
  const errores = {};

  const titulo = texto(cuerpo.titulo, 1, 150);
  if (titulo === null) errores.titulo = MENSAJES.titulo;

  const clienteId = cuerpo.cliente_id;
  if (!esEnteroPositivo(clienteId)) {
    errores.cliente_id = MENSAJES.clienteId;
  } else {
    const propios = await consulta(SQL_CLIENTE_PROPIO, [clienteId, vendedorId]);
    if (propios.length === 0) errores.cliente_id = MENSAJES.clienteId;
  }

  if (Object.keys(errores).length > 0) throw invalido(errores);
  return { clienteId, titulo };
}

// ── Rutas ───────────────────────────────────────────────────────────────────

// Montado en /api/oportunidades.
export const rutasOportunidades = Router();

// GET /api/oportunidades?etapa=
rutasOportunidades.get('/', async (req, res) => {
  // El filtro se revisa antes de consultar. Solo la ausencia del parámetro da todas:
  // "", un arreglo (?etapa=a&etapa=b) o "Ganada" responden 400.
  const etapa = req.query.etapa;
  let filas;
  if (etapa === undefined) {
    filas = await consulta(SQL_LISTAR_TODAS, [req.usuario.id]);
  } else if (typeof etapa === 'string' && ETAPAS.includes(etapa)) {
    filas = await consulta(SQL_LISTAR_POR_ETAPA, [req.usuario.id, etapa]);
  } else {
    throw peticionMala(MENSAJES.filtroEtapa);
  }
  res.json(filas.map(formato.oportunidadResumen));
});

// GET /api/oportunidades/:id
rutasOportunidades.get('/:id', async (req, res) => {
  const id = idDeRuta(req.params.id);
  const detalle = await leerDetalle(consulta, id, req.usuario.id);
  if (!detalle) throw noEncontrado();
  res.json(detalle);
});

// POST /api/oportunidades  { "cliente_id": 5, "titulo": "…" }
rutasOportunidades.post('/', async (req, res) => {
  const cuerpo = exigirCuerpo(req);
  const vendedorId = req.usuario.id;
  const { clienteId, titulo } = await validarNueva(cuerpo, vendedorId);

  const detalle = await transaccion(async (cx) => {
    const [resultado] = await cx.execute(SQL_INSERTAR, [vendedorId, titulo, clienteId, vendedorId]);
    // El panel le quitó el paciente entre la validación y el INSERT: no se insertó nada.
    // Lanzar aquí hace que transaccion() revierta.
    if (resultado.affectedRows === 0) throw invalido({ cliente_id: MENSAJES.clienteId });

    const id = resultado.insertId;
    await cx.execute(SQL_HISTORIAL_INSERTAR, [id, vendedorId, null, 'prospecto']);
    return leerDetalle(ejecutarCon(cx), id, vendedorId);
  });

  res.status(201).json(detalle);
});

// PATCH /api/oportunidades/:id  { "titulo": "…" }
// Solo cambia el título; cualquier otro campo del cuerpo se ignora.
rutasOportunidades.patch('/:id', async (req, res) => {
  const id = idDeRuta(req.params.id);
  const cuerpo = exigirCuerpo(req);
  const vendedorId = req.usuario.id;

  const detalle = await transaccion(async (cx) => {
    await bloquearPlanAbierto(cx, id, vendedorId);

    const titulo = texto(cuerpo.titulo, 1, 150);
    if (titulo === null) throw invalido({ titulo: MENSAJES.titulo });

    await cx.execute(SQL_CAMBIAR_TITULO, [titulo, id]);
    return leerDetalle(ejecutarCon(cx), id, vendedorId);
  });

  res.json(detalle);
});

// PATCH /api/oportunidades/:id/etapa  { "etapa": "…", "motivo_perdida": "…" }
// Orden de la sección 4.4: 404, 409, transición, ganada sin tratamientos, motivo.
rutasOportunidades.patch('/:id/etapa', async (req, res) => {
  const id = idDeRuta(req.params.id);
  const cuerpo = exigirCuerpo(req);
  const vendedorId = req.usuario.id;

  let detalle;
  try {
    detalle = await transaccion(async (cx) => {
      const { etapa: actual, cliente_id: clienteId } = await bloquearPlanAbierto(cx, id, vendedorId);
      const nueva = cuerpo.etapa;

      // Saltos, retrocesos, la misma etapa o un valor que no es una etapa: 422.
      if (!transicionPermitida(actual, nueva)) {
        const mostrada = typeof nueva === 'string' ? nueva : SIN_ETAPA;
        throw invalido({ etapa: `No se puede pasar de ${actual} a ${mostrada}.` });
      }

      if (nueva === 'ganada') {
        const [[{ lineas }]] = await cx.execute(SQL_CONTAR_LINEAS, [id]);
        if (lineas === 0) throw invalido({ etapa: MENSAJES.sinTratamientos });
      }

      // En las demás etapas, `motivo_perdida` se ignora.
      let motivo = null;
      if (nueva === 'perdida') {
        motivo = texto(cuerpo.motivo_perdida, 1, 255);
        if (motivo === null) throw invalido({ motivo_perdida: MENSAJES.motivoPerdida });
      }

      // Regla propia (sección 2.3 de la especificación).
      if (ETAPAS_CON_EVALUACION.includes(nueva)) {
        await verificarEvaluacion(cx, id, clienteId);
      }

      if (ABIERTAS.includes(nueva)) {
        await cx.execute(SQL_AVANZAR, [nueva, id]);
      } else {
        await cx.execute(SQL_CERRAR, [nueva, motivo, id]);
      }
      await cx.execute(SQL_HISTORIAL_INSERTAR, [id, vendedorId, actual, nueva]);

      return leerDetalle(ejecutarCon(cx), id, vendedorId);
    });
  } catch (err) {
    // Respaldo del FOR UPDATE: transaccion() ya revirtió, no queda ni el cambio ni el historial.
    if (esDuplicadoDe(err, INDICE_HISTORIAL)) {
      throw conflicto(MENSAJES.cambioSimultaneo);
    }
    throw err;
  }

  res.json(detalle);
});

// POST /api/oportunidades/:id/productos  { "producto_id": 1, "cantidad": 2 }
// `precio_unitario` del cuerpo nunca se lee: el precio se copia del catálogo.
rutasOportunidades.post('/:id/productos', async (req, res) => {
  const id = idDeRuta(req.params.id);
  const cuerpo = exigirCuerpo(req);
  const vendedorId = req.usuario.id;

  let detalle;
  try {
    detalle = await transaccion(async (cx) => {
      const plan = await bloquearPlanAbierto(cx, id, vendedorId);

      // Todos los errores juntos en un solo 422.
      const errores = {};
      const productoId = cuerpo.producto_id;
      let requiereEvaluacion = false;
      if (!esEnteroPositivo(productoId)) {
        errores.producto_id = MENSAJES.productoId;
      } else {
        const [activos] = await cx.execute(SQL_PRODUCTO_ACTIVO, [productoId]);
        if (activos.length === 0) errores.producto_id = MENSAJES.productoId;
        // MySQL lo entrega como 0 o 1.
        else requiereEvaluacion = Boolean(activos[0].requiere_evaluacion);
      }
      const cantidad = cuerpo.cantidad;
      if (!esCantidadValida(cantidad)) errores.cantidad = MENSAJES.cantidad;
      if (Object.keys(errores).length > 0) throw invalido(errores);

      const [repetidas] = await cx.execute(SQL_LINEA_REPETIDA, [id, productoId]);
      if (repetidas.length > 0) throw conflicto(MENSAJES.repetido);

      // Regla propia (sección 2.3 de la especificación).
      if (
        requiereEvaluacion &&
        ETAPAS_AGREGAR_CON_EVALUACION.includes(plan.etapa) &&
        !(await pacienteTieneEvaluacion(cx, plan.cliente_id))
      ) {
        throw conflicto(MENSAJE_EVALUACION);
      }

      const [resultado] = await cx.execute(SQL_INSERTAR_LINEA, [id, cantidad, productoId]);
      // El producto se desactivó entre la validación y el INSERT: no se insertó nada.
      if (resultado.affectedRows === 0) throw invalido({ producto_id: MENSAJES.productoId });

      return leerDetalle(ejecutarCon(cx), id, vendedorId);
    });
  } catch (err) {
    // Respaldo de la consulta previa: transaccion() ya revirtió.
    if (esDuplicadoDe(err, INDICE_LINEA)) throw conflicto(MENSAJES.repetido);
    throw err;
  }

  res.status(201).json(detalle);
});

// PATCH /api/oportunidades/:id/productos/:lineaId  { "cantidad": 3 }
// Solo cambia la cantidad; precio_unitario y cualquier otro campo del cuerpo se ignoran.
rutasOportunidades.patch('/:id/productos/:lineaId', async (req, res) => {
  const id = idDeRuta(req.params.id);
  const lineaId = idDeRuta(req.params.lineaId);
  const cuerpo = exigirCuerpo(req);
  const vendedorId = req.usuario.id;

  const detalle = await transaccion(async (cx) => {
    await bloquearLinea(cx, id, lineaId, vendedorId);

    const cantidad = cuerpo.cantidad;
    if (!esCantidadValida(cantidad)) throw invalido({ cantidad: MENSAJES.cantidad });

    await cx.execute(SQL_CAMBIAR_CANTIDAD, [cantidad, lineaId, id]);
    return leerDetalle(ejecutarCon(cx), id, vendedorId);
  });

  res.json(detalle);
});

// DELETE /api/oportunidades/:id/productos/:lineaId → 204 sin cuerpo.
rutasOportunidades.delete('/:id/productos/:lineaId', async (req, res) => {
  const id = idDeRuta(req.params.id);
  const lineaId = idDeRuta(req.params.lineaId);
  const vendedorId = req.usuario.id;

  await transaccion(async (cx) => {
    await bloquearLinea(cx, id, lineaId, vendedorId);
    await cx.execute(SQL_BORRAR_LINEA, [lineaId, id]);
  });

  res.status(204).end();
});
