# AGENTS.md

Sistema de Marcaciones — control de asistencia. Uso público y escalable.

## Arquitectura

Aplicación PHP 8 por capas con un único front controller, cero dependencias externas (sin Composer; autoloader propio `spl_autoload_register` mapeando `App\` → `app/`) y configuración vía `.env`.

```
public/            # Document root (front controller + .htaccess + assets)
  index.php        # bootstrap + Router
  .htaccess        # reescritura al front controller
  assets/          # css/js/img
app/
  bootstrap.php    # Env, autoloader, helpers, sesión, zona horaria
  Core/            # Env, Database, Router, Controller, Auth, View
  Controllers/     # Auth, Panel, Calendario, Consulta, Observaciones, Importacion, Marcacion, Mes, Exportar
  Models/          # Marcacion, MarcacionResumen, MarcacionImportacion, Usuario
  Services/        # ImportadorMarcaciones (NDJSON), ExcelExporter, ExportadorInasistencias, ExportadorHorasMes
  Support/         # helpers globales: h(), base_url(), RUT, fecha, hora
  Views/           # layouts/ + vistas por módulo
config/
  routes.php
database/
  schema.sql       # esquema público
  schema_demo.sql  # datos demo (julio 2026; admin 11111111-1/admin123, operador 22222222-2/operador123)
storage/logs/
.env / .env.example
```

Naming confirmado: DB `marcaciones`, `BASE_URL=/Sistema-Marcaciones` (un `.env` con `sistema_bodega` fue copy-paste y NO se usa).

## Rutas principales (`config/routes.php`)

| Ruta | Método | Acceso | Descripción |
|---|---|---|---|
| `/login` | GET/POST | pública | Inicio de sesión |
| `/logout` | GET | sesión | Cerrar sesión |
| `/` | GET | sesión | Panel principal |
| `/calendario` | GET | sesión | Calendario día/semana/mes |
| `/consulta` | GET | sesión | Consulta por RUT |
| `/observaciones` | GET | sesión | Bandeja de incidencias |
| `/importar` | GET | sesión | Vista de importación |
| `/importar/importar` | POST | sesión | Endpoint NDJSON (streaming) |
| `/exportar/inasistencias` | GET | sesión | XLSX inasistencias (semana/mes) |
| `/exportar/horas-mes` | GET | sesión | XLSX horas del mes |
| `/marcacion/editar` | GET/POST | sesión | Editar/crear marcación |
| `/eliminar-mes` | GET/POST | admin | Eliminar un mes completo |

Middleware en `Router` (3er parámetro de `get`/`post`): `null` pública, `'login'` cualquier sesión, `'admin'` o `['admin','operador']` roles.

## Convenciones de código

- PHP 8.x (XAMPP 8.2), `declare(strict_types=1)`; usar `??`, arrow functions, types.
- Todo el código lee configuración de `.env` vía `Core/Env` — **nada hardcodeado** (host, credenciales, nombre de BD, `BASE_URL`, zona horaria).
- Helpers centralizados (RUT, hora, fecha, `h()`) en `app/Support/helpers.php` — nunca duplicarlos por archivo.
- URL base siempre vía `base_url()` (assets, links, redirects).
- Todo el código, UI, comentarios y commits en español.
- Verificación mínima: `/opt/lampp/bin/php -l <archivo>`; MySQL CLI `/opt/lampp/bin/mysql -u root marcaciones`.

## Gotchas del front controller (Apache/XAMPP)

- `.htaccess` raíz reescribe: `^assets/(.*)$ → public/assets/$1` (assets directos), el resto → `public/index.php`. No usar `%{DOCUMENT_ROOT}` para ubicar `public/` (apunta a htdocs, no al proyecto).
- En reglas por-directorio, el backref del patrón de la regla en un `RewriteCond` es `$1` (NO `%1`). Evitar `public/$1` como destino (quirk 404 en este Apache); usar `public/assets/$1`.
- `Router` resta `BASE_URL` del `REQUEST_URI` para obtener la ruta; toda URL/asset se genera con `base_url()`.
- Patrón para endpoints binarios (descargas): el controlador escribe `header()` + echo y termina con `exit` (`never`), limpiando antes el buffer de salida.

## Lógica de negocio (no obvia)

- Estados de `marcaciones_resumen.estado`: `OK` = 2 marcas con entrada < salida · `OBSERVADO` = 3+ marcas · `INCOMPLETO` = 1 marca · `ERROR` = salida anterior a entrada o solo salida.
- `editado_manual = 1` protege ediciones manuales ante reimportaciones: `recalcular_parcial()` usa `INSERT ... ON DUPLICATE KEY UPDATE` que NO pisa filas marcadas como manuales.
- RUT chileno: `normalizar_rut()` quita puntos/guiones/espacios; la importación guarda `rut_base` = número sin el dígito verificador.
- Exportadores XLSX sin librerías (`Services/ExcelExporter`): cascada ZipArchive → PurePhpZip → CsvWriter. Estilos vía const `STYLE_*` en la clase fábrica.
