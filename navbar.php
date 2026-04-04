<?php
// Asegurar que la sesión esté iniciada para obtener el nombre del usuario
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$nombreUsuario = isset($_SESSION['usuario_nombre']) ? htmlspecialchars($_SESSION['usuario_nombre'], ENT_QUOTES, 'UTF-8') : 'Usuario';

// Obtener el nombre del archivo actual para marcar el enlace activo
$current_page = basename($_SERVER['PHP_SELF']);
?>
<script>
(function() {
    var link = document.querySelector("link[rel*='icon']") || document.createElement('link');
    link.type = 'image/svg+xml';
    link.rel = 'icon';
    link.href = "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%232563eb'><path fill-rule='evenodd' d='M6.75 2.25A.75.75 0 017.5 3v1.5h9V3A.75.75 0 0118 3v1.5h.75a3 3 0 013 3v11.25a3 3 0 01-3 3H5.25a3 3 0 01-3-3V7.5a3 3 0 013-3H6V3a.75.75 0 01.75-.75zm13.5 9a1.5 1.5 0 00-1.5-1.5H5.25a1.5 1.5 0 00-1.5 1.5v7.5a1.5 1.5 0 001.5 1.5h13.5a1.5 1.5 0 001.5-1.5v-7.5z' clip-rule='evenodd' /></svg>";
    document.getElementsByTagName('head')[0].appendChild(link);
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    /* ══════════════════════════════════════════════════════════════════
       RESETEO LIMPIO DEL BODY
       Eliminamos los trucos de "scrollbar-gutter" y "overflow-y". 
       El comportamiento nativo es el más estable: evita espacios en blanco 
       a la derecha y no genera barras fantasma en páginas sin scroll.
    ══════════════════════════════════════════════════════════════════ */
    body {
        margin: 0; /* Vital para que el width: 100% del navbar encaje perfecto */
    }

    /* ══════════════════════════════════════════════════════════════════
       AISLAMIENTO TOTAL DEL NAVBAR
       Se usan valores explícitos en todos los elementos para que NINGÚN
       estilo global de las páginas padre pueda filtrarse (herencia CSS).
       Se evita usar `!important` masivo; en su lugar se usa especificidad
       alta y se resetean las propiedades más vulnerables (font-weight,
       font-family, font-size, line-height, color, text-decoration).
    ══════════════════════════════════════════════════════════════════ */
    .global-navbar,
    .global-navbar *,
    .global-navbar *::before,
    .global-navbar *::after {
        box-sizing: border-box;
        /* Reset de herencia que causan el "negrita" en algunas páginas */
        font-family: 'Figtree', Arial, sans-serif;
        font-weight: 400;        /* base neutral; cada elemento lo sobreescribe abajo */
        line-height: 1.5;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    /* ── Contenedor principal ── */
    .global-navbar {
        background: #ffffff;
        padding: 0 20px;
        height: 56px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 4px rgba(0,0,0,.05);
        border-bottom: 1px solid #e5e7eb;
        position: sticky;
        top: 0;
        z-index: 1000;
        flex-shrink: 0;
        width: 100%;
        margin: 0;
    }

    /* ── Logo / Brand ── */
    .global-navbar .navbar-brand {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 700 !important;   /* explícito para evitar herencia */
        font-size: 17px !important;
        color: #1f2937 !important;
        text-decoration: none !important;
        white-space: nowrap;
        flex-shrink: 0;
        letter-spacing: -0.2px;
    }

    .global-navbar .navbar-brand svg {
        flex-shrink: 0;
    }

    /* ── Zona central de enlaces ── */
    .global-navbar .navbar-links {
        display: flex;
        gap: 4px;
        align-items: center;
        justify-content: center;
        flex: 1;
    }

    .global-navbar .navbar-links a {
        text-decoration: none !important;
        color: #4b5563 !important;
        font-weight: 500 !important;   /* medio, nunca negrita por defecto */
        font-size: 14px !important;
        transition: color 0.15s, background 0.15s;
        padding: 7px 12px;
        border-radius: 6px;
        white-space: nowrap;
        display: inline-block;
    }

    .global-navbar .navbar-links a:hover {
        color: #2563eb !important;
        background: #f3f4f6;
    }

    .global-navbar .navbar-links a.active {
        color: #2563eb !important;
        background: #eff6ff;
        font-weight: 600 !important;   /* solo el activo va a semibold */
    }

    /* ── Controles de usuario (derecha) ── */
    .global-navbar .user-controls {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-shrink: 0;
        white-space: nowrap;
    }

    .global-navbar .user-name {
        font-weight: 500 !important;
        font-size: 13px !important;
        color: #6b7280 !important;
    }

    .global-navbar .btn-logout {
        background: #fee2e2;
        color: #991b1b !important;
        padding: 6px 12px;
        border-radius: 6px;
        text-decoration: none !important;
        font-weight: 700 !important;
        font-size: 13px !important;
        transition: background 0.2s, color 0.2s;
        display: flex;
        align-items: center;
        gap: 6px;
        border: none;
        cursor: pointer;
    }

    .global-navbar .btn-logout:hover {
        background: #fca5a5;
        color: #7f1d1d !important;
    }

    /* ── Botón hamburguesa (solo visible en móvil) ── */
    .global-navbar .navbar-hamburger {
        display: none;        /* oculto en desktop */
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border: none;
        background: transparent;
        border-radius: 6px;
        cursor: pointer;
        padding: 0;
        color: #374151;
        transition: background 0.15s;
        flex-shrink: 0;
    }

    .global-navbar .navbar-hamburger:hover {
        background: #f3f4f6;
    }

    /* Las 3 líneas del icono hamburguesa */
    .global-navbar .hamburger-icon {
        display: flex;
        flex-direction: column;
        gap: 5px;
        width: 20px;
    }

    .global-navbar .hamburger-icon span {
        display: block;
        height: 2px;
        width: 100%;
        background: #374151;
        border-radius: 2px;
        transition: transform 0.25s ease, opacity 0.25s ease, width 0.25s ease;
        transform-origin: center;
        font-size: 0;   /* reset por si hay herencia de font-size */
    }

    /* Estado "abierto": las líneas se convierten en X */
    .global-navbar.menu-open .hamburger-icon span:nth-child(1) {
        transform: translateY(7px) rotate(45deg);
    }
    .global-navbar.menu-open .hamburger-icon span:nth-child(2) {
        opacity: 0;
        width: 0;
    }
    .global-navbar.menu-open .hamburger-icon span:nth-child(3) {
        transform: translateY(-7px) rotate(-45deg);
    }

    /* ══════════════════════════════════════════════════════════════════
       OVERLAY oscuro detrás del menú móvil
    ══════════════════════════════════════════════════════════════════ */
    #navbar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.35);
        z-index: 998;
        transition: opacity 0.2s;
    }

    #navbar-overlay.active {
        display: block;
    }

    /* ══════════════════════════════════════════════════════════════════
       RESPONSIVE — Tablets (≤ 900 px)
    ══════════════════════════════════════════════════════════════════ */
    @media (max-width: 900px) {
        .global-navbar .user-name {
            display: none;
        }

        /* Ocultar zona de enlaces central en tablet/móvil.
           Los enlaces se moverán al panel deslizable. */
        .global-navbar .navbar-links {
            display: none;
        }

        /* Mostrar botón hamburguesa */
        .global-navbar .navbar-hamburger {
            display: flex;
        }

        /* Panel lateral que se desliza desde la izquierda */
        .global-navbar .navbar-links.mobile-open {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            max-width: 80vw;
            background: #ffffff;
            padding: 72px 16px 24px 16px;
            box-shadow: 4px 0 20px rgba(0,0,0,0.12);
            z-index: 999;
            overflow-y: auto;
            animation: slideInLeft 0.25s ease;
        }

        .global-navbar .navbar-links.mobile-open a {
            width: 100%;
            padding: 10px 14px !important;
            font-size: 15px !important;
            border-radius: 8px;
        }

        /* Título "Menú" en la cabecera del panel */
        .global-navbar .navbar-links.mobile-open::before {
            content: 'Menú';
            display: block;
            font-size: 11px !important;
            font-weight: 700 !important;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #9ca3af !important;
            padding: 0 14px;
            margin-bottom: 4px;
            width: 100%;
        }

        @keyframes slideInLeft {
            from { transform: translateX(-100%); }
            to   { transform: translateX(0); }
        }
    }

    /* ══════════════════════════════════════════════════════════════════
       RESPONSIVE — Móviles pequeños (≤ 480 px)
    ══════════════════════════════════════════════════════════════════ */
    @media (max-width: 480px) {
        .global-navbar {
            padding: 0 14px;
        }

        .global-navbar .navbar-brand {
            font-size: 15px !important;
        }
    }
</style>

<!-- Overlay oscuro para cerrar el menú al tocar fuera -->
<div id="navbar-overlay"></div>

<nav class="global-navbar" id="globalNavbar">

    <!-- Botón hamburguesa (izquierda, solo móvil) -->
    <button class="navbar-hamburger"
            id="navbarHamburger"
            aria-label="Abrir menú"
            aria-expanded="false"
            aria-controls="navbarLinks">
        <span class="hamburger-icon">
            <span></span>
            <span></span>
            <span></span>
        </span>
    </button>

    <!-- Logo / Brand -->
    <a href="panel.php" class="navbar-brand">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
             fill="none" stroke="#2563eb" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8"  y1="2" x2="8"  y2="6"/>
            <line x1="3"  y1="10" x2="21" y2="10"/>
        </svg>
        RRHH Coltauco
    </a>

    <!-- Zona central de navegación -->
    <div class="navbar-links" id="navbarLinks" role="navigation" aria-label="Menú principal">
        <a href="panel.php"
           class="<?php echo ($current_page === 'panel.php') ? 'active' : ''; ?>">
            Inicio
        </a>
        <a href="calendario_marcaciones.php"
           class="<?php echo ($current_page === 'calendario_marcaciones.php') ? 'active' : ''; ?>">
            Calendario
        </a>
        <a href="importar_marcaciones.php"
           class="<?php echo ($current_page === 'importar_marcaciones.php') ? 'active' : ''; ?>">
            Importar
        </a>
        <a href="observaciones_marcaciones.php"
           class="<?php echo ($current_page === 'observaciones_marcaciones.php') ? 'active' : ''; ?>">
            Observaciones
        </a>
        <a href="consulta_marcaciones.php"
           class="<?php echo ($current_page === 'consulta_marcaciones.php') ? 'active' : ''; ?>">
            Consulta
        </a>
    </div>

    <!-- Controles de usuario (derecha) -->
    <div class="user-controls">
        <span class="user-name">Hola, <?php echo $nombreUsuario; ?></span>
        <a href="logout.php" class="btn-logout">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Salir
        </a>
    </div>
</nav>

<script>
(function () {
    'use strict';

    var navbar    = document.getElementById('globalNavbar');
    var links     = document.getElementById('navbarLinks');
    var hamburger = document.getElementById('navbarHamburger');
    var overlay   = document.getElementById('navbar-overlay');

    if (!navbar || !links || !hamburger || !overlay) return;

    function openMenu() {
        links.classList.add('mobile-open');
        navbar.classList.add('menu-open');
        overlay.classList.add('active');
        hamburger.setAttribute('aria-expanded', 'true');
        hamburger.setAttribute('aria-label', 'Cerrar menú');
        document.body.style.overflow = 'hidden'; // evita scroll de fondo
    }

    function closeMenu() {
        links.classList.remove('mobile-open');
        navbar.classList.remove('menu-open');
        overlay.classList.remove('active');
        hamburger.setAttribute('aria-expanded', 'false');
        hamburger.setAttribute('aria-label', 'Abrir menú');
        document.body.style.overflow = '';
    }

    function toggleMenu() {
        if (links.classList.contains('mobile-open')) {
            closeMenu();
        } else {
            openMenu();
        }
    }

    hamburger.addEventListener('click', toggleMenu);
    overlay.addEventListener('click', closeMenu);

    // Cerrar con tecla Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeMenu();
    });

    // Cerrar si el usuario amplía la ventana a desktop
    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) closeMenu();
    });

    // Cerrar automáticamente al hacer click en un enlace del menú móvil
    var menuLinks = links.querySelectorAll('a');
    for (var i = 0; i < menuLinks.length; i++) {
        menuLinks[i].addEventListener('click', closeMenu);
    }
}());
</script>