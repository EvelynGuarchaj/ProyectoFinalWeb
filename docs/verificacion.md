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

### 2.2 API (B)

| Criterio | Evidencia | Resultado |
|---|---|---|

### 2.3 SPA · login, cartera, actividades, resumen y base (A)

| Criterio | Evidencia | Resultado |
|---|---|---|

### 2.4 SPA · embudo y detalle del plan (B)

| Criterio | Evidencia | Resultado |
|---|---|---|

## 3. Seguridad (S1–S8)

### 3.1 Pruebas de A

| Regla | Prueba | Resultado |
|---|---|---|

### 3.2 Pruebas de B

| Regla | Prueba | Resultado |
|---|---|---|

## 4. Peticiones simultáneas (S8)

_Salida de `npm run simultaneas` en api/._

## 5. Cifras de control obtenidas

| Quién | Mes | Panel (A) | API (B) | ¿Coinciden con el enunciado? |
|---|---|---|---|---|

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