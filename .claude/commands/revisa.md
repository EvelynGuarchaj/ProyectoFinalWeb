---
description: Revisa el Pull Request de la compañera y explica su código
argument-hint: <nombre de la rama>
---
Revisa los cambios de la rama $ARGUMENTS frente a main: ejecuta git fetch y git diff main...origin/$ARGUMENTS.
Es trabajo de mi compañera. No modifiques archivos.

1. Busca: contradicciones con CLAUDE.md o con la especificación, SQL concatenado con datos del usuario, datos expuestos (hashes, vendedor_id, supervisor_id), problemas con peticiones simultáneas, archivos que no son de su dueña, y textos sin escapar.
2. Dame una lista de observaciones para dejar en el PR (archivo, línea, qué cambiar).
3. Explícame lo más importante de ese código para que yo pueda defenderlo.
