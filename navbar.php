<?php
// Asegurar que la sesión esté iniciada para obtener el nombre del usuario
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$nombreUsuario = isset($_SESSION['usuario_nombre']) ? htmlspecialchars($_SESSION['usuario_nombre'], ENT_QUOTES, 'UTF-8') : 'Usuario';

// Obtener el nombre del archivo actual para marcar el enlace activo
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    /* Estilos exclusivos y aislados para el navbar global */
    .global-navbar {
        background: #ffffff;
        padding: 12px 2%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 4px rgba(0,0,0,.05);
        border-bottom: 1px solid #e5e7eb;
        font-family: 'Figtree', Arial, sans-serif;
        flex-wrap: wrap;
        gap: 10px;
        position: relative;
        z-index: 1000;
    }
    .global-navbar .navbar-brand {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
        font-size: 18px;
        color: #1f2937;
        text-decoration: none;
    }
    .global-navbar .navbar-brand svg {
        color: #2563eb;
        width: 20px;
        height: 20px;
    }
    .global-navbar .navbar-links {
        display: flex;
        gap: 5px;
        align-items: center;
        flex-wrap: wrap;
    }
    .global-navbar .navbar-links a {
        text-decoration: none;
        color: #4b5563;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.2s;
        padding: 8px 12px;
        border-radius: 6px;
    }
    .global-navbar .navbar-links a:hover {
        color: #2563eb;
        background: #f3f4f6;
    }
    .global-navbar .navbar-links a.active {
        color: #2563eb;
        background: #eff6ff;
    }
    .global-navbar .user-controls {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .global-navbar .user-name {
        font-weight: 600;
        font-size: 14px;
        color: #6b7280;
    }
    .global-navbar .btn-logout {
        background: #fee2e2;
        color: #991b1b;
        padding: 6px 12px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 700;
        font-size: 13px;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .global-navbar .btn-logout:hover {
        background: #fca5a5;
        color: #7f1d1d;
    }
    
    /* Responsividad para móviles */
    @media(max-width: 900px) {
        .global-navbar {
            flex-direction: column;
        }
        .global-navbar .navbar-links {
            justify-content: center;
            width: 100%;
            order: 3;
            margin-top: 10px;
        }
        .global-navbar .user-name {
            display: none;
        }
    }
</style>

<nav class="global-navbar">
    <a href="panel.php" class="navbar-brand">
        RRHH Coltauco
    </a>
    
    <div class="navbar-links">
        <a href="panel.php" class="<?php echo ($current_page == 'panel.php') ? 'active' : ''; ?>">Inicio</a>
        <a href="calendario_marcaciones.php" class="<?php echo ($current_page == 'calendario_marcaciones.php') ? 'active' : ''; ?>">Calendario</a>
        <a href="importar_marcaciones.php" class="<?php echo ($current_page == 'importar_marcaciones.php') ? 'active' : ''; ?>">Importar</a>
        <a href="observaciones_marcaciones.php" class="<?php echo ($current_page == 'observaciones_marcaciones.php') ? 'active' : ''; ?>">Observaciones</a>
        <a href="consulta_marcaciones.php" class="<?php echo ($current_page == 'consulta_marcaciones.php') ? 'active' : ''; ?>">Consulta</a>
    </div>

    <div class="user-controls">
        <span class="user-name">Hola, <?php echo $nombreUsuario; ?></span>
        <a href="logout.php" class="btn-logout">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Salir
        </a>
    </div>
</nav>