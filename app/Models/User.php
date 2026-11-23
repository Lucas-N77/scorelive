<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Comptes utilisateurs (mots de passe hachés avec password_hash).
 */
final class User
{
    /** Profil public (sans le hash du mot de passe). */
    public static function find(int $id): ?array
    {
        $user = Database::fetch(
            'SELECT id, username, email, created_at, last_login_at FROM users WHERE id = ?',
            [$id]
        );
        if ($user !== null) {
            $user['id'] = (int) $user['id'];
        }
        return $user;
    }

    /** Recherche par e-mail ou pseudo, hash inclus (connexion uniquement). */
    public static function findForLogin(string $identifier): ?array
    {
        return Database::fetch(
            'SELECT id, username, email, password_hash FROM users WHERE email = ? OR username = ? LIMIT 1',
            [mb_strtolower($identifier), $identifier]
        );
    }

    public static function usernameExists(string $username): bool
    {
        return Database::value('SELECT 1 FROM users WHERE username = ?', [$username]) !== null;
    }

    public static function emailExists(string $email): bool
    {
        return Database::value('SELECT 1 FROM users WHERE email = ?', [mb_strtolower($email)]) !== null;
    }

    public static function create(string $username, string $email, string $password): int
    {
        Database::run(
            'INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)',
            [$username, mb_strtolower($email), password_hash($password, PASSWORD_DEFAULT)]
        );
        return Database::lastInsertId();
    }

    public static function touchLogin(int $id): void
    {
        Database::run('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    }

    public static function updatePasswordHash(int $id, string $password): void
    {
        Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
    }
}
