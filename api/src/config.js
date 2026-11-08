// Lee y valida las variables de entorno una sola vez, al arrancar.
// Las carga `node --env-file=.env` (scripts de package.json); aquí no se usa dotenv.
// Si algo falta, la API no arranca: es mejor fallar ahora que en la primera petición.

const OBLIGATORIAS = ['DB_HOST', 'DB_USER', 'DB_NAME', 'JWT_SECRET', 'JWT_EXPIRA', 'CORS_ORIGIN'];
const LARGO_MINIMO_SECRETO = 32;

const env = process.env;
const problemas = [];

for (const nombre of OBLIGATORIAS) {
  if (!env[nombre] || env[nombre].trim() === '') {
    problemas.push(`Falta ${nombre} en api/.env.`);
  }
}

// Nunca se imprime el valor del secreto, solo el problema.
if (env.JWT_SECRET && env.JWT_SECRET.length < LARGO_MINIMO_SECRETO) {
  problemas.push(
    `JWT_SECRET debe tener al menos ${LARGO_MINIMO_SECRETO} caracteres. ` +
      `Genere uno con: node -e "console.log(require('crypto').randomBytes(48).toString('hex'))"`
  );
}

if (problemas.length > 0) {
  console.error('La API no puede arrancar:');
  for (const problema of problemas) console.error(`  - ${problema}`);
  process.exit(1);
}

export const config = {
  puerto: Number(env.PORT) || 3000,
  db: {
    host: env.DB_HOST,
    port: Number(env.DB_PORT) || 3306,
    user: env.DB_USER,
    password: env.DB_PASSWORD ?? '',
    database: env.DB_NAME,
  },
  jwt: {
    secreto: env.JWT_SECRET,
    expira: env.JWT_EXPIRA,
  },
  corsOrigin: env.CORS_ORIGIN,
};
