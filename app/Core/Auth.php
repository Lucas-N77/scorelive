<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Utilisateur connecté (identifiant stocké en session).
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = $_SESSION['user_id'] ?? null;
            if (is_int($id) && $id > 0) {
                self::$user = User::find($id);
                if (self::$user === null && session_status() === PHP_SESSION_ACTIVE) {
                    unset($_SESSION['user_id']);
                }
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function login(array $user): void
    {
        Session::regenerate();               // nouvel identifiant de session : anti-fixation
        $_SESSION['user_id'] = (int) $user['id'];
        Csrf::rotate();
        self::$user = $user;
        self::$loaded = true;
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$user = null;
        self::$loaded = true;
    }
}
