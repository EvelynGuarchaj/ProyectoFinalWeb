# Verificación · CRM Clínica Dental Dientecitos Felices

## 1. Cómo se verificó

_Base cargada con `php artisan migrate:fresh --seeder=VerificacionSeeder` salvo que se indique otra cosa._

## 2. Matriz de criterios de aceptación

### 2.1 Panel (A)

| Criterio | Evidencia | Resultado |
|---|---|---|
| L2 · `/register` responde 404 y el login no tiene enlace de registro | `routes/auth.php:9-14`; `LoginPanelTest::test_ca1_sin_registro_ni_restablecer_contrasena`; `curl -o /dev/null -w "%{http_code}" http://localhost:8000/register` → 404 | Cumple |
| L2 · Crear asesor con coordinador Sergio (`rol`, `supervisor_id`, `activo = 1`, hash `$2y$`) | `CuentaController.php:44`, `User.php:49`; `CuentasTest::test_ca3_crear_asesor`; `SELECT rol, supervisor_id, activo, LEFT(password,4) FROM users WHERE email='<nuevo>'` | Cumple |
| L2 · Asesor sin coordinador o con el id de Valeria: mensaje junto al campo y no se crea | `CuentaRequest.php:35-38`; `CuentasTest::test_ca4_asesor_sin_coordinador`, `test_ca4_coordinador_que_no_es_coordinador_activo` | Cumple |
| L2 · Editar a Diego sin contraseña conserva el hash y sigue entrando en la API | `CuentaRequest.php:48`, `CuentaController.php:62-63`; `CuentasTest::test_ca7_contrasena_opcional_al_editar`. Falta probar `POST /api/login` con Diego (la API aún no tiene login, spec 02) | Parcial: falta la parte de la API |
| L2 · El administrador no se desactiva ni cambia su propio rol | `CuentaRequest.php:103-108`; `CuentasTest::test_ca9_el_administrador_no_se_toca_a_si_mismo` | Cumple |
| L2 · Coordinador con asesores (Sergio, Sandra) no se desactiva ni cambia de rol | `CuentaRequest.php:114-120`; `CuentasTest::test_ca10_coordinador_con_asesores` | Cumple |
| L2 · Valeria (3 pacientes) no cambia de rol; desactivada conserva sus 3 pacientes | `CuentaRequest.php:123-124`; `CuentasTest::test_ca11_asesor_con_pacientes`; `SELECT COUNT(*) FROM clientes WHERE vendedor_id = <id de Valeria>` → 3 | Cumple |
| L2 · No se borran cuentas; `DELETE /cuentas/{id}` no borra | `routes/web.php:27-28`; `php artisan route:list --path=cuentas` sin DELETE; `CuentasTest::test_ca12_no_se_borran_cuentas` (405) | Cumple |
| L2 · Valeria con contraseña correcta no entra al panel ("Esta cuenta es de asesor…") | `LoginRequest.php:71,77`; `LoginPanelTest::test_ca14_un_asesor_no_entra_al_panel` | Cumple |
| L2 · Iván (desactivado) ve "Su cuenta está desactivada…" | `LoginRequest.php:70`; `LoginPanelTest::test_ca15_una_cuenta_desactivada_no_entra` | Cumple |
| L2 · Correo inexistente y contraseña incorrecta dan el mismo mensaje | `LoginRequest.php:61`; `LoginPanelTest::test_ca16_correo_inexistente`, `test_ca16_contrasena_incorrecta_de_coordinador_y_de_asesor` | Cumple |
| L2 · Sergio desactivado con sesión abierta pierde la sesión en su siguiente petición | `Middleware/CuentaPanel.php:21-28`; `LoginPanelTest::test_ca17_*` (4 pruebas); a mano: `UPDATE users SET activo=0 WHERE email='sergio@crm.test'` y recargar | Cumple |
| L2 · Sergio en `/cuentas` o `/productos` recibe 403 y no ve esos enlaces | `Middleware/Rol.php:18`, `routes/web.php:26`; `CuentasTest::test_ca18_el_coordinador_recibe_403`; `MenuTest::test_ca18_el_coordinador_no_ve_cuentas_ni_tratamientos`; `/productos` con `ProductosTest::test_ca10_*` (spec 04) | Cumple |
| L3 · Alicia agrega "Sellante dental" a Q150.00: activo, "Requiere evaluación: no", `precio = 150.00`, `requiere_evaluacion = 0` | `ProductoController.php:32-42`, `ProductoRequest.php:29-36`; `ProductosTest::test_ca1_alta_de_sellante_dental`; `SELECT precio, activo, requiere_evaluacion FROM productos WHERE nombre='Sellante dental'` | Cumple |
| L3 · Precio `0`, `-5`, `12.345` o `abc`: vuelve con lo escrito y un mensaje junto al precio, no se guarda | `ProductoRequest.php:33` (`bail`, `numeric`, `gt:0`, `decimal:0,2`, `max`); `ProductosTest::test_ca2_precio_invalido` (6 casos, también vacío y `100000000`), `test_ca2_lo_escrito_vuelve_al_formulario` | Cumple |
| L3 · Nombre "Plan básico" (ya existe): mensaje junto al nombre | `ProductoRequest.php:29-32`, `ProductoController.php:79-86` (1062); `ProductosTest::test_ca3_nombre_repetido`, `test_carrera_1062_se_muestra_junto_al_nombre`, `test_carrera_1062_al_editar` | Cumple |
| L3 · Desactivar "Instalación": `GET /productos` ya no la devuelve y "Capacitación de personal" sigue con 2 × Q450.00 | `ProductoController.php:65-72`, `routes/web.php:34-35`; `ProductosTest::test_ca7_desactivar_y_activar_instalacion` (`SELECT nombre FROM productos WHERE activo = 1` sin Instalación). Falta probar `GET /productos` de la API (spec 11) | Parcial: falta la parte de la API |
| L3 · "Plan avanzado" a Q3,000.00: "Renovación de planes" sigue con 2 × Q2,600.00 y total Q5,650.00; cifras de control sin cambio | Sin observers sobre `Producto` (`git grep -nE "Observer\|::updated\|::saved\|observe\(" laravel/app laravel/bootstrap` vacío); `ProductosTest::test_ca6_cambiar_un_precio_no_cambia_el_pasado` (9250.00 / 22450.00 / 2512.88 / 6751.13 antes y después) | Cumple |
| L3 · No hay ruta ni botón para borrar tratamientos | `routes/web.php:33` (`except(['show', 'destroy'])`); `php artisan route:list --path=productos` sin DELETE; `ProductosTest::test_ca8_no_hay_ruta_ni_boton_para_borrar` (405) | Cumple |
| R-P9 · Alicia marca "Requiere evaluación" → `requiere_evaluacion = 1`; un coordinador no puede abrir el formulario | `ProductoRequest.php:36`, `productos/form.blade.php` (input oculto `0` + casilla `1`); `ProductosTest::test_ca9_marcar_y_desmarcar_requiere_evaluacion`, `test_ca10_el_coordinador_no_edita_tratamientos`. A mano: Alicia → Tratamientos → Editar "Plan básico" → marcar → Guardar → `SELECT requiere_evaluacion FROM productos WHERE nombre='Plan básico'` → 1; Sergio en `/productos/1/edit` → 403 | Pendiente: falta la comprobación a mano |
| L4 · Alicia ve 21 pacientes; "Sin asignar" → 6; filtro "Iván Toj" → solo Hotel Los Pinos | `ClienteController.php:23-57`, `Cliente.php:26` (`visiblePara`); `ClientesTest::test_ca3_*` (4 pruebas, también 15 + 6 por página y CF / "Disponible") | Cumple |
| L4 · Sergio ve 11 (3 de Valeria, 2 de Diego, 6 disponibles), no ve Abarrotes San Juan y su filtro solo ofrece a Valeria y Diego | `Cliente.php:26-41`, `ClienteController.php:162-168`; `ClientesTest::test_ca4_*` (4 pruebas, también `?asesor=<Carlos>` → lista vacía) | Cumple |
| L4 · Buscar `4521817-k` y `quetzal` muestra Comercial El Quetzal | `ClienteController.php:37-45` (nombre "contiene" y NIT compactado con `Nit::normalizar`, comodines escapados); `ClientesTest::test_ca5_*` (3 pruebas) | Cumple |
| L4 · Alta con NIT `4521817-k` o teléfono `+502 5555-1234`: vuelve con lo escrito y el mensaje de repetido junto al campo | `ClienteRequest.php:78-106` (`after()` con el valor normalizado); `ClientesTest::test_ca7_*` | Cumple |
| L4 · Alta con NIT `4521817-1` y teléfono `1234-5678`: mensaje junto a cada campo, lo escrito se conserva | `Rules/NitValido.php`, `Rules/TelefonoValido.php`; `ClientesTest::test_ca8_*` | Cumple |
| L4 · "Paciente Prueba" con NIT `cf` y teléfono `(502) 7761.9999` → `nit = NULL`, `telefono = '77619999'`, `vendedor_id = NULL`; la lista muestra "CF" y "Disponible" | `ClienteRequest.php:111-121` (`datos()`); `ClientesTest::test_ca9_*`; `SELECT nit, telefono, vendedor_id FROM clientes WHERE nombre='Paciente Prueba'` | Cumple |
| L4 · Cada entrada de la sección 10 en el alta del panel se guarda o se rechaza como dice la tabla | `Support/Nit.php`, `Support/Telefono.php`; `ClientesTest::test_ca10_seccion_10_en_el_alta` (12 casos) | Cumple |
| L4 · Repetido que se cuela entre la validación y el `INSERT` (1062): error junto al campo, nunca página de error | `ClienteController.php:146-158`; `ClientesTest::test_ca15_*` (al crear y al editar) | Cumple |
| L4 · Alicia elimina Café Zunil; Ferretería Xela muestra "No se puede eliminar: el paciente tiene planes de tratamiento." | `ClienteController.php:120-139` (también 1451 por carrera); `ClientesTest::test_ca16_*` (5 pruebas, también solo planes cerrados y actividades en cascada). Falta ver que no aparece en `GET /clientes/disponibles` (spec 06) | Parcial: falta la parte de la API |
| L4 · Sergio envía a mano `DELETE` de Café Zunil → 403 y el paciente sigue | `routes/web.php:45` (grupo `rol:administrador`); `ClientesTest::test_ca17_*`; `php artisan route:list --path=clientes -v` (DELETE solo con `rol:administrador`) | Cumple |
| L4 · Sandra asigna Agencia Cuatro Caminos a Carlos (9 → 10); después Escuela Futuro → "Ese asesor ya tiene 10 pacientes, el máximo permitido." y sigue disponible | `Services/Cartera.php:37-53,116-162` (candado `users` → `clientes`, contar, asignar), `ClienteController.php:162-174`; `AsignacionTest::test_ca1_*` (4 pruebas, servicio y HTTP). Falta ver Agencia Cuatro Caminos en `GET /clientes` con el token de Carlos (spec 06) | Parcial: falta la parte de la API |
| L4 · Sergio envía a mano asignar con Carlos (otro equipo) o Iván (desactivado) → "Elija un asesor activo de su equipo." junto al campo, sin cambios | `AsignarRequest.php:37-53` (`exists` con rol, activo y equipo), `Cartera.php:116-128` (se repite tras el candado); `AsignacionTest::test_ca2_*`, `test_ca1_servicio_rechaza_asesor_ajeno_desactivado_o_no_vendedor` | Cumple |
| L4 · Sergio reasigna Comercial El Quetzal a Diego: abiertos a Diego, "Renovación de planes" con Valeria, la actividad sigue en el paciente; Valeria 2 ventas, Q9,250.00, pronóstico Q450.00; Diego pronóstico Q2,175.38 y 3 de 10 | `Cartera.php:49-53` (solo `Oportunidad::ABIERTAS`); `AsignacionTest::test_ca4_reasignar_mueve_solo_planes_abiertos` (cifras por SQL); en el reporte: `ReporteTest::test_ca13_reasignacion_en_el_reporte` (Diego 3 de 10 · Q2,175.38; Valeria 2 · Q9,250.00 · Q450.00; equipo sin cambio) | Cumple |
| L4 · Sergio intenta liberar Ferretería Xela → mensaje de planes abiertos y sigue con Valeria | `Cartera.php:91-110`, `ClienteController.php:177-188`; `AsignacionTest::test_ca10_no_libera_con_plan_abierto` | Cumple |
| L4 · Sergio libera María Coyoy → disponible, Valeria 2 de 10, "Soporte del hogar" sigue de Valeria (noviembre: 1 venta, Q3,600.00) | `Cartera.php:91-110`; `AsignacionTest::test_ca11_*` (también 404 con paciente ajeno) | Cumple |
| L4 · Sandra reasigna Hotel Los Pinos de Iván (desactivado) a Carlos | `AsignacionTest::test_ca5_reasignar_paciente_de_ivan_a_carlos` | Cumple |
| L4 · Asesor opcional al crear, con el mismo candado; cartera llena o asesor ajeno → error junto al campo y no se crea | `Cartera.php:66-82` (`INSERT` después del candado de `users`), `ClienteRequest.php:52-57`, `ClienteController.php:75-89`, `clientes/form.blade.php:33-47`; `AsignacionTest::test_ca12_*`, `test_ca13_*`; `ClientesTest::test_ca11_vendedor_id_ajeno_a_mano_se_rechaza` | Cumple |
| L4 · Selector con los asesores que la persona puede usar y "x de 10"; asignar al mismo asesor no cambia nada; el error sale solo en la fila del paciente | `ClienteController.php` (`asesoresAsignables`), `clientes/_asignar.blade.php`; `AsignacionTest::test_ca6_*`, `test_ca7_*`, `test_ca8_*`, `test_ca9_*` | Cumple |
| L5 · Sergio en `/equipo?mes=2026-10`: Diego "2 de 10", 1 venta, Q6,000.00; Valeria "3 de 10", 2 ventas, Q9,250.00; no ve a Carlos ni a Iván | `EquipoController.php:18-33` (`withCount` + `leftJoinSub`), `Reporte.php:45-53` (`ventasDelMes`, compartida con el reporte desde el spec 14: `DATE_FORMAT(o.fecha_cierre - INTERVAL 6 HOUR, '%Y-%m') = ?`, `COUNT(DISTINCT o.id)`, `SUM(l.cantidad * l.precio_unitario)`); `EquipoTest::test_ca1_equipo_de_sergio_en_octubre` | Cumple |
| L5 · Sergio en `/equipo?mes=2026-11`: Valeria 1 venta y Q3,600.00 ("Equipo para sucursal", `2026-11-01 03:30` UTC), Diego 0 y Q0.00 | `EquipoTest::test_ca2_equipo_de_sergio_en_noviembre` | Cumple |
| L5 · Sandra en `/equipo?mes=2026-10`: Carlos "9 de 10", 1 venta, Q7,200.00; Iván "Desactivado", "1 de 10", 0 y Q0.00 | `equipo/index.blade.php` (etiqueta "Desactivado"); `EquipoTest::test_ca3_equipo_de_sandra_con_desactivado` | Cumple |
| L5 · Alicia en `/equipo` → 403 | `routes/web.php:39-41` (grupo `rol:supervisor`); `EquipoTest::test_ca4_el_administrador_recibe_403`; `php artisan route:list --path=equipo` (solo `GET equipo`) | Cumple |
| L5 · Por omisión, mes actual de Guatemala; selector `?mes=AAAA-MM`; mes no válido → mes actual con "Elija un mes válido (AAAA-MM)." y 200 | `Support/Mes.php:16-38` (`now('America/Guatemala')`, `^\d{4}-(0[1-9]\|1[0-2])$`; compartido con `/reportes` desde el spec 14); `EquipoTest::test_ca5_mes_por_omision_en_hora_de_guatemala` (03:30 UTC del 1 nov → octubre; 06:30 → noviembre), `test_ca6_mes_no_valido_usa_el_mes_actual_con_aviso` (`2026-13`, `hola`, `2026-1`, `mes[]`) | Cumple |
| L5 · Menú "Mi equipo" solo para el coordinador; sin sesión → login | `layouts/navigation.blade.php:8`; `EquipoTest::test_ca7_*`, `test_ca8_*`; `MenuTest` ya usa la ruta real | Cumple |
| L5 · Sergio abre a mano la URL de un paciente o de un plan del equipo de Sandra → 404 | Paciente: ver S5 en la sección 3.1 (`ClientesTest::test_ca6_*`). Plan: `Oportunidad.php:59-73` (`visiblePara`), `OportunidadController.php:56-60` (`findOrFail`); `OportunidadesTest::test_ca7_ajeno_responde_404` | Cumple |
| L6 · Alicia en `/oportunidades` ve los 11 planes; filtro "Aceptado" → 5; filtro "Diego Castillo" → 2; los dos juntos → 1 | `OportunidadController.php:19-48` (filtros como parámetros, orden reciente, 15 por página); `OportunidadesTest::test_ca1_administrador_ve_todo_y_filtra`, `test_visible_para_por_rol` | Cumple |
| L6 · Sergio ve 9 planes (7 de Valeria y 2 de Diego) y ninguno de Carlos; Sandra ve 2 | `Oportunidad.php:59-73` (por `oportunidades.vendedor_id`); `User::asesoresVisiblesPara` para el filtro; `OportunidadesTest::test_ca2_coordinador_ve_su_equipo` | Cumple |
| L6 · "Equipo para sucursal" en la lista: Q3,600.00 y cierre `31 oct 2026, 21:30` (`2026-11-01 03:30:00` UTC) | `Oportunidad.php:80-86` (`conTotal`: `COALESCE(SUM(cantidad * precio_unitario), 0)` en subconsulta), `Formato::fechaGT`; `OportunidadesTest::test_ca3_total_y_fecha_de_cierre`, `test_con_total_calcula_en_sql` | Cumple |
| L6 · Detalle de "Renovación de planes": Plan avanzado 2 × Q2,600.00 = Q5,200.00, Instalación 1 × Q450.00 = Q450.00, total Q5,650.00, 5 registros de Valeria López; "Plan familiar" muestra "Eligió a la competencia" | `OportunidadController.php:56-81` (subtotal con `selectRaw` fija, historial por `created_at`), `oportunidades/show.blade.php:19-20` (motivo solo si `perdida`), `:73` ("—" en la creación); `OportunidadesTest::test_ca4_*`, `test_ca5_motivo_del_rechazo`, `test_ca6_precio_guardado` (precio de la línea, no del catálogo) | Cumple |
| L6 · `PATCH` o `DELETE` a mano a `/oportunidades/{id}` no cambia nada | `routes/web.php:41-43` (`only(['index', 'show'])`); `php artisan route:list --path=oportunidades` (solo dos GET); `OportunidadesTest::test_ca9_solo_lectura` (405 en PATCH, PUT y DELETE; base sin cambios), `test_ca9_lista_sin_acciones_de_escritura` | Cumple |
| L6 · Menú "Planes de tratamiento" para administrador y coordinador; filtros no válidos se ignoran; título enlazado desde el paciente (sin enlace si el plan es de otro equipo) | `layouts/navigation.blade.php:10`; `ClienteController.php:106-114`, `clientes/show.blade.php:59`; `OportunidadesTest::test_ca10_menu_y_acceso`, `test_ca8_filtros_no_validos`, `test_ca12_*`; `MenuTest` ya usa la ruta real | Cumple |
| L6 · Alicia en `/reportes?mes=2026-10`: Valeria 3 de 10 · 2 · Q9,250.00 · Q2,512.88; Diego 2 de 10 · 1 · Q6,000.00 · Q112.50; Carlos 9 de 10 · 1 · Q7,200.00 · Q4,125.75; Iván "Desactivado" en 0; equipo de Sergio 3 · Q15,250.00 · Q2,625.38; equipo de Sandra 1 · Q7,200.00 · Q4,125.75; toda la clínica 4 · Q22,450.00 · Q6,751.13 | `Reporte.php:45-53` (ventas del mes en hora de Guatemala, mes como `?`), `:59-66` (`pronosticoAbierto`: `CASE` con 0.10/0.25/0.50/0.75, sin mes y sin redondear), `:73-118` (`porAsesor`, `totales`, `porEquipo`: `ROUND` una sola vez por fila), `:164-170` (alcance con `User::asesoresVisiblesPara` como subconsulta); `ReporteTest::test_ca1_por_asesor_del_administrador_en_octubre`, `test_ca1_por_equipo_y_toda_la_clinica`, `test_ca1_pagina_del_administrador` | Cumple |
| L6 · Por tratamiento (Alicia, octubre): Plan básico 5 · Q6,000.00; Plan avanzado 2 · Q5,200.00 · pronóstico Q6,188.63; Instalación 1 · Q450.00 · Q562.50; Soporte anual 3 · Q10,800.00; total 11 · Q22,450.00 · Q6,751.13 | `Reporte.php:35-39` (`SUMAS_LINEAS`), `:124-162` (`porTratamiento`, `totalPorTratamiento`, `lineas`: ganadas del mes u abiertas; de `productos` solo el nombre); `ReporteTest::test_ca2_por_tratamiento_del_administrador`, `test_ca2_pagina_por_tratamiento` | Cumple |
| L6 · Alicia en `/reportes?mes=2026-11`: Valeria 1 · Q3,600.00 con el mismo pronóstico Q2,512.88 | `ReporteTest::test_ca3_noviembre_cambia_ventas_pero_no_pronostico`, `test_ca3_pagina_noviembre` | Cumple |
| L6 · Sergio en `/reportes?mes=2026-10`: solo Valeria y Diego, total del equipo 3 · Q15,250.00 · Q2,625.38, por tratamiento Plan básico 5 · Q6,000.00, Plan avanzado 2 · Q5,200.00, Instalación 1 · Q450.00, Soporte anual 1 · Q3,600.00 | `ReporteController.php:17-31` (tabla por equipo solo para el administrador); `ReporteTest::test_ca4_coordinador_sergio`, `test_ca4_por_tratamiento_de_sergio`, `test_ca4_pagina_de_sergio` | Cumple |
| L6 · Sandra en `/reportes?mes=2026-10`: 1 · Q7,200.00 · Q4,125.75, con Iván en 0 | `ReporteTest::test_ca5_coordinadora_sandra`, `test_ca5_pagina_de_sandra` | Cumple |
| L6 · El pronóstico de un grupo se redondea al final, no sumando redondeados | `Reporte.php:28-29` (`SUMAS`: `ROUND(COALESCE(SUM(p.bruto), 0), 2)`); `ReporteTest::test_ca6_pronostico_de_grupo_se_redondea_al_final` (bruto de Valeria 2512.875; equipo de Sergio 2625.375 → 2625.38) | Cumple |
| L6 · Reporte: mes por omisión en hora de Guatemala; mes no válido → aviso y mes actual; menú "Reporte"; sin sesión → login | `Support/Mes.php`, `routes/web.php:48`; `ReporteTest::test_ca7_mes_por_omision_y_no_valido` (03:00 UTC del 1 nov → octubre; `2026-13`, `abc`, `mes[]`), `test_ca10_menu_y_acceso`; `php artisan route:list --path=reportes` (solo `GET reportes`) | Cumple |
| L6 · Cambiar el precio de Plan avanzado a Q3,000.00 no cambia el reporte | `git grep -n "productos.precio\|pr.precio" laravel/app/Services/Reporte.php` vacío; `ReporteTest::test_ca8_cambiar_un_precio_no_cambia_el_reporte` | Cumple |
| L6 · Prueba central: un plan que Valeria gana hoy aparece en el reporte del mes actual | `ReporteTest::test_ca9_plan_ganado_hoy_aparece_en_el_reporte` (simula la SPA con `fecha_cierre` = ahora en UTC). Falta la prueba de punta a punta con la SPA (F3, spec 19/21) | Parcial: falta ganarlo desde la SPA |
| L6 · Reporte: el mes solo entra como parámetro; sin `{!! !!}` | `git grep -n "Raw" laravel/app/Services/Reporte.php` (el mes solo en `:50` y `:155`, como `?` con `[$mes]`); `git grep -n "{!!" laravel/resources/views/reportes` vacío; `ReporteTest::test_ca12_nombre_se_escapa` | Cumple |
| L7 · Alta de paciente vacía: mensajes en español junto a cada campo; correo `no-es-correo` → "Escriba un correo válido." con el resto conservado | `ClienteRequest.php:43-71`; `ClientesTest::test_ca11_*` (5 pruebas). La paginación de la lista aún sale en inglés hasta el spec 16 | Cumple para el formulario de pacientes |
| Sección 10 · NIT y teléfono en el panel | `NitTest` y `TelefonoTest` (sección 10 + bordes, funciones y reglas); falta comparar con `src/util/nit.js` y `telefono.js` (spec 06) | Parcial: falta la API |

