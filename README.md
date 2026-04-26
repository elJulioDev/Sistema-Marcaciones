# Sistema de Marcaciones — Control de Asistencia Municipal

## Descripción General
El Sistema de Marcaciones es una plataforma web destinada a la gestión integral de la asistencia laboral, construida específicamente para la **Municipalidad de Coltauco**. Permite importar de manera masiva los archivos planos generados por los relojes de control horario, calcular automáticamente entradas, salidas, totales de horas e incidencias, y entregar reportes operativos para Recursos Humanos.

Está pensado para entornos corporativos sobre **PHP 5.6+ / MariaDB / XAMPP**, sin dependencias externas de Composer ni librerías pesadas: el generador de Excel, el escritor ZIP y los flujos en streaming están escritos en PHP puro.

---

## Arquitectura y Seguridad

El proyecto sigue un esquema modular de "una página por responsabilidad". Cada módulo es autónomo (importación, calendario, observaciones, consulta, edición), pero todos comparten una capa común de autenticación, navegación y conexión a base de datos.

A nivel técnico:

* **Autenticación y Sesiones:** Cada ruta protegida valida sesión vía `auth.php`. Las contraseñas se almacenan con `password_hash()` y se verifican con `password_verify()`.
* **Acceso por Rol:** La tabla `usuarios_sistema` define el campo `rol`, lo que permite restringir vistas a perfiles administrativos o de RR.HH.
* **Conexión PDO:** Toda interacción con MySQL utiliza consultas preparadas con marcadores nombrados (`:param`) o posicionales (`?`), eliminando el riesgo de inyección SQL.
* **Sanitización de salidas:** Función helper `h()` con `htmlspecialchars()` ENT_QUOTES UTF-8 aplicada en todas las vistas.
* **Streaming NDJSON:** La importación masiva emite eventos línea a línea (`application/x-ndjson`) con `flush()` explícito, permitiendo barras de progreso reales en el cliente sin saturar el servidor.
* **Deduplicación por hash:** Cada marcación bruta genera un MD5 sobre `dpto|nombre|numero|fecha_hora`. Esto evita registros duplicados al reimportar archivos solapados, incluso si se cargan varias veces.
* **Micropausas controladas:** Durante la inserción y el recálculo de resúmenes se introducen `usleep()` cada 200ms para no saturar la base de datos compartida.

---

## Roles y Perfiles

### 1. Administrador / RR.HH. (`admin`)
Acceso global a todos los módulos:
* Importar archivos del reloj.
* Visualizar y exportar reportes.
* Editar manualmente cualquier marcación.
* Eliminar meses completos.
* Consultar por RUT a cualquier funcionario.

### 2. Operador (`operador`)
Acceso restringido a:
* Calendario visual.
* Observaciones (corrección de incidencias).
* Consulta por RUT.

---

## Manual de Usuario por Módulos

### Login (`login.php`)
Pantalla de acceso con tema institucional (azul navy + degradados). Valida RUT + contraseña contra `usuarios_sistema`, regenera la sesión y redirige al panel principal. La cookie de sesión se invalida explícitamente al cerrar sesión vía `logout.php`.

### Panel Principal (`panel.php`)
Dashboard de cuatro accesos directos: **Calendario**, **Importar**, **Observaciones** y **Consulta**. Sin scroll, totalmente responsive, optimizado para uso desde tablet o móvil del personal de RR.HH.

### Importar Marcaciones (`importar_marcaciones.php`)
Núcleo operacional del sistema. Permite cargar archivos `.txt` o `.csv` exportados desde el reloj de control. Características:

