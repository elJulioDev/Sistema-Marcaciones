# AGENTS.md

Sistema de Marcaciones — control de asistencia. Uso público y escalable (dejó de ser específico de Coltauco).

## ⚠️ REESTRUCTURACIÓN EN CURSO

Se está transformando el sistema de un montón de páginas PHP planas a una arquitectura profesional. **Hasta que se complete la migración, las secciones de abajo describen el estado LEGADO que aún convive en la raíz.** Actualizar esta sección al cerrar cada fase.

### Objetivo
- Sistema genérico (sin marca Coltauco), PHP 8 moderno, configuración vía `.env`, estructura por capas.
- Sin Composer: mantener cero dependencias externas (autoloader propio `spl_autoload_register`).
- Naming confirmado con el usuario: DB `marcaciones`, `BASE_URL=/Sistema-Marcaciones` (el `.env` con `sistema_bodega` fue copy-paste y NO se usa).

### Estructura objetivo
```
public/            # Document root (front controller + .htaccess + assets)
  index.php        # bootstrap + Router
  .htaccess        # reescritura al front controller
  assets/          # css/js/img (migra desde static/)
app/
  Core/            # Env, Database, Router, Controller base, View
  Controllers/     # Auth, Panel, Calendario, Importacion, Observaciones, Marcacion, Consulta, Mes, Exportar*
  Models/          # Marcacion, MarcacionResumen, MarcacionImportacion, Usuario
  Services/        # ImportadorMarcaciones (NDJSON), ExcelExporter
  Support/         # helpers globales: h(), base_url(), RUT, fecha, hora
  Views/           # layouts/ + vistas por módulo
config/
  routes.php
database/
  schema.sql       # esquema público (ya existe)
  schema_demo.sql  # datos demo públicos (ya existe, julio 2026, admin/admin123)
storage/logs/
.env / .env.example
```

### Fases
1. **Fundación (hecha):** `.env.example`, `database/schema_demo.sql`, `.gitignore` actualizado, este plan.
2. **Core (hecha):** `Env` (cargador .env), `Database` (PDO singleton), autoloader PSR-4 sin Composer, `public/index.php` + `Router`, helpers centralizados en `app/Support`, `View` + layout base, `base_url()` en todas las URLs/assets, `.htaccess` raíz (coexiste con el legado). Prueba en `http://localhost/Sistema-Marcaciones/`.
3. **Auth y roles:** `AuthController` (login/logout), middleware de sesión + control real de roles `admin`/`operador` (hoy solo se documenta, no se aplica), navbar en layout.
4. **Migración de módulos** a Controllers/Models/Views: panel, consulta, calendario, observaciones, editar resumen, eliminar mes.
5. **Importación:** mover lógica NDJSON a `Services/ImportadorMarcaciones` (parseo, dedup md5, `INSERT IGNORE` lotes 500, `recalcular_parcial`), endpoint JSON.
6. **Exportadores:** mover `inc/xlsx_generator.php` a `Services/ExcelExporter`, controladores de exportación.
7. **Des-rotulación:** quitar textos/logo/footer Coltauco, `README.md` nuevo.
8. **Limpieza/QA:** borrar archivos planos de la raíz (`login.php`, `panel.php`, `inc/db.php`, `auth.php`, `hash.php`, `navbar.php`, etc.), `php -l` global, probar flujo completo.

### Gotchas del front controller (Apache/XAMPP)
- `.htaccess` raíz reescribe: `^assets/(.*)$ → public/assets/$1` (assets directos), archivos `.php`/`static/` reales del legado se sirven directos, el resto → `public/index.php`. No usar `%{DOCUMENT_ROOT}` para ubicar `public/` (apunta a htdocs, no al proyecto).
- En reglas por-directorio, el backref del patrón de la regla en un `RewriteCond` es `$1` (NO `%1`). Evitar `public/$1` como destino (quirk 404 en este Apache); usar `public/assets/$1`.
- `Router` resta `BASE_URL` del `REQUEST_URI` para obtener la ruta; toda URL/asset se genera con `base_url()`.

### Reglas nuevas desde aquí
- PHP 8.x (XAMPP 8.2), `declare(strict_types=1)`; usar `??`, arrow functions, types.
- Todo el código nuevo lee configuración de `.env` vía `Core/Env` — **nada hardcodeado** (host, credenciales, nombre de BD, `BASE_URL`, zona horaria).
- Helpers centralizados (RUT, hora, fecha, `h()`) en `app/Services` — nunca duplicarlos por archivo.
- URL base siempre vía `BASE_URL` (assets, links, redirects).

---

## Estado actual (LEGADO — plano, será reemplazado)

Sistema de asistencia municipal. PHP plano, sin framework.

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
