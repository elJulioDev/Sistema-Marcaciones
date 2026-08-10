# Sistema de Marcaciones — Control de Asistencia

## Descripción General

Plataforma web para la gestión integral de la asistencia laboral. Permite importar de forma masiva los archivos planos generados por los relojes de control horario, calcular automáticamente entradas, salidas, totales de horas e incidencias, y entregar reportes operativos para Recursos Humanos.

Está pensado para entornos corporativos sobre **PHP 8.x / MariaDB / XAMPP**, sin dependencias externas: no usa Composer ni librerías pesadas. El generador de Excel, el escritor ZIP y los flujos en streaming están escritos en PHP puro.

---

## Características

* **Importación masiva** con streaming NDJSON y barra de progreso en tiempo real.
* **Deduplicación por hash** (MD5 de `dpto|nombre|numero|fecha_hora`) para reimportaciones seguras.
* **Recálculo parcial** de resúmenes que respeta las ediciones manuales (`editado_manual = 1`).
* **Calendario visual** en modos día, semana y mes, con navegación AJAX.
* **Observaciones**: bandeja de incidencias (`OBSERVADO`, `INCOMPLETO`, `ERROR`) con paginación y edición directa.
* **Consulta por RUT** con validación módulo 11 e impresión / guardado PDF.
* **Exportadores XLSX** propios (sin librerías): inasistencias semanal/mensual y horas trabajadas del mes.
* **Edición manual** de marcaciones, incluyendo crear registro para un ausente.
* **Roles**: `admin` y `operador` con control real en las rutas.

---

## Arquitectura

Aplicación PHP por capas con un único front controller:

* `public/` — document root (bootstrap + Router + assets).
* `app/Core/` — `Env` (cargador `.env`), `Database` (PDO singleton), `Router`, `Controller`, `Auth`, `View`.
* `app/Controllers/` — un controlador por módulo.
* `app/Models/` — acceso a datos (`Marcacion`, `MarcacionResumen`, `MarcacionImportacion`, `Usuario`).
* `app/Services/` — lógica de negocio reutilizable (`ImportadorMarcaciones`, `ExcelExporter`, `ExportadorInasistencias`, `ExportadorHorasMes`).
* `app/Support/` — helpers globales (sanitización, RUT chileno, fechas, horas).
* `config/routes.php` — registro de rutas con middleware de autenticación/roles.

Sin Composer: el autoloader propio (`spl_autoload_register`) mapea `App\` → `app/`.

Toda la configuración vive en `.env` (conexión BD, `BASE_URL`, zona horaria, entorno): nada hardcodeado.

---

## Requisitos

* PHP 8.x (probado con XAMPP 8.2).
* MariaDB / MySQL 5.7+ (InnoDB, utf8mb4).
* Apache con `mod_rewrite` (o cualquier servidor con reescritura al front controller).

---

## Instalación

```bash
# 1. Clonar en htdocs de XAMPP
git clone <repositorio> Sistema-Marcaciones
cd Sistema-Marcaciones

# 2. Configurar entorno
cp .env.example .env
#    Editar .env: credenciales BD, BASE_URL (sin barra final), APP_TIMEZONE

# 3. Crear la base de datos y las tablas
mysql -u root -p < database/schema.sql
#    (o importar database/schema_demo.sql para datos de ejemplo)

# 4. Crear el primer usuario administrador
#    Ejecutar una vez el hash del password con password_hash():
#    INSERT INTO usuarios_sistema (rut, password, nombre, rol)
#    VALUES ('11111111-1', '<hash bcrypt>', 'Administrador', 'admin');

# 5. Acceder
#    http://localhost/Sistema-Marcaciones/  (según BASE_URL)
```

Usuarios de la base demo (`schema_demo.sql`):

| RUT | Contraseña | Rol |
|---|---|---|
| `11111111-1` | `admin123` | admin |
| `22222222-2` | `operador123` | operador |

---

## Rutas principales

| Ruta | Método | Acceso | Descripción |
|---|---|---|---|
| `/login` | GET/POST | pública | Inicio de sesión |
| `/logout` | GET | sesión | Cerrar sesión |
| `/` | GET | sesión | Panel principal |
| `/calendario` | GET | sesión | Calendario día/semana/mes |
| `/consulta` | GET | sesión | Consulta por RUT |
| `/observaciones` | GET | sesión | Bandeja de incidencias |
| `/importar` | GET | sesión | Vista de importación |
| `/importar/importar` | POST | sesión | Endpoint NDJSON |
| `/exportar/inasistencias` | GET | sesión | XLSX inasistencias |
| `/exportar/horas-mes` | GET | sesión | XLSX horas del mes |
| `/marcacion/editar` | GET/POST | sesión | Editar/crear marcación |
| `/eliminar-mes` | GET/POST | admin | Eliminar un mes completo |

---

## Modelo de datos

```sql
marcaciones (                -- Registros brutos del reloj de control
    id, id_importacion, dpto, nombre, numero, rut_base,
    fecha_hora, fecha, hora, hash_registro UNIQUE
)

