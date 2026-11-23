<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Rendu des vues PHP (app/Views) dans un gabarit commun.
 */
final class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'layout', int $status = 200): void
    {
        $content = self::fetch($template, $data);
        $html = $layout !== null ? self::fetch($layout, ['content' => $content] + $data) : $content;

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo $html;
    }

    public static function fetch(string $template, array $data = []): string
    {
        $file = APP_PATH . '/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('Vue introuvable : ' . $template);
        }

        $level = ob_get_level();
        ob_start();
        try {
            (static function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })($file, $data);
            return (string) ob_get_clean();
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
    }

    /** Affiche un fragment de app/Views/partials. */
    public static function partial(string $name, array $data = []): void
    {
        echo self::fetch('partials/' . $name, $data);
    }
}
