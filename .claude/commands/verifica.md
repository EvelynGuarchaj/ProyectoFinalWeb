---
description: Comprueba un requisito contra el código, sin modificar nada
argument-hint: <código del requisito>
---
Comprueba el requisito $ARGUMENTS contra el código actual. No modifiques archivos.

Para cada criterio Dado/Cuando/Entonces de $ARGUMENTS en docs/especificacion.md:
- di si se cumple y en qué archivo y línea,
- y cómo demostrarlo (comando, consulta SQL, pasos en el navegador o petición).

Si alguno no se cumple, dímelo sin corregirlo.
Al final, dame las filas para docs/verificacion.md con el formato:
| Criterio | Evidencia | Resultado |