### 2.2 API (B)

| Criterio | Evidencia | Resultado |
|---|---|---|

### 2.3 SPA · login, cartera, actividades, resumen y base (A)

_Spec 10. "Simulación" = flujo completo en Node con DOM, `location.hash`, `localStorage` y API simulados (respuestas del spec 02). La comprobación en el navegador contra la API real queda pendiente._

| Criterio | Evidencia | Resultado |
|---|---|---|
| F1 · Valeria entra: `#/cartera`, su nombre en la barra y solo la clave `sesion` con `token` y `usuario` | `vistas/login.js:47` (`entrar`: `guardarSesion` y `#/cartera`), `sesion.js:17` (guarda solo `{ token, usuario }`), `app.js:88` (nombre escapado); simulación CA1 | Cumple en simulación; pendiente en el navegador |
| F1 · Coordinador, administrador o asesor desactivado: mensaje del 403 de la API, sigue en `#/login`, `localStorage` vacío | `vistas/login.js:68` (`mostrarError(e)` con el `error` de la API); simulación CA2 | Cumple en simulación; pendiente en el navegador |
| F1 · Contraseña incorrecta: mensaje del 401, sin el aviso de sesión terminada; 422 junto a cada campo | `api.js:53` (el 401 de `/auth/login` no termina la sesión), `vistas/login.js:62` (422 → `errores` del formulario); simulación CA3 y CA4 | Cumple en simulación; pendiente en el navegador |
| F1 · "Cerrar sesión" borra la clave `sesion` y muestra `#/login` | `app.js:139` (`salir`); simulación CA5 | Cumple en simulación; pendiente en el navegador |
| F1 · 401 en plena sesión: borra la sesión, `#/login` y "Su sesión terminó. Ingrese de nuevo." | `api.js:53-63` (aviso con `conservar`), `util.js:69` (`mostrarError` ignora `sesionTerminada`), `app.js:39` (el aviso sobrevive una navegación); simulación CA6 | Cumple en simulación; pendiente con Valeria desactivada desde el panel |
| F1 · Sin sesión, `#/actividades` → `#/login`; con sesión, `#/login` → `#/cartera` | `app.js:42-51`; simulación CA7 | Cumple en simulación; pendiente en el navegador |
| F6 · Solo hay escuchadores delegados | `git grep -n "addEventListener\|onclick" frontend/js` → solo `app.js:151` (`click`), `app.js:164` (`submit`) y `app.js:183` (`hashchange` de `window`) | Cumple |
| F6 · Indicador de carga y botones de envío desactivados con `cargando > 0` | `api.js` (suma al empezar, resta en `finally:67`), `app.js:96` (spinner), `app.js:123` (`disabled`) | Pendiente: "Slow 3G" en el navegador |
| F6 · API apagada: "No se pudo conectar con el servidor. Verifique que la API esté encendida." con "Reintentar" | `api.js:38` (`ErrorApi` con status 0), `app.js:103` (botón), `app.js:145` (`reintentar`); simulación CA10 | Cumple en simulación; pendiente en el navegador y con el origen `localhost:5500` |
| F6 · Texto de la API escapado | `util.js:10` (`escapar`); un solo `innerHTML` (`app.js:117`); datos de la API escapados en `app.js:88`, `app.js:110` y `vistas/login.js:27`; `errorCampo` escapa (`util.js:57`). Comprobación con Node de `escapar` | Cumple en el código; pendiente CA11 en el navegador |
| F6 · Utilidades (`escapar`, `quetzales`, `aISO`, `fechaGT`, `mesActualGT`) | Node: `Q9,250.00`, `2026-12-01T10:00:00-06:00`, `5 oct 2026, 10:00`, `2026-11` | Cumple |
| F6 · 375 px sin desplazamiento horizontal en `#/login`, `#/cartera`, `#/actividades` y `#/resumen` | `navbar-expand-md`, rejilla de Bootstrap, `.texto-largo`; sin anchos fijos en `css/estilos.css` | Pendiente: modo teléfono en el navegador |
| F6 · Ruta inexistente (`#/otra`, `#/`, `#/plan`) → `#/cartera`; `#/plan/5` → `params.id === '5'` | `app.js:27` (`leerHash`, con `Object.hasOwn`); simulación CA14 | Cumple en simulación; pendiente en el navegador |

