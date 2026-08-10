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
  Services/        # ImportadorMarcaciones (NDJSON), ExcelExporter, ExportadorInasistencias, ExportadorHorasMes
  Support/         # helpers globales: h(), base_url(), RUT, fecha, hora
  Views/           # layouts/ + vistas por módulo
config/
  routes.php
database/
  schema.sql       # esquema público (ya existe)
  schema_demo.sql  # datos demo públicos (ya existe, julio 2026; admin 11111111-1/admin123, operador 22222222-2/operador123)
storage/logs/
.env / .env.example
```

### Fases
1. **Fundación (hecha):** `.env.example`, `database/schema_demo.sql`, `.gitignore` actualizado, este plan.
2. **Core (hecha):** `Env` (cargador .env), `Database` (PDO singleton), autoloader PSR-4 sin Composer, `public/index.php` + `Router`, helpers centralizados en `app/Support`, `View` + layout base, `base_url()` en todas las URLs/assets, `.htaccess` raíz (coexiste con el legado). Prueba en `http://localhost/Sistema-Marcaciones/`.
3. **Auth y roles (hecha):** `AuthController` (login/logout), `Core/Auth` (sesión + roles), `Models/Usuario`, middleware en `Router` (3er parámetro de `get`/`post`: `null` pública, `'login'` cualquier sesión, `'admin'` o `['admin','operador']` roles), navbar en layout con usuario + salir, vistas `auth/login` y `errors/403`. El legado (`login.php`) sigue sirviéndose aparte.
4. **Migración de módulos (hecha):** controllers/models/vistas para panel, consulta, calendario, observaciones, editar resumen, eliminar mes. Rutas `/marcacion/editar`, `/eliminar-mes` (admin).
5. **Importación (hecha):** lógica NDJSON en `Services/ImportadorMarcaciones` (parseo, dedup md5, `INSERT IGNORE` lotes 500, `recalcular_parcial` respeta `editado_manual=1`), endpoint JSON `POST /importar/importar`.
6. **Exportadores (hecha):** `inc/xlsx_generator.php` movido a `Services/ExcelExporter` (mismo generador sin librerías: ZipArchive → PurePhpZip → CsvWriter); `Services/ExportadorInasistencias` y `Services/ExportadorHorasMes` (lógica portada de los archivos raíz) + `Controllers/ExportarController`. Rutas `GET /exportar/inasistencias?rango=...&mes=...&fecha=...` y `GET /exportar/horas-mes?mes=...&dpto=...&q=...` (auth `login`). `calendario.js` apunta a las rutas nuevas.
7. **Des-rotulación (hecha):** la app nueva (`app/` + `public/`) no tiene textos Coltauco (verificado). `README.md` reescrito genérico y `database/schema.sql` sin marca en la cabecera (comentarios actualizados a la app nueva). La marca que queda en `login.php`/`navbar.php` se elimina con los archivos planos en la Fase 8, no se re-rotula.
8. **Limpieza/QA:** borrar archivos planos de la raíz (`login.php`, `panel.php`, `inc/db.php`, `auth.php`, `hash.php`, `navbar.php`, etc.), `php -l` global, probar flujo completo.

### Gotchas del front controller (Apache/XAMPP)
- `.htaccess` raíz reescribe: `^assets/(.*)$ → public/assets/$1` (assets directos), archivos `.php`/`static/` reales del legado se sirven directos, el resto → `public/index.php`. No usar `%{DOCUMENT_ROOT}` para ubicar `public/` (apunta a htdocs, no al proyecto).
- En reglas por-directorio, el backref del patrón de la regla en un `RewriteCond` es `$1` (NO `%1`). Evitar `public/$1` como destino (quirk 404 en este Apache); usar `public/assets/$1`.
- `Router` resta `BASE_URL` del `REQUEST_URI` para obtener la ruta; toda URL/asset se genera con `base_url()`.

### Reglas nuevas desde aquí
- PHP 8.x (XAMPP 8.2), `declare(strict_types=1)`; usar `??`, arrow functions, types.
- Todo el código nuevo lee configuración de `.env` vía `Core/Env` — **nada hardcodeado** (host, credenciales, nombre de BD, `BASE_URL`, zona horaria).
- Helpers centralizados (RUT, hora, fecha, `h()`) en `app/Support` — nunca duplicarlos por archivo.
- URL base siempre vía `BASE_URL` (assets, links, redirects).

---

## Estado actual del código (tras Fase 6)

Conviven dos capas: el legado plano (raíz) y la app migrada (`public/` + `app/`). Las Fases 1-6 migraron estos módulos a rutas propias en `config/routes.php`:

- login/logout (`/login`, `/logout`), panel (`/`), consulta (`/consulta`), calendario (`/calendario`), observaciones (`/observaciones`), importación (`/importar`), exportadores (`/exportar/inasistencias`, `/exportar/horas-mes`), editar marcación (`/marcacion/editar`), eliminar mes (`/eliminar-mes`).
- Los archivos planos equivalentes SIGUEN en la raíz y Apache los sirve directo (coexisten); se eliminarán en la Fase 8.

### Legado que aún existe en la raíz (se borra en Fase 8)
- Páginas planas: `login.php`, `panel.php`, `calendario_marcaciones.php`, `consulta_marcaciones.php`, `observaciones_marcaciones.php`, `editar_marcacion_resumen.php`, `eliminar_mes.php`, `importar_marcaciones.php`, `exportar_horas_mes.php`, `exportar_inasistencias.php`, `logout.php`.
- Soporte: `auth.php`, `hash.php`, `navbar.php`.
- `inc/` (gitignored): `db.php` (define `db()`), `xlsx_generator.php` (ya migrado a `Services/ExcelExporter`).
- `static/` (css/img legados).

### Marca Coltauco restante
- `login.php` (líneas 59 y 127), `navbar.php` (línea 334, "RRHH Coltauco") y `consulta_marcaciones.php` (logo alt "Municipalidad"): texto en páginas planas que se borrarán en Fase 8 — no re-rotular, solo borrar.
- `README.md`, `database/schema.sql` y la app nueva (`app/` + `public/`) ya NO tienen marca (fase 7 hecha).

### Convenciones del legado (solo aplican a los archivos planos que queden)
- Compatibilidad PHP 5.6 es restricción dura (hubo un commit de fix explícito). `array(...)`, sin `??`, arrow functions ni tipos escalares declarados. No duplicar helpers en código nuevo.
- Cada página plana protegida empieza con `require_once __DIR__ . '/inc/db.php'; require_once __DIR__ . '/auth.php';`. `login.php` es la única sin `auth.php`.

## Lógica de negocio (no obvia)
- Estados de `marcaciones_resumen.estado`: `OK` = 2 marcas con entrada < salida · `OBSERVADO` = 3+ marcas · `INCOMPLETO` = 1 marca · `ERROR` = salida anterior a entrada o solo salida.
- `editado_manual = 1` protege ediciones manuales ante reimportaciones: `recalcular_parcial()` usa `INSERT ... ON DUPLICATE KEY UPDATE` que NO pisa filas marcadas como manuales.
- RUT chileno: `normalizar_rut()` quita puntos/guiones/espacios; la importación guarda `rut_base` = número sin el dígito verificador.
