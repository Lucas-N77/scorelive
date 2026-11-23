<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Session PHP sécurisée (cookie HttpOnly + SameSite=Lax) et messages flash.
 */
final class Session
{
    public static function start(bool $readOnly = false): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (PHP_SAPI === 'cli') {
            $_SESSION ??= [];
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        session_name('scorelive_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => App::basePath() . '/',
            'secure'   => Request::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // read_and_close : les endpoints JSON ne bloquent pas la session pendant l'appel à l'API.
        session_start($readOnly ? ['read_and_close' => true] : []);

        if (!$readOnly) {
            self::ageOldInput();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /** Message affiché une seule fois, à la page suivante. $type : success | error | info */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function takeFlashes(): array
    {
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($flashes) ? $flashes : [];
    }

    /** Conserve la saisie d'un formulaire pour la requête suivante (sans les mots de passe). */
    public static function flashInput(array $input): void
    {
        $_SESSION['_old_input'] = $input;
        $_SESSION['_old_input_fresh'] = true;
    }

    private static function ageOldInput(): void
    {
        if (!empty($_SESSION['_old_input_fresh'])) {
            unset($_SESSION['_old_input_fresh']);
        } else {
            unset($_SESSION['_old_input']);
        }
    }

    /** Détruit la session puis en ouvre une neuve (pour pouvoir afficher un message flash). */
    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?: 'Lax',
        ]);
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }
}
