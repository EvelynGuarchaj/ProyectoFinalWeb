// Punto de entrada (npm run dev / npm start): revisa MySQL y enciende la API.
import { config } from './config.js';
import { app } from './app.js';
import { probarConexion } from './db.js';

try {
  await probarConexion();
} catch (err) {
  console.error(`No se pudo conectar a MySQL: ${err.code ?? err.message}`);
  console.error(`Revise que MySQL esté encendido y los datos DB_* de api/.env (${config.db.host}:${config.db.port}/${config.db.database}).`);
  process.exit(1);
}

app.listen(config.puerto, () => {
  console.log(`API escuchando en http://localhost:${config.puerto}/api`);
});
