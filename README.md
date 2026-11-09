# CRM Dientecitos Felices

CRM académico para la clínica dental. Incluye un panel Laravel, una API REST en Express/MySQL y una SPA estática.

## Requisitos

- PHP y Composer compatibles con `laravel/composer.json`.
- Node.js 24 o posterior, npm y MySQL.
- Postman para importar la colección.

## Preparar la base

Desde `laravel/`, configure `.env` para MySQL (`DB_DATABASE=crm_dental`) y la zona horaria UTC. Luego:

```sh
composer install
php artisan key:generate
php artisan migrate:fresh --seeder=VerificacionSeeder
```

Para los datos ampliados del sector, cargue las dos semillas:

```sh
php artisan migrate:fresh --seed
```

`migrate:fresh` borra y vuelve a crear las tablas. Use la base local de desarrollo/verificación.

## Preparar y levantar la API

Desde `api/`:

```sh
npm install
cp .env.example .env
```

Edite `api/.env`: indique `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_NAME=crm_dental`, un `JWT_SECRET` aleatorio de al menos 32 caracteres y el origen exacto de la SPA en `CORS_ORIGIN` (por omisión `http://127.0.0.1:5500`). Para las verificaciones simultáneas, agregue `CORREO_CARLOS` con el correo de Carlos de `VerificacionSeeder` y `CLAVE_SEMILLA` con su contraseña de verificación.

```sh
npm run dev
```

La API queda en `http://127.0.0.1:3000/api`. Laravel se sirve desde `laravel/` con `php artisan serve` (panel en `http://127.0.0.1:8000`). Si abre la carpeta raíz del proyecto en Live Server, la SPA queda en `http://127.0.0.1:5500/frontend/`; debe coincidir con el origen configurado en `CORS_ORIGIN`.

## Endpoints de la API

Todas las rutas están bajo `/api`. Excepto `POST /auth/login`, requieren `Authorization: Bearer <token>`. El token se obtiene al iniciar sesión; la API vuelve a leer la cuenta en cada petición.

| Método | Ruta | Uso |
|---|---|---|
| `POST` | `/auth/login` | Iniciar sesión (`correo`, `clave`) |
| `GET` | `/yo` | Usuario autenticado |
| `GET` | `/clientes` | Cartera; acepta `?q=` para buscar |
| `POST` | `/clientes` | Crear paciente dentro del máximo de cartera |
| `GET` | `/clientes/disponibles` | Pacientes sin asesor |
| `POST` | `/clientes/:id/tomar` | Tomar un paciente disponible |
| `GET` | `/clientes/:id` | Ver un paciente propio |
| `PATCH` | `/clientes/:id` | Editar nombre, NIT, teléfono o correo |
| `POST` | `/clientes/:id/actividades` | Registrar llamada, cita (`reunion`) o tarea |
| `GET` | `/actividades` | Listar actividades; acepta `?estado=pendiente` o `completada` |
| `PATCH` | `/actividades/:id` | Completar o reabrir una actividad |
| `GET` | `/productos` | Catálogo de tratamientos activos |
| `GET` | `/oportunidades` | Planes propios; acepta `?etapa=` |
| `POST` | `/oportunidades` | Crear plan para un paciente propio |
| `GET` | `/oportunidades/:id` | Detalle, tratamientos e historial |
| `PATCH` | `/oportunidades/:id` | Cambiar título |
| `PATCH` | `/oportunidades/:id/etapa` | Avanzar o cerrar el plan |
| `POST` | `/oportunidades/:id/productos` | Agregar tratamiento y cantidad |
| `PATCH` | `/oportunidades/:id/productos/:lineaId` | Cambiar cantidad |
| `DELETE` | `/oportunidades/:id/productos/:lineaId` | Quitar tratamiento |
| `GET` | `/reportes/resumen` | Resumen del mes actual en Guatemala; acepta `?mes=AAAA-MM` |

Los errores mantienen JSON: 400 para petición mal formada, 401/403 para sesión o permisos, 404 para recurso no visible, 409 para conflicto, 422 para validación y 500 genérico. Los datos ajenos a la cartera responden 404.

## Colección Postman

Importe `docs/api.postman_collection.json` en Postman. La colección configura `base` como `http://127.0.0.1:3000/api`; llene `correo_valeria`, `correo_sergio`, `correo_ivan`, `correo_diego`, `correo_carlos` y `clave` con los valores de `VerificacionSeeder`. Ejecute las carpetas en orden: las pruebas guardan tokens e identificadores para las siguientes peticiones. La carpeta de regla propia requiere la base con la semilla sector.

## Verificación

```sh
cd api
npm test
npm run simultaneas
```

`npm run simultaneas` usa a Carlos, que inicia con 9 de 10 pacientes en la semilla de verificación; realiza dos altas concurrentes, espera un `201` y un `409`, y borra el único registro temporal creado. Revise `docs/verificacion.md` para la matriz de aceptación, seguridad, cifras de control y recorridos manuales de ambos frontends.