* **Drag & Drop** con feedback visual (verde al validar extensión).
* **Streaming NDJSON** que emite eventos en tiempo real al navegador: `parsing`, `parsed`, `dedup`, `inserting`, `resumen`, `done`.
* **Pre-filtrado de duplicados:** consulta por hash en lotes de 400 antes de insertar.
* **Inserción en lotes** de 500 registros con `INSERT IGNORE` y prepared statements.
* **Recálculo parcial:** solo se recalculan las combinaciones `(rut_base, fecha)` afectadas por la importación, usando `INSERT ... ON DUPLICATE KEY UPDATE` que respeta ediciones manuales (`editado_manual = 1`).
* **Barra de progreso animada** con tres pasos visuales y contador de registros por segundo.
* **Animación count-up** en los resultados finales.
* **Registro de auditoría:** cada importación queda guardada en `marcaciones_importaciones` con archivo, período, observación, líneas leídas, insertadas, duplicadas e inválidas.

### Calendario Visual (`calendario_marcaciones.php`)
Vista interactiva de la asistencia con tres modos:

* **Día:** lista de presentes y ausentes filtrable por departamento, estado y búsqueda libre. Permite editar registro o crear marcación manual para un ausente.
* **Semana:** matriz `empleado × día` con celdas coloreadas por estado, hover overlay para edición rápida y bloque de "ausentes toda la semana".
* **Mes:** matriz mensual con paginación cliente-side de 25 empleados por página. Distribuye los días en filas semanales por empleado. Solo incluye sábado/domingo si registró marcaciones (descansos no inflan la tabla).

Funciones adicionales:
* Filtro toggle "Solo inasistencias".
* Navegación AJAX entre meses sin recargar (`history.pushState`).
* Tres botones de exportación XLSX: **Inasistencias semanal**, **Inasistencias mensual** y **Horas del mes**.
* Modal de confirmación con rango de fechas exacto antes de descargar.

### Observaciones (`observaciones_marcaciones.php`)
Bandeja de incidencias que requieren corrección manual. Por defecto lista únicamente los estados `OBSERVADO`, `INCOMPLETO` y `ERROR`. Soporta:

* Filtro rápido por estado (Todos / Observados / Incompletos / Errores / OK).
* Búsqueda combinada por nombre, número, RUT, departamento u observación.
* Filtro por período mensual.
* **Paginación de 50 registros por página** con ventana deslizante de ±2 páginas.
* Detalle inline de las marcaciones brutas del día (todas las píldoras de hora).
* Botón directo a la edición.

### Editar Marcación Resumen (`editar_marcacion_resumen.php`)
Formulario que permite ajustar manualmente entrada, salida, estado y observación de un día específico. Características:

* Soporta editar registros existentes O **crear** uno nuevo cuando un funcionario faltó (no había fila en `marcaciones_resumen`).
* Calcula automáticamente el `total_horas` cuando se completan ambas horas.
* Detecta inconsistencias (salida antes que entrada → `ERROR`).
* Marca `editado_manual = 1`, lo que protege los cambios ante futuras reimportaciones.
* Navegación contextual: el botón "Volver" respeta el `return_url` recibido (calendario, observaciones, etc).

### Consulta por RUT (`consulta_marcaciones.php`)
Búsqueda histórica orientada al funcionario individual. Valida el RUT con el algoritmo módulo 11 chileno, normaliza el formato (`12345678-9`, `12.345.678-9`, `123456789` son todos válidos) y entrega:

* Información del funcionario (nombre, departamento, número).
* Tabla completa de marcaciones del período con detalle de cada hora marcada.
* Filtro opcional por mes.
* **Botón "Imprimir / Guardar PDF"** con CSS especializado (`@media print`) que genera un reporte formal con logo institucional, formato vertical y distribución tipo Excel sin colores ni botones.

### Eliminar Mes (`eliminar_mes.php`)
Herramienta administrativa para limpiar datos de prueba o importaciones erróneas. Borra de forma transaccional:

1. Registros de `marcaciones_resumen` del rango.
2. Registros brutos de `marcaciones`.
3. Entradas asociadas en `marcaciones_importaciones`.

Requiere doble confirmación (selección del mes + checkbox explícito) y muestra el historial completo de importaciones cargadas en el sistema.