### 2.4 SPA · embudo y detalle del plan (B)

| Criterio | Evidencia | Resultado |
|---|---|---|

## 3. Seguridad (S1–S8)

### 3.1 Pruebas de A

| Regla | Prueba | Resultado |
|---|---|---|
| S5 · Sergio abre a mano el detalle o la edición de Abarrotes San Juan (de Carlos) o envía el `PUT` directamente → 404, sin cambios | `ClienteController.php` (`visiblePara(...)->findOrFail()` en show, edit, update y destroy), `ClienteRequest.php:32-38` (404 antes de validar); `ClientesTest::test_ca6_*` (también `PATCH`, datos incompletos e id inexistente) | Cumple |
| S7 · Paciente con nombre `<img src=x onerror=alert(1)>` se ve literal en la lista, el detalle y la edición del panel | Vistas `clientes/` solo con `{{ }}` (`git grep -n "{!!" laravel/resources/views/clientes` vacío); `ClientesTest::test_ca19_el_nombre_se_muestra_literal`. Asignado a Valeria al crearlo: `AsignacionTest::test_ca15_nombre_con_html_asignado_a_valeria_se_ve_literal` (lista y detalle). Falta la parte de la SPA | Parcial: panel cumple |

| S5 · Sergio envía a mano asignar o liberar Abarrotes San Juan (de Carlos) → 404, sin cambios | `AsignarRequest.php:28-32` (404 antes de validar), `ClienteController.php:164,179`, `Cartera.php:134-155` (se repite tras el candado); `AsignacionTest::test_ca3_*`, `test_ca11_liberar_paciente_ajeno_da_404`, `test_ca1_servicio_paciente_ajeno_da_404` | Cumple |
| S7 · Asesor con nombre `<img src=x onerror=alert(1)>` en el equipo de Sergio se ve literal en `/equipo` | Vista `equipo/index.blade.php` solo con `{{ }}` (`git grep -n "{!!" laravel/resources/views/equipo` vacío); `EquipoTest::test_ca9_nombre_del_asesor_se_muestra_literal` | Cumple (panel) |
| S5 · Sergio abre a mano el detalle de un plan de Carlos ("Plan anual") → 404; también un id inexistente o no numérico | `Oportunidad.php:59-73` (`visiblePara`), `OportunidadController.php:56-60` (`findOrFail`, sin enlace implícito de modelo); `OportunidadesTest::test_ca7_ajeno_responde_404` | Cumple |
| S7 · Plan con título `<img src=x onerror=alert(1)>` se ve literal en la lista y el detalle del panel | Vistas `oportunidades/` solo con `{{ }}` (`git grep -n "{!!" laravel/resources/views/oportunidades` vacío); `OportunidadesTest::test_ca11_xss` | Cumple (panel) |
| S8 · El panel usa el mismo candado que la API (`SELECT id FROM users WHERE id = ? FOR UPDATE`) | `Cartera.php:118`. A mano: en `mysql`, `START TRANSACTION; SELECT id FROM users WHERE id = <Carlos> FOR UPDATE;` sin confirmar; en el panel, Alicia asigna un disponible a Carlos → la página espera hasta el `COMMIT`. Con la API (spec 06): Carlos con 9, el panel asigna y la API toma a la vez → solo uno lo consigue | Pendiente: falta la comprobación a mano y la parte de la API |

