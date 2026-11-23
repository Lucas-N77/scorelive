<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\ApiException;
use PDOException;
use Throwable;

/**
 * Noyau de l'application : configuration, en-têtes de sécurité,
 * routage et gestion centralisée des erreurs.
 */
final class App
{
    private static array $config = [];
    private static bool $configLoaded = false;
    private static ?string $basePath = null;
    private static ?bool $pretty = null;
    private static string $nonce = '';

    /** Chargement de la configuration, du fuseau horaire et des erreurs (web et CLI). */
    public static function boot(): void
    {
        self::loadConfig();
        date_default_timezone_set((string) config('app.timezone', 'Europe/Paris'));
        self::configureErrors();
        if (PHP_SAPI === 'cli') {
            ini_set('display_errors', '1');
        }
    }

    public static function run(): void
    {
        self::boot();
        self::$nonce = base64_encode(random_bytes(16));
        self::sendSecurityHeaders();

        try {
            Session::start(str_starts_with(Request::path(), '/api/'));

            if (!self::$configLoaded) {
                View::render('errors/setup', [
                    'title'   => 'Configuration requise',
                    'reason'  => 'config',
                    'minimal' => true,
                ], 'layout', 503);
                return;
            }

            $router = new Router();
            require APP_PATH . '/routes.php';
            $router->dispatch(Request::method(), Request::path());
        } catch (Throwable $e) {
            self::handleException($e);
        }
    }

    // ---------------------------------------------------------------------
    // Configuration
    // ---------------------------------------------------------------------

    private static function loadConfig(): void
    {
        $file = ROOT_PATH . '/config.php';
        $example = ROOT_PATH . '/config.example.php';

        if (is_file($file)) {
            $config = require $file;
            self::$configLoaded = is_array($config);
        } else {
            // Sans config.php : on charge le modèle pour pouvoir afficher la page d'aide.
            $config = is_file($example) ? require $example : [];
        }
        self::$config = is_array($config) ? $config : [];
    }

    public static function config(): array
    {
        return self::$config;
    }

    public static function configLoaded(): bool
    {
        return self::$configLoaded;
    }

    /** Chemin public de l'application, sans « / » final (ex. « /scorelive »). */
    public static function basePath(): string
    {
        if (self::$basePath === null) {
            $configured = trim((string) (self::$config['app']['base_path'] ?? ''), '/');
            if ($configured !== '') {
                self::$basePath = '/' . $configured;
            } else {
                $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
                $dir = rtrim(dirname($script), '/');
                self::$basePath = ($dir === '.' || $dir === '') ? '' : $dir;
            }
        }
        return self::$basePath;
    }

    /** URLs propres (sans index.php) : uniquement si mod_rewrite est réellement actif. */
    public static function prettyUrls(): bool
    {
        if (self::$pretty === null) {
            $setting = self::$config['app']['pretty_urls'] ?? 'auto';
            if ($setting === 'auto') {
                self::$pretty = !empty($_SERVER['SCORELIVE_REWRITE']) || !empty($_SERVER['REDIRECT_SCORELIVE_REWRITE']);
            } else {
                self::$pretty = (bool) $setting;
            }
        }
        return self::$pretty;
    }

    /** Nonce CSP du script en ligne (application du thème avant affichage). */
    public static function nonce(): string
    {
        return self::$nonce;
    }

    // ---------------------------------------------------------------------
    // Réponses
    // ---------------------------------------------------------------------

    public static function json(array $data, int $status = 200): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    // ---------------------------------------------------------------------
    // Erreurs
    // ---------------------------------------------------------------------

    private static function configureErrors(): void
    {
        $debug = (bool) config('app.debug', false);
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');

        $logDir = ROOT_PATH . '/storage/logs';
        if (is_dir($logDir) && is_writable($logDir)) {
            ini_set('error_log', $logDir . '/php-error.log');
        }

        if ($debug) {
            // En développement, le moindre avertissement devient une exception visible.
            set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
                if (!(error_reporting() & $severity)) {
                    return false;
                }
                throw new \ErrorException($message, 0, $severity, $file, $line);
            });
        }
    }

    private static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        $nonce = self::$nonce;
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; "
            . "style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; connect-src 'self'; "
            . "font-src 'self'; manifest-src 'self'; worker-src 'self'; object-src 'none'; "
            . "base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Cache-Control: no-cache, private');
    }

    public static function handleException(Throwable $e): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $wantsJson = Request::wantsJson();

        try {
            if ($e instanceof HttpException) {
                if ($wantsJson) {
                    self::json(['ok' => false, 'error' => 'http_' . $e->status, 'message' => $e->getMessage()], $e->status);
                }
                $view = $e->status === 404 ? 'errors/404' : 'errors/http';
                View::render($view, [
                    'title'   => $e->status === 404 ? 'Page introuvable' : 'Erreur',
                    'message' => $e->getMessage(),
                    'status'  => $e->status,
                ], 'layout', $e->status);
                return;
            }

            if ($e instanceof ApiException) {
                if ($e->retryAfter > 0 && !headers_sent()) {
                    header('Retry-After: ' . $e->retryAfter);
                }
                if ($wantsJson) {
                    self::json([
                        'ok'         => false,
                        'error'      => $e->kind,
                        'message'    => $e->getMessage(),
                        'retryAfter' => $e->retryAfter,
                    ], $e->pageStatus());
                }
                if ($e->kind === ApiException::MISSING_KEY) {
                    View::render('errors/setup', ['title' => 'Clé d’API requise', 'reason' => 'key'], 'layout', 200);
                    return;
                }
                if ($e->kind === ApiException::NOT_FOUND) {
                    View::render('errors/404', [
                        'title'   => 'Introuvable',
                        'message' => 'Cette ressource n’existe pas (ou plus) dans la base football-data.org.',
                        'status'  => 404,
                    ], 'layout', 404);
                    return;
                }
                View::render('errors/api', ['title' => $e->title(), 'error' => $e], 'layout', $e->pageStatus());
                return;
            }

            error_log(sprintf('[ScoreLive] %s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
            $isDatabase = $e instanceof PDOException;

            if ($wantsJson) {
                self::json([
                    'ok'      => false,
                    'error'   => $isDatabase ? 'database' : 'server',
                    'message' => $isDatabase ? 'Base de données indisponible.' : 'Erreur interne du serveur.',
                ], 500);
            }
            View::render($isDatabase ? 'errors/database' : 'errors/500', [
                'title'     => $isDatabase ? 'Base de données indisponible' : 'Erreur interne',
                'exception' => config('app.debug') ? $e : null,
                'minimal'   => true,
            ], 'layout', 500);
        } catch (Throwable $inner) {
            error_log('[ScoreLive] Erreur pendant l’affichage de l’erreur : ' . $inner->getMessage());
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
            }
            echo '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Erreur — ScoreLive</title>'
                . '<body style="font-family:system-ui;background:#0b1018;color:#e8eef6;padding:2rem">'
                . '<h1>Une erreur interne est survenue</h1><p>Consultez storage/logs/php-error.log pour le détail.</p></body></html>';
        }
    }
}
