# Specs por feature

Cada feature se diseña con `/spec` y se implementa con `/spec-impl` (fernando-skills).
`docs/especificacion.md` dice QUÉ hace el sistema; cada spec dice CÓMO se construye una parte.
Los números ya están asignados para que A y B no choquen al crear specs al mismo tiempo.

| Spec | Requisitos | Dueña | Fase |
|---|---|---|---|
| 01-esquema-y-semilla | L1 | A | 1 |
| 02-api-base-login | A1, A2, base de A7 | B | 1 · Implementado |
| 03-cuentas-roles | L2 | A | 1 |
| 04-catalogo | L3 | A | 2 |
| 05-pacientes | L4 (parte 1) | A | 2 |
| 06-api-clientes | A3 | B | 2 · Implementado |
| 07-asignacion | L4 (parte 2) | A | 2 |
| 08-api-actividades | A5 | B | 2 · Implementado |
| 09-equipo | L5 | A | 2 |
| 10-spa-base | F6, F1 | A | 3 |
| 11-api-oportunidades | A4, GET /productos | B | 3 · Implementado |
| 12-planes | L6 (parte 1) | A | 3 |
| 13-api-regla-propia | Regla propia | B | 3 · Implementado |
| 14-reporte | L6 (parte 2) | A | 3 |
| 15-api-resumen | A6 | B | 3 · Implementado |
| 16-validacion | L7 | A | 3 |
| 17-semilla-sector | L1 (capa de sector) | A | 3 |
| 18-spa-cartera | F2 | A | 4 |
| 19-spa-embudo | F3 (tablero) | B | 4 · Implementado |
| 20-spa-actividades | F4 | A | 4 |
| 21-spa-plan | F3 (detalle del plan) | B | 4 · Implementado |
| 22-spa-resumen | F5 | A | 4 |
| 23-api-cierre | A7, A8, S8 | B | 5 · Implementado |

Para el cambio de requisito (spec-v2) y los retos se usan números desde el 24. Antes de usar uno, anótalo en esta tabla en un PR pequeño.

Estados: Borrador → En revisión → Aprobado → Implementado (u Obsoleto). Solo una persona cambia el estado.
Rama de cada spec: `spec-NN-slug`.
