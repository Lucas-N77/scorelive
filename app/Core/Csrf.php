<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Jeton anti-CSRF : un jeton aléatoire par session, vérifié sur chaque POST
 * (champ caché « _csrf » ou en-tête « X-CSRF-Token » pour les appels fetch).
 */
final class Csrf
{
    public static function token(): string
    {
        $token = $_SESSION['_csrf'] ?? null;
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $_SESSION['_csrf'] = $token;
        }
        return $token;
    }

    public static function validate(mixed $token): bool
    {
        $expected = $_SESSION['_csrf'] ?? null;
        return is_string($token) && is_string($expected) && $expected !== '' && hash_equals($expected, $token);
    }

    /** Bloque la requête si le jeton est absent ou invalide. */
    public static function guard(): void
    {
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (self::validate($token)) {
            return;
        }

        $message = 'Votre session a expiré ou le formulaire n’est plus valide. Veuillez réessayer.';
        if (Request::wantsJson()) {
            throw new HttpException(403, $message);
        }
        Session::flash('error', $message);
        redirect(Request::back(url()), 303);
    }

    /** Nouveau jeton (après connexion) pour éviter toute fixation. */
    public static function rotate(): void
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
}
