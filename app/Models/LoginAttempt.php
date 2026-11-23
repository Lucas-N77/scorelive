<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Limitation des tentatives de connexion : 5 échecs par IP sur 15 minutes.
 */
final class LoginAttempt
{
    public const MAX_ATTEMPTS = 5;
    public const WINDOW_MINUTES = 15;

    public static function tooMany(string $ip): bool
    {
        return self::recentCount($ip) >= self::MAX_ATTEMPTS;
    }

    /** Minutes restantes avant de pouvoir réessayer. */
    public static function retryAfterMinutes(string $ip): int
    {
        $elapsed = Database::value(
            'SELECT TIMESTAMPDIFF(MINUTE, MIN(attempted_at), UTC_TIMESTAMP()) FROM login_attempts
              WHERE ip = ? AND attempted_at > UTC_TIMESTAMP() - INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE',
            [$ip]
        );
        return max(1, self::WINDOW_MINUTES - (int) $elapsed);
    }

    public static function record(string $ip, string $identifier): void
    {
        Database::run(
            'INSERT INTO login_attempts (ip, identifier, attempted_at) VALUES (?, ?, UTC_TIMESTAMP())',
            [$ip, mb_substr($identifier, 0, 190)]
        );
    }

    public static function clear(string $ip): void
    {
        Database::run('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
    }

    private static function recentCount(string $ip): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts
              WHERE ip = ? AND attempted_at > UTC_TIMESTAMP() - INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE',
            [$ip]
        );
    }
}