### 3.2 Pruebas de B

| Regla | Prueba | Resultado |
|---|---|---|

## 4. Peticiones simultáneas (S8)

_Salida de `npm run simultaneas` en api/._

## 5. Cifras de control obtenidas

| Quién | Mes | Panel (A) | API (B) | ¿Coinciden con el enunciado? |
|---|---|---|---|---|
| Valeria López | octubre 2026 | 2 · Q9,250.00 · Q2,512.88 · 3 de 10 | | Panel: sí |
| Valeria López | noviembre 2026 | 1 · Q3,600.00 · Q2,512.88 · 3 de 10 | | Panel: sí |
| Diego Castillo | octubre 2026 | 1 · Q6,000.00 · Q112.50 · 2 de 10 | | Panel: sí |
| Carlos Ajú | octubre 2026 | 1 · Q7,200.00 · Q4,125.75 · 9 de 10 | | Panel: sí |
| Equipo de Sergio Ruiz | octubre 2026 | 3 · Q15,250.00 · Q2,625.38 | — | Sí |
| Equipo de Sandra Pérez | octubre 2026 | 1 · Q7,200.00 · Q4,125.75 | — | Sí |
| Toda la empresa | octubre 2026 | 4 · Q22,450.00 · Q6,751.13 | — | Sí |
| Por producto | octubre 2026 | Plan básico 5 · Q6,000.00; Plan avanzado 2 · Q5,200.00; Instalación 1 · Q450.00; Soporte anual 3 · Q10,800.00 | — | Sí |

