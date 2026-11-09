// Arma la aplicación de Express: CORS, lectura de JSON, rutas, 404 y errores.
// No abre ningún puerto (eso lo hace servidor.js), así otros scripts pueden importarla.
import express from 'express';
import cors from 'cors';
import { config } from './config.js';
import { manejarErrores } from './middleware/errores.js';
import { exigirToken } from './middleware/auth.js';
import { rutasAuth } from './rutas/auth.js';
import { rutasYo } from './rutas/yo.js';
import { rutasClientes } from './rutas/clientes.js';
import { rutasActividades, rutasActividadesDeCliente } from './rutas/actividades.js';
import { rutasProductos } from './rutas/productos.js';
import { rutasOportunidades } from './rutas/oportunidades.js';

export const app = express();

// CORS: solo la SPA puede llamar a la API desde el navegador.
// El origen va en un arreglo: así `cors` compara el Origin de la petición y, si no coincide,
// no manda Access-Control-Allow-Origin. Con un texto suelto lo mandaría siempre (spec 02, decisiones).
app.use(
  cors({
    origin: [config.corsOrigin],
    methods: ['GET', 'POST', 'PATCH', 'DELETE'],
    allowedHeaders: ['Content-Type', 'Authorization'],
  })
);

// Lee los cuerpos JSON. Si el JSON está roto, el error llega a manejarErrores (400).
app.use(express.json());

// Rutas bajo /api.
const api = express.Router();
// Sin token: solo el inicio de sesión.
api.use('/auth', rutasAuth);
// Todo lo que sigue exige token. Una ruta inexistente bajo /api sin token responde 401.
api.use(exigirToken);
api.use('/yo', rutasYo);
api.use('/clientes', rutasClientes);
// POST /clientes/:id/actividades: rutasClientes no la tiene, así que Express sigue a este router.
api.use('/clientes', rutasActividadesDeCliente);
api.use('/actividades', rutasActividades);
api.use('/productos', rutasProductos);
api.use('/oportunidades', rutasOportunidades);
app.use('/api', api);

// Ninguna ruta respondió.
app.use((req, res) => {
  res.status(404).json({ error: 'Ruta no encontrada.' });
});

// Siempre al final.
app.use(manejarErrores);
