# AGENTS.md

Sistema de asistencia municipal (Municipalidad de Coltauco). PHP plano, sin framework.

## Entorno
- PHP 5.6+, Apache/XAMPP, MariaDB. Sin Composer, sin build/lint/test pipeline. Verificación mínima: `php -l <archivo>`.
- Todo el código, UI, comentarios y commits están en español — mantener esa convención.
- No hay dump SQL en el repo: el esquema de 4 tablas (`marcaciones`, `marcaciones_resumen`, `marcaciones_importaciones`, `usuarios_sistema`) solo está documentado en `README.md`.

## Archivos locales obligatorios (gitignore — no existen en el repo)
- `inc/db.php` — debe definir `function db()` que devuelva un singleton PDO (MySQL, utf8mb4, ERRMODE_EXCEPTION).
- `auth.php` — guardia de sesión: `session_start()` + redirect a `login.php` si falta `$_SESSION['usuario_id']`.
- `hash.php` — script local para crear el primer usuario admin con `password_hash()`.
- Cada página protegida empieza con `require_once __DIR__ . '/inc/db.php'; require_once __DIR__ . '/auth.php';`. `login.php` es la única página sin `auth.php`. No hay bootstrapping centralizado.

## Convenciones de código
- No hay archivo de helpers compartidos: `h()`, `normalizar_rut()`, `validar_rut()`, `formatear_rut()`, `hms_a_minutos()`, `minutos_a_hhmm_display()`, `nombre_dia_es()` se duplican en cada página. Para un archivo nuevo, copiar la versión de una página existente.
- Compatibilidad PHP 5.6 es restricción dura (hubo un commit de fix explícito). Los exportadores usan `array(...)`; NO usar `??`, arrow functions ni tipos escalares declarados.
- RUT chileno: `normalizar_rut()` quita puntos/guiones/espacios; la importación guarda `rut_base` = número sin el dígito verificador.

## Lógica de negocio (no obvia)
- Estados de `marcaciones_resumen.estado`: `OK` = 2 marcas con entrada < salida · `OBSERVADO` = 3+ marcas · `INCOMPLETO` = 1 marca · `ERROR` = salida anterior a entrada o solo salida.
- `editado_manual = 1` protege ediciones manuales ante reimportaciones: `recalcular_parcial()` usa `INSERT ... ON DUPLICATE KEY UPDATE` que NO pisa filas marcadas como manuales.

## Importación (`importar_marcaciones.php`)
- Endpoint AJAX es el mismo archivo: POST + `?action=importar`, responde NDJSON línea a línea con `flush()` (eventos: `parsing`/`parsed`/`dedup`/`inserting`/`resumen`/`done`).
- Dedup por `md5(dpto|nombre|numero|fecha_hora)` en `hash_registro`; `INSERT IGNORE` en lotes de 500; recálculo parcial solo de pares `(rut_base, fecha)` afectados.

## Exportadores XLSX
- `inc/xlsx_generator.php` es generador propio sin librerías (clase `ExcelExporter`; cascada ZipArchive → PurePhpZip → CSV). Estilos vía const `STYLE_*`.
- `exportar_inasistencias.php` y `exportar_horas_mes.php` se invocan por GET (`mes`, `dpto`, `q`), llaman `ob_start()` al inicio y escriben la descarga con `header()` + echo directo. No llamarlos tras output emitido.

## Sesión y roles
- `$_SESSION['usuario_id'|'usuario_nombre'|'usuario_rol']` se setean en `login.php`.
- El control por rol (`admin`/`operador`) está documentado en el README pero NO está implementado en el código: no hay checks de `usuario_rol` fuera de `login.php`.
- Las páginas embeben `navbar.php` para la navegación compartida (marca la pestaña activa vía `$current_page`); incluir también el favicon/JS que trae.
