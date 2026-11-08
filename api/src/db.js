// Conexión a MySQL con mysql2. SQL escrito a mano y siempre con parámetros `?`:
// ningún dato de la petición se pega dentro del texto SQL (S3). La API nunca ejecuta CREATE ni ALTER:
// el esquema es de las migraciones de Laravel.
import mysql from 'mysql2/promise';
import { config } from './config.js';

const pool = mysql.createPool({
  ...config.db,
  connectionLimit: 10,
  // DECIMAL como número (9250) y no como texto ("9250.00").
  decimalNumbers: true,
  // Las fechas que mysql2 lee y escribe se interpretan en UTC.
  timezone: 'Z',
});

// Cada conexión nueva del pool trabaja en UTC, igual que Laravel ('timezone' => '+00:00').
// Corre antes que cualquier consulta, porque la conexión ejecuta sus comandos en orden.
pool.on('connection', (conexion) => {
  conexion.query("SET time_zone = '+00:00'", (err) => {
    if (err) console.error('No se pudo poner time_zone en UTC:', err);
  });
});

/**
 * Ejecuta una consulta preparada y devuelve las filas (o el resultado de un INSERT/UPDATE).
 * @param {string} sql  Texto SQL con `?` en lugar de cada dato.
 * @param {unknown[]} [parametros]  Valores para cada `?`, en orden.
 */
export async function consulta(sql, parametros = []) {
  const [filas] = await pool.execute(sql, parametros);
  return filas;
}

/**
 * Ejecuta `trabajo(conexion)` dentro de una transacción: si termina bien hace COMMIT;
 * si lanza un error hace ROLLBACK y vuelve a lanzarlo. La conexión siempre se devuelve al pool.
 * Dentro de `trabajo` se usa `conexion.execute(sql, parametros)`, no `consulta()`.
 */
export async function transaccion(trabajo) {
  const conexion = await pool.getConnection();
  try {
    await conexion.beginTransaction();
    const resultado = await trabajo(conexion);
    await conexion.commit();
    return resultado;
  } catch (err) {
    await conexion.rollback();
    throw err;
  } finally {
    conexion.release();
  }
}

// La usa servidor.js antes de encender: si MySQL no responde, la API no arranca.
export async function probarConexion() {
  await pool.query('SELECT 1');
}