---

## Exportadores XLSX

El sistema incluye un generador propio de archivos Excel **sin dependencias externas** (`inc/xlsx_generator.php`). Trabaja en dos motores en cascada:

1. **ZipArchive** (extensión nativa) si está disponible.
2. **PurePhpZip** (escritor ZIP en PHP puro usando `pack()` + `crc32()`) como fallback universal.
3. **CsvWriter** como último recurso teórico.

Los reportes ocupan estilos predefinidos (`STYLE_HEADER`, `STYLE_OK`, `STYLE_FALTA`, `STYLE_FUTURO`, `STYLE_TOTALES`, `STYLE_DATE_HEADER`), con colores institucionales y soporte para celdas combinadas (`mergeCells`), auto-fit de columnas y filas, y wrap de texto multilínea.

### Reportes disponibles

* **`exportar_inasistencias.php`** — Reporte semanal o mensual con matriz de empleados y días. Solo incluye sábado/domingo si hubo marcaciones reales. Empleados aparecen si faltaron al menos un día hábil o trabajaron un fin de semana.
* **`exportar_horas_mes.php`** — Reporte de horas trabajadas por funcionario, con horas esperadas, horas reales y diferencia (+/−). Incluye TODOS los funcionarios activos del mes, no solo los que faltaron.

---

## Esquema de Base de Datos

```sql
-- Marcaciones brutas (tal como salen del reloj)
marcaciones (
    id, id_importacion, dpto, nombre, numero, rut_base,
    fecha_hora, fecha, hora, hash_registro UNIQUE
)

-- Resumen calculado por (rut_base, fecha)
marcaciones_resumen (
    id, rut_base, numero, nombre, dpto, fecha,
    entrada, salida, total_horas, cantidad_marcaciones,
    estado ENUM('OK','OBSERVADO','INCOMPLETO','ERROR'),
    observacion, editado_manual, updated_at,
    UNIQUE(rut_base, fecha)
)

-- Auditoría de importaciones
marcaciones_importaciones (
    id, nombre_archivo, periodo, observacion,
    total_lineas, total_insertadas, total_duplicadas,
    total_invalidas, creado_por, created_at
)

-- Usuarios del sistema
usuarios_sistema (
    id, rut, password, nombre, rol, activo
)
```

### Lógica de estados

| Estado | Condición |
|---|---|
| `OK` | Exactamente 2 marcaciones, entrada < salida. |
| `OBSERVADO` | 3 o más marcaciones (revisar detalle). |
| `INCOMPLETO` | Solo 1 marcación en el día. |
| `ERROR` | Salida anterior a entrada, o solo existe salida. |

---

## Documentación Técnica para Desarrolladores

### Helpers compartidos

```php
// Sanitización para HTML
h($valor)                           // htmlspecialchars con UTF-8

// Manejo de RUT chileno
normalizar_rut($rut)                // Quita puntos, guiones, espacios
rut_cuerpo($rut)                    // Devuelve solo el cuerpo
rut_dv($rut)                        // Devuelve solo el dígito verificador
validar_rut($rut)                   // Valida módulo 11
formatear_rut($rut)                 // Devuelve "12.345.678-9"

// Fechas
nombre_dia_es($fechaYmd)            // "Lunes", "Martes", etc.

// Conversión horaria
hms_a_minutos($hms)                 // "08:30:00" → 510
minutos_a_hhmm_display($mins)       // 510 → "8h 30m"
minutos_a_time($mins)               // 510 → "08:30:00"
normalizar_hora($hora)              // Valida formato HH:MM
```

### Helpers de importación (en `importar_marcaciones.php`)

```php
limpiar_numero($v)                  // Solo dígitos y K mayúscula
obtener_rut_base($numero)           // Quita el último dígito (DV)
parsear_linea($linea)               // Separa campos por tabs
insertar_lote($pdo, $lote, $idImp)  // Bulk insert con INSERT IGNORE
prefiltrar_duplicados($pdo, $rows)  // Detecta duplicados por hash
recalcular_parcial($pdo, $pares)    // Recalcula solo (rut, fecha) afectados
```

