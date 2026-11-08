// Punto de entrada (npm run dev / npm start): enciende la API en el puerto de config.
import { config } from './config.js';
import { app } from './app.js';

app.listen(config.puerto, () => {
  console.log(`API escuchando en http://localhost:${config.puerto}/api`);
});
