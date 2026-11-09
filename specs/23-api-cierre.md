# 23 · Cierre de API y entrega de B

**Estado:** Implementado

**Depende de:** specs 02, 06, 08, 11, 13 y 15; base `VerificacionSeeder`

**Dueña:** B

**Objetivo:** dejar documentada la API, la colección final de Postman y una comprobación reproducible de la carrera de cartera para el cierre.

## Alcance

- `api/pruebas/simultaneas.js`: envía dos altas concurrentes para Carlos, que comienza con 9 de 10 pacientes. Comprueba un `201`, un `409` y el conteo final de 10. Limpia el registro temporal creado.
- `README.md`: preparación, pasos para levantar panel/API/SPA, endpoints y ejecución de Postman.
- `docs/api.postman_collection.json`: colección de verificación de los endpoints, respuestas de error, regla propia y cifras de control.
- `docs/verificacion.md`: resultados de API, seguridad, concurrencia, criterios de ambos recorridos y bitácora de IA.

## Ejecución

1. Cargar `VerificacionSeeder` y arrancar MySQL y la API.
2. Definir `CORREO_CARLOS` y `CLAVE_SEMILLA` en `api/.env`.
3. Ejecutar `cd api && npm run simultaneas`.
4. Importar la colección de Postman, completar sus variables y ejecutar las carpetas en orden.
5. Abrir Laravel y la SPA; recorrer los pasos de `docs/verificacion.md` y registrar por separado evidencia automática, simulada y de navegador.

## Aceptación

- El script de concurrencia falla con detalle si no obtiene exactamente los códigos esperados o si la cartera supera el máximo.
- La colección es JSON válido y conserva los scripts de prueba de respuestas y variables encadenadas.
- El README permite preparar el entorno y enumera las rutas montadas bajo `/api`.
- El estado “Implementado” de los specs 02, 06, 08, 11, 13, 15, 19 y 21 se refleja en `specs/README.md` y en cada spec.
