<?php 
require_once __DIR__ . '/auth.php'; 

// Prevenir errores si la variable de sesión no está definida por algún motivo
$nombreUsuario = isset($_SESSION['usuario_nombre']) ? htmlspecialchars($_SESSION['usuario_nombre'], ENT_QUOTES, 'UTF-8') : 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Panel Principal - Marcaciones</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f7fb;
            --card-bg: #ffffff;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --radius: 12px;
            --font: 'Figtree', system-ui, sans-serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        /* Forzamos a que el body ocupe el 100% del alto de la pantalla sin scroll */
        body { 
            font-family: var(--font); 
            background: var(--bg); 
            color: var(--text-main);
            line-height: 1.4;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden; /* Evita el scroll */
        }

        .user-controls {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-muted);
            display: none;
        }

        @media(min-width: 600px) {
            .user-name { display: block; }
        }

        .btn-logout {
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

        .btn-logout:hover {
            background: #fca5a5;
            color: #7f1d1d;
        }

        /* --- Main Container (Centrado verticalmente) --- */
        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center; /* Centra el bloque en la pantalla */
            padding: 20px 5%;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
        }

        .header-section {
            margin-bottom: 24px;
            text-align: center;
        }

        .header-section h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
            letter-spacing: -0.3px;
        }

        .header-section p {
            color: var(--text-muted);
            font-size: 14px;
        }

        /* --- Grid & Cards (Diseño Horizontal Compacto) --- */
        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr); /* 2 columnas fijas en escritorio */
            gap: 16px;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid #e5e7eb;
            border-radius: var(--radius);
            padding: 16px;
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center; /* Alineación horizontal */
            gap: 16px;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,.02);
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px -6px rgba(37,99,235,.12);
            border-color: #bfdbfe;
        }

        .icon-wrapper {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .card.importar .icon-wrapper { background: #dbeafe; color: #2563eb; }
        .card.calendario .icon-wrapper { background: #dcfce7; color: #166534; }
        .card.observaciones .icon-wrapper { background: #fef3c7; color: #d97706; }
        .card.consulta .icon-wrapper { background: #f3e8ff; color: #9333ea; }

        .icon-wrapper svg {
            width: 22px;
            height: 22px;
        }

        .card-info {
            flex: 1; /* Ocupa el espacio restante */
            min-width: 0;
        }

        .card-info h2 {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .card-info p {
            font-size: 12px;
            color: var(--text-muted);
            display: -webkit-box;
            -webkit-line-clamp: 2; /* Limita a 2 líneas */
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-arrow {
            color: #9ca3af;
            transition: color 0.2s ease, transform 0.2s ease;
            flex-shrink: 0;
        }

        .card:hover .card-arrow {
            color: var(--primary);
            transform: translateX(3px);
        }

        /* --- Móvil --- */
        @media(max-width: 768px) {
            body { height: auto; overflow: visible; } /* En móvil liberamos el scroll por si acaso */
            .main-wrapper { padding: 30px 5%; display: block; }
            .grid { grid-template-columns: 1fr; /* 1 columna en teléfonos */ }
        }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="main-wrapper">
        <div class="container">
            
            <div class="header-section">
                <h1>Sistema de Marcaciones</h1>
                <p>Selecciona un módulo para comenzar a trabajar.</p>
            </div>

            <div class="grid">
                
                <a href="calendario_marcaciones.php" class="card calendario">
                    <div class="icon-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <div class="card-info">
                        <h2>Calendario Visual</h2>
                        <p>Estado de marcaciones diarias o semanales.</p>
                    </div>
                    <div class="card-arrow">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </a>

                <a href="importar_marcaciones.php" class="card importar">
                    <div class="icon-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    </div>
                    <div class="card-info">
                        <h2>Importar Archivos</h2>
                        <p>Sube archivos TXT/CSV desde los relojes de control.</p>
                    </div>
                    <div class="card-arrow">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </a>

                <a href="observaciones_marcaciones.php" class="card observaciones">
                    <div class="icon-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </div>
                    <div class="card-info">
                        <h2>Observaciones</h2>
                        <p>Corrige incidencias y errores en la asistencia.</p>
                    </div>
                    <div class="card-arrow">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </a>

                <a href="consulta_marcaciones.php" class="card consulta">
                    <div class="icon-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><path d="M11 8v6"/><path d="M8 11h6"/></svg>
                    </div>
                    <div class="card-info">
                        <h2>Buscar Funcionario</h2>
                        <p>Busca marcaciones e historial usando el RUT.</p>
                    </div>
                    <div class="card-arrow">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </a>

            </div>
        </div>
    </div>
</body>
</html>