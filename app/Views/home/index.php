<section class="card">
    <h1>Bienvenido al Sistema de Marcaciones</h1>
    <p class="muted">Infraestructura (Fase 2) funcionando. Este panel será reemplazado por el panel real en la Fase 3.</p>

    <ul class="card-list">
        <li>
            Base de datos conectada:
            <strong><?= number_format($totalResumenes) ?></strong> resúmenes en
            <code>marcaciones_resumen</code>
        </li>
        <li>
            BASE_URL: <code><?= h(base_url('')) ?></code>
        </li>
        <li>
            APP_ENV: <code><?= h((string) \App\Core\Env::get('APP_ENV', '')) ?></code>
        </li>
        <li>
            Zona horaria: <code><?= h(date_default_timezone_get()) ?></code>
        </li>
    </ul>
</section>
