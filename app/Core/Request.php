<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Accès en lecture à la requête HTTP courante.
 */
final class Request
{
    private static ?string $path = null;

    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    /**
     * Chemin interne de la page, ex. « /match/123 ».
     * Fonctionne avec index.php/chemin (PATH_INFO) comme avec une URL réécrite.
     */
    public static function path(): string
    {
        if (self::$path !== null) {
            return self::$path;
        }

        $pathInfo = $_SERVER['PATH_INFO'] ?? '';
        if (is_string($pathInfo) && $pathInfo !== '') {
            $path = $pathInfo;
        } else {
            $uri = rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
            $base = App::basePath();
            if ($base !== '' && str_starts_with($uri, $base)) {
                $uri = substr($uri, strlen($base));
            }
            if (str_starts_with($uri, '/index.php')) {
                $uri = substr($uri, strlen('/index.php'));
            }
            $path = $uri;
        }

        return self::$path = '/' . trim($path, '/');
    }

    /** Paramètre de l'URL (?cle=valeur), nettoyé. */
    public static function query(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    /** Champ de formulaire POST (non nettoyé : utile pour les mots de passe). */
    public static function input(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }

    public static function wantsJson(): bool
    {
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $requestedWith = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        return str_contains($accept, 'application/json')
            || strcasecmp($requestedWith, 'XMLHttpRequest') === 0
            || str_starts_with(self::path(), '/api/');
    }

    public static function ip(): string
    {
        // REMOTE_ADDR uniquement : les en-têtes X-Forwarded-For sont falsifiables.
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        return substr($ip, 0, 45);
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }

    /** Page précédente si elle appartient à l'application, sinon $fallback. */
    public static function back(string $fallback): string
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer === '') {
            return $fallback;
        }
        $parts = parse_url($referer);
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $refHost = ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if ($host === '' || strcasecmp($refHost, $host) !== 0) {
            return $fallback;
        }
        $target = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        return safe_redirect_target($target, $fallback);
    }
}