### Variables de sesión expuestas

```php
$_SESSION['usuario_id']             // ID numérico del usuario
$_SESSION['usuario_nombre']         // Nombre completo (para navbar)
$_SESSION['usuario_rol']            // 'admin' | 'operador' | etc.
```

---

## Estructura del Proyecto

```text
sistema-marcaciones/
├── inc/
│   ├── db.php                          # Conexión PDO (no versionada)
│   └── xlsx_generator.php              # Generador Excel + PurePhpZip
├── static/
│   ├── css/
│   │   ├── calendario.css              # Tema del calendario visual
│   │   └── login.css                   # Tema del login institucional
│   └── img/
│       └── logo.png                    # Logo Municipalidad
├── auth.php                            # Guardia de sesión (no versionada)
├── login.php                           # Pantalla de acceso
├── logout.php                          # Cierre de sesión
├── panel.php                           # Dashboard principal
├── navbar.php                          # Componente de navegación global
├── importar_marcaciones.php            # Carga masiva con NDJSON streaming
├── calendario_marcaciones.php          # Vista día/semana/mes
├── observaciones_marcaciones.php       # Bandeja de incidencias paginada
├── editar_marcacion_resumen.php        # Edición manual con creación
├── consulta_marcaciones.php            # Búsqueda por RUT + impresión PDF
├── eliminar_mes.php                    # Limpieza administrativa
├── exportar_inasistencias.php          # XLSX semanal/mensual
└── exportar_horas_mes.php              # XLSX horas trabajadas
```

---

## Instalación

```bash
# 1. Clonar en htdocs de XAMPP
git clone https://github.com/elJulioDev/sistema-marcaciones.git
cd sistema-marcaciones

# 2. Crear inc/db.php (no versionado)
cat > inc/db.php <<'EOF'
<?php
function db() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=marcaciones;charset=utf8mb4',
            'usuario', 'clave',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}
EOF

# 3. Crear auth.php (no versionado)
cat > auth.php <<'EOF'
<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
EOF

# 4. Importar el esquema desde phpMyAdmin
# 5. Crear el primer usuario admin desde hash.php (también ignorado en .gitignore)
```

---

## Hoja de Ruta (Cosas por hacerse / To-Do)

- [ ] **Dashboard de RR.HH.:** gráfico de tendencias mensuales (asistencias vs inasistencias) con Chart.js, top de funcionarios con más incidencias y proyección de horas extras.
- [ ] **Notificaciones automáticas:** envío de correo al jefe directo cuando un funcionario presenta más de N inasistencias en un mes.
- [ ] **API REST:** exposición de endpoints `/api/marcaciones/{rut}` para integración con el sistema de remuneraciones.
- [ ] **Auditoría completa:** bitácora de quién editó cada marcación manual, con fecha y valor anterior. Actualmente solo se marca `editado_manual = 1` sin trazabilidad de cambios.
- [ ] **Importación programada:** cron que descargue automáticamente el archivo del reloj de control vía SFTP cada noche.
- [ ] **Permisos administrativos:** solicitudes de día libre, vacaciones, permisos administrativos integrados al cálculo del estado diario (un día con permiso aprobado no debería figurar como `ERROR`).
- [ ] **Comparador histórico:** ver lado a lado la asistencia de un funcionario entre dos meses distintos.
- [ ] **PDF directo del lado del servidor:** actualmente la consulta se imprime con CSS, pero podría generarse vía mPDF o Dompdf desde PHP.
- [ ] **Roles granulares:** permisos por departamento (un jefe ve solo a su equipo).

## Licencia
Proyecto de uso interno. Distribuido bajo licencia MIT para fines educativos y de referencia técnica.