_Panel: `ReporteTest` (spec 14) con `VerificacionSeeder`. La columna API la llena B con `GET /reportes/resumen` (spec 15)._

## 6. Bitácora de IA (P5)

### 6.1 Casos de A

### Caso 1 · El login revisaba el rol antes que la cuenta desactivada
- **Requisito:** L2
- **Qué propuso el agente:** en la sesión de `/spec` del spec 03, la sección 2.4 ordenaba las comprobaciones de `LoginRequest::authenticate()` así: primero el rol y después el estado, con la nota "si un vendedor además está desactivado, gana el mensaje de asesor: es el primero que se comprueba".
  ```php
  // orden propuesto
  si rol = 'vendedor' → "Esta cuenta es de asesor. Ingrese desde la aplicación de asesores."
  si activo = 0       → "Su cuenta está desactivada. Comuníquese con el administrador."
  ```
- **Por qué estaba mal:** contradecía L2 de `docs/especificacion.md`, que dice que Iván (desactivado) con la contraseña correcta ve "Su cuenta está desactivada. Comuníquese con el administrador.". En la semilla de verificación Iván es asesor, así que con ese orden habría visto el mensaje de asesor. Eso incumplía también CA15 del propio spec y daba otro mensaje que la API, que a Iván le responde 403 con el de cuenta desactivada (A1).
- **Cómo lo detectamos:** en revisión, al implementar el Paso 2 con `/spec-impl` y antes de escribir el código. Al comparar la sección 2.4 con CA15 y con la semilla, salió que Iván es `vendedor` y está desactivado a la vez.
- **Corrección:** `LoginRequest` comprueba primero `activo` y después el rol, y la prueba `LoginPanelTest::test_ca15_una_cuenta_desactivada_no_entra` lo verifica con Iván. Se actualizaron la sección 2.4 del spec 03 y su tabla de decisiones (sección 5) · commit `d8cad35`

### 6.2 Casos de B