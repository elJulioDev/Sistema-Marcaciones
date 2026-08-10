<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Render de vistas desde app/Views con soporte de layouts.
 */
final class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'app'): string
    {
        $content = self::partial($template, $data);

        if ($layout === null) {
            return $content;
        }

        return self::partial('layouts/' . $layout, ['content' => $content] + $data);
    }

    public static function partial(string $template, array $data = []): string
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require APP_PATH . '/Views/' . $template . '.php';

        return (string) ob_get_clean();
    }
}
