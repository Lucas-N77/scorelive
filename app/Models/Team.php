<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Index local des équipes (recherche, favoris), alimenté par les réponses de l'API.
 */
final class Team
{
    public static function upsertMany(array $rows): void
    {
        foreach (array_chunk($rows, 200) as $chunk) {
            $values = [];
            $params = [];
            foreach ($chunk as $r) {
                $values[] = '(?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())';
                array_push($params, $r['id'], $r['name'], $r['short_name'], $r['tla'], $r['crest'], $r['area_name']);
            }
            Database::run(
                'INSERT INTO teams (id, name, short_name, tla, crest, area_name, updated_at)
                 VALUES ' . implode(', ', $values) . '
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    short_name = COALESCE(VALUES(short_name), short_name),
                    tla = COALESCE(VALUES(tla), tla),
                    crest = COALESCE(VALUES(crest), crest),
                    area_name = COALESCE(VALUES(area_name), area_name),
                    updated_at = VALUES(updated_at)',
                $params
            );
        }
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM teams WHERE id = ?', [$id]);
    }

    /** Recherche insensible à la casse et aux accents (collation utf8mb4_unicode_ci). */
    public static function search(string $q, int $limit = 20): array
    {
        $escaped = Competition::escapeLike($q);
        return Database::fetchAll(
            'SELECT id, name, short_name, tla, crest, area_name
               FROM teams
              WHERE name LIKE :like1 OR short_name LIKE :like2 OR tla = :tla
              ORDER BY (name LIKE :prefix1 OR short_name LIKE :prefix2) DESC, CHAR_LENGTH(name), name
              LIMIT :lim',
            [
                'like1'   => '%' . $escaped . '%',
                'like2'   => '%' . $escaped . '%',
                'tla'     => mb_strtoupper($q),
                'prefix1' => $escaped . '%',
                'prefix2' => $escaped . '%',
                'lim'     => $limit,
            ]
        );
    }

    public static function count(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM teams');
    }
}
