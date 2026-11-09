import assert from 'node:assert/strict';
import mysql from 'mysql2/promise';
import { config } from '../src/config.js';

const base = 'http://127.0.0.1:' + config.puerto + '/api';
const correo = process.env.CORREO_CARLOS;
const clave = process.env.CLAVE_SEMILLA;
if (!correo || !clave) {
  throw new Error('Defina CORREO_CARLOS y CLAVE_SEMILLA en api/.env para ejecutar esta verificación.');
}

const conexion = await mysql.createConnection({ ...config.db, timezone: 'Z' });
const prefijo = 'Simultánea ' + Date.now();
const idsCreados = [];

async function pedir(ruta, opciones = {}) {
  const respuesta = await fetch(base + ruta, opciones);
  const texto = await respuesta.text();
  let cuerpo = null;
  try { cuerpo = texto ? JSON.parse(texto) : null; } catch { cuerpo = texto; }
  return { status: respuesta.status, cuerpo };
}

try {
  const login = await pedir('/auth/login', {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({ correo, clave }),
  });
  assert.equal(login.status, 200, 'Carlos debe iniciar sesión');
  const token = login.cuerpo?.token;
  assert.ok(token, 'El login debe devolver token');

  // Carlos parte con 9 pacientes en VerificacionSeeder. Dos altas simultáneas
  // deben compartir el candado de users: solo una puede ocupar el décimo espacio.
  const telefono = () => '9' + String(Math.floor(Math.random() * 10000000)).padStart(7, '0');
  const solicitudes = await Promise.all([0, 1].map((i) => pedir('/clientes', {
    method: 'POST',
    headers: { authorization: 'Bearer ' + token, 'content-type': 'application/json' },
    body: JSON.stringify({ nombre: prefijo + ' ' + (i + 1), nit: 'CF', telefono: telefono() }),
  })));

  for (const r of solicitudes) if (r.status === 201) idsCreados.push(r.cuerpo.id);
  assert.deepEqual(solicitudes.map((r) => r.status).sort(), [201, 409],
    'Se esperaba un alta 201 y otra 409; se recibieron ' + solicitudes.map((r) => r.status).join(', '));

  const [filas] = await conexion.execute(
    'SELECT COUNT(*) AS total FROM clientes WHERE vendedor_id = (SELECT id FROM users WHERE email = ?)',
    [correo]
  );
  assert.equal(Number(filas[0].total), 10, 'La cartera de Carlos debe quedar exactamente en 10');
  console.log('OK: dos altas simultáneas con Carlos (9/10) dieron un 201 y un 409; cartera final 10/10.');
} finally {
  // Eliminar exclusivamente los pacientes temporales creados por esta corrida.
  if (idsCreados.length) {
    await conexion.execute('DELETE FROM clientes WHERE id IN (' + idsCreados.map(() => '?').join(',') + ')', idsCreados);
  }
  await conexion.end();
}
