<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;

/**
 * Favoris d'un utilisateur : équipes (ref_id = id) et compétitions (ref_id = code).
 */
final class Favorite
{
    public const TYPES = ['team', 'competition'];

    /** @var array<int, array{teams: array<int,true>, competitions: array<string,true>}> */
    private static array $cache = [];

    /** @return array{teams: array<int,true>, competitions: array<string,true>} */
    public static function mapForUser(?int $userId): array
    {
        $map = ['teams' => [], 'competitions' => []];
        if (!$userId) {
            return $map;
        }
        if (isset(self::$cache[$userId])) {
            return self::$cache[$userId];
        }
        foreach (Database::fetchAll('SELECT type, ref_id FROM favorites WHERE user_id = ?', [$userId]) as $row) {
            if ($row['type'] === 'team') {
                $map['teams'][(int) $row['ref_id']] = true;
            } else {
                $map['competitions'][(string) $row['ref_id']] = true;
            }
        }
        return self::$cache[$userId] = $map;
    }

    /** Favoris de l'utilisateur connecté (tableaux vides si personne n'est connecté). */
    public static function forCurrentUser(): array
    {
        return self::mapForUser(Auth::id());
    }

    public static function listForUser(int $userId): array
    {
        return Database::fetchAll(
            'SELECT id, type, ref_id, name, crest, created_at FROM favorites WHERE user_id = ? ORDER BY type, name',
            [$userId]
        );
    }

    public static function exists(int $userId, string $type, string $refId): bool
    {
        return Database::value(
            'SELECT 1 FROM favorites WHERE user_id = ? AND type = ? AND ref_id = ?',
            [$userId, $type, $refId]
        ) !== null;
    }

    public static function add(int $userId, string $type, string $refId, string $name, ?string $crest): void
    {
        Database::run(
            'INSERT IGNORE INTO favorites (user_id, type, ref_id, name, crest) VALUES (?, ?, ?, ?, ?)',
            [$userId, $type, $refId, mb_substr($name, 0, 150), $crest]
        );
        unset(self::$cache[$userId]);
    }

    public static function remove(int $userId, string $type, string $refId): void
    {
        Database::run('DELETE FROM favorites WHERE user_id = ? AND type = ? AND ref_id = ?', [$userId, $type, $refId]);
        unset(self::$cache[$userId]);
    }

    public static function countForUser(int $userId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM favorites WHERE user_id = ?', [$userId]);
    }
}