marcaciones_resumen (        -- Resumen calculado por (rut_base, fecha)
    id, rut_base, numero, nombre, dpto, fecha,
    entrada, salida, total_horas, cantidad_marcaciones,
    estado ENUM('OK','OBSERVADO','INCOMPLETO','ERROR'),
    observacion, editado_manual, updated_at,
    UNIQUE(rut_base, fecha)
)

marcaciones_importaciones (  -- Auditoría de cada archivo cargado
    id, nombre_archivo, periodo, observacion,
    total_lineas, total_insertadas, total_duplicadas,
    total_invalidas, creado_por, created_at
)

usuarios_sistema (           -- Usuarios del sistema
    id, rut, password, nombre, rol, activo
)
```

### Lógica de estados

| Estado | Condición |
|---|---|
| `OK` | Exactamente 2 marcaciones, entrada < salida |
| `OBSERVADO` | 3 o más marcaciones (revisar detalle) |
| `INCOMPLETO` | Solo 1 marcación en el día |
| `ERROR` | Salida anterior a entrada, o solo existe salida |

---

## Exportadores XLSX

Generador propio sin librerías (`app/Services/ExcelExporter.php`) con tres motores en cascada:

1. **ZipArchive** (extensión nativa) si está disponible.
2. **PurePhpZip** (escritor ZIP en PHP puro con `pack()` + `crc32()`) como fallback universal.
3. **CsvWriter** como último recurso teórico.

Reportes disponibles:

* **`/exportar/inasistencias`** — matriz de empleados y días (semana o mes). Solo incluye sábado/domingo si hubo marcaciones reales. Aparecen los empleados que faltaron al menos un día hábil o trabajaron un fin de semana.
* **`/exportar/horas-mes`** — horas trabajadas por funcionario en el mes, con días trabajados, horas esperadas y diferencia (+/−). Incluye todos los empleados del período.

---

## Helpers globales (`app/Support/helpers.php`)

```php
h($valor)                   // htmlspecialchars UTF-8 (sanitización)
base_url($path = '')        // URL absoluta según BASE_URL

// RUT chileno
normalizar_rut($rut)        // Quita puntos, guiones y espacios
rut_cuerpo($rut)            // Solo el número sin DV
rut_dv($rut)                // Solo el dígito verificador
validar_rut($rut)           // Valida módulo 11
formatear_rut($rut)         // "12.345.678-9"

// Fechas y horas
nombre_dia_es($fechaYmd)    // "Lunes", "Martes", ...
hms_a_minutos($hms)         // "08:30:00" → 510
minutos_a_hhmm_display($m)  // 510 → "8h 30m"
minutos_a_time($m)          // 510 → "08:30:00"
normalizar_hora($hora)      // Valida formato HH:MM
```

---

## Estructura del proyecto

```text
Sistema-Marcaciones/
├── public/                        # Document root
│   ├── index.php                  # Bootstrap + Router
│   ├── .htaccess                  # Reescritura al front controller
│   └── assets/                    # css / js / img
├── app/
│   ├── bootstrap.php              # Env, autoloader, helpers, sesión
│   ├── Core/                      # Env, Database, Router, Controller, Auth, View
│   ├── Controllers/               # Auth, Panel, Calendario, Consulta, Observaciones,
│   │                              # Importacion, Marcacion, Mes, Exportar
│   ├── Models/                    # Marcacion, MarcacionResumen, MarcacionImportacion, Usuario
│   ├── Services/                  # ImportadorMarcaciones, ExcelExporter,
│   │                              # ExportadorInasistencias, ExportadorHorasMes
│   ├── Support/                   # helpers.php
│   └── Views/                     # layouts/ + vistas por módulo
├── config/
│   └── routes.php
├── database/
│   ├── schema.sql                 # Esquema público
│   └── schema_demo.sql            # Datos de demostración
├── storage/logs/                  # Logs de errores
├── .env / .env.example
└── AGENTS.md                      # Guía de desarrollo para agentes
```

---

## Estado de la migración

La reestructuración desde las páginas PHP planas a la arquitectura por capas descrita arriba está **completa**. Se eliminaron los archivos planos de la raíz, los helpers legados (`inc/db.php`, `auth.php`, `hash.php`, `navbar.php`) y los assets de `static/` (incluidos los logos institucionales). Todo el sistema se sirve desde `public/` mediante el front controller.

---

## Licencia

Proyecto de uso interno. Distribuido bajo licencia MIT para fines educativos y de referencia técnica.
