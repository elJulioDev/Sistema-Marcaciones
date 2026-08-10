/* ── Shell de la aplicación: tema (claro/oscuro/auto), sidebar y drawer móvil ──
   Carga con `defer`; los atributos iniciales se aplican inline en el <head>
   (data-theme / data-bs-theme / data-sidebar-collapsed) para evitar FOUC. */
(function () {
    'use strict';

    var root = document.documentElement;
    var STORAGE_THEME = 'sm-theme';
    var STORAGE_COLLAPSED = 'sm-sidebar-collapsed';
    var mqOscuro = window.matchMedia('(prefers-color-scheme: dark)');
    var mqEscritorio = window.matchMedia('(min-width: 992px)');

    /* ── Tema: preferencia (light/dark/auto) vs. tema aplicado ── */
    function temaAplicado(pref) {
        if (pref === 'auto') return mqOscuro.matches ? 'dark' : 'light';
        return pref === 'dark' ? 'dark' : 'light';
    }

    var ICONOS = { light: 'bi-sun', dark: 'bi-moon', auto: 'bi-circle-half' };

    function marcarIcono(tema) {
        document.querySelectorAll('#themeIcon').forEach(function (ic) {
            ic.className = 'bi ' + (ICONOS[tema] || ICONOS.auto);
        });
    }

    function marcarOpcion(pref) {
        document.querySelectorAll('[data-theme-option]').forEach(function (op) {
            op.classList.toggle('active', op.getAttribute('data-theme-option') === pref);
        });
    }

    function aplicarPreferencia(pref) {
        var tema = temaAplicado(pref);
        root.setAttribute('data-theme', tema);
        root.setAttribute('data-bs-theme', tema);
        try { localStorage.setItem(STORAGE_THEME, pref); } catch (e) { /* sin almacenamiento */ }
        marcarIcono(tema);
        marcarOpcion(pref);
        document.dispatchEvent(new CustomEvent('sm:theme', { detail: { theme: tema, preference: pref } }));
    }

    document.addEventListener('click', function (e) {
        var op = e.target.closest('[data-theme-option]');
        if (!op) return;
        e.preventDefault();
        aplicarPreferencia(op.getAttribute('data-theme-option'));
        var dd = op.closest('.dropdown');
        if (dd) { var m = bootstrap.Dropdown.getInstance(dd.querySelector('[data-bs-toggle="dropdown"]')); if (m) m.hide(); }
    });

    /* En "auto" se sigue el cambio de preferencia del sistema en caliente. */
    if (mqOscuro.addEventListener) {
        mqOscuro.addEventListener('change', function () {
            var pref = 'auto';
            try { pref = localStorage.getItem(STORAGE_THEME) || 'auto'; } catch (e) { /* noop */ }
            if (pref === 'auto') aplicarPreferencia('auto');
        });
    } else if (mqOscuro.addListener) {
        mqOscuro.addListener(function () {
            var pref = 'auto';
            try { pref = localStorage.getItem(STORAGE_THEME) || 'auto'; } catch (e) { /* noop */ }
            if (pref === 'auto') aplicarPreferencia('auto');
        });
    }

    /* ── Sidebar: drawer móvil + colapso de escritorio ───────── */
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');

    function abrirDrawer() {
        if (!sidebar) return;
        sidebar.classList.add('show');
        if (overlay) overlay.classList.add('show');
    }

    function cerrarDrawer() {
        if (!sidebar) return;
        sidebar.classList.remove('show');
        if (overlay) overlay.classList.remove('show');
    }

    function toggleDrawer() {
        sidebar && sidebar.classList.contains('show') ? cerrarDrawer() : abrirDrawer();
    }

    document.addEventListener('click', function (e) {
        if (overlay && e.target === overlay) { cerrarDrawer(); return; }
        if (e.target.closest('#btnSidebarClose')) { cerrarDrawer(); return; }
        if (sidebar && e.target.closest('.sidebar-nav a')) { cerrarDrawer(); }
    });

    /* ── Colapso del rail (solo escritorio) ──────────────────── */
    function aplicarColapso(colapsado) {
        if (colapsado) {
            root.setAttribute('data-sidebar-collapsed', '1');
        } else {
            root.removeAttribute('data-sidebar-collapsed');
        }
        try { localStorage.setItem(STORAGE_COLLAPSED, colapsado ? '1' : '0'); } catch (e) { /* noop */ }
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#btnSidebarCollapse');
        if (!btn) return;
        e.preventDefault();
        if (!mqEscritorio.matches) { toggleDrawer(); return; }
        aplicarColapso(root.getAttribute('data-sidebar-collapsed') !== '1');
    });

    /* Si la ventana pasa a escritorio con el drawer abierto, se cierra. */
    if (mqEscritorio.addEventListener) {
        mqEscritorio.addEventListener('change', function (e) { if (e.matches) cerrarDrawer(); });
    }

    /* ── Estado inicial ──────────────────────────────────────── */
    var pref = 'auto';
    try { pref = localStorage.getItem(STORAGE_THEME) || 'auto'; } catch (e) { /* noop */ }
    aplicarPreferencia(pref);
}());
