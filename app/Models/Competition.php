<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\Format;

/**
 * Index local des compétitions (menu, recherche, favoris).
 */
final class Competition
{
    /** Ordre d'affichage par défaut (public francophone). */
    public const PRIORITY = ['CL', 'FL1', 'PL', 'PD', 'SA', 'BL1', 'PPL', 'DED', 'ELC', 'BSA', 'WC', 'EC'];

    public static function upsertMany(array $rows): void
    {
        foreach (array_chunk($rows, 100) as $chunk) {
            $values = [];
            $params = [];
            foreach ($chunk as $r) {
                $values[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())';
                array_push(
                    $params,
                    $r['id'],
                    $r['code'],
                    $r['name'],
                    $r['type'],
                    $r['emblem'],
                    $r['plan'],
                    $r['area_name'],
                    $r['area_code'],
                    $r['area_flag']
                );
            }
            Database::run(
                'INSERT INTO competitions (id, code, name, type, emblem, plan, area_name, area_code, area_flag, updated_at)
                 VALUES ' . implode(', ', $values) . '
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    code = COALESCE(VALUES(code), code),
                    type = COALESCE(VALUES(type), type),
                    emblem = COALESCE(VALUES(emblem), emblem),
                    plan = COALESCE(VALUES(plan), plan),
                    area_name = COALESCE(VALUES(area_name), area_name),
                    area_code = COALESCE(VALUES(area_code), area_code),
                    area_flag = COALESCE(VALUES(area_flag), area_flag),
                    updated_at = VALUES(updated_at)',
                $params
            );
        }
    }

    /** Recherche par code (« PL ») ou identifiant numérique (« 2021 »). */
    public static function findByKey(string $key): ?array
    {
        if (ctype_digit($key)) {
            return Database::fetch('SELECT * FROM competitions WHERE id = ?', [(int) $key]);
        }
        return Database::fetch('SELECT * FROM competitions WHERE code = ? LIMIT 1', [strtoupper($key)]);
    }

    /** Compétitions couvertes par l'offre configurée (api.plans), triées par priorité. */
    public static function accessible(): array
    {
        $plans = self::plans();
        $in = implode(', ', array_fill(0, count($plans), '?'));
        return self::sortByPriority(Database::fetchAll("SELECT * FROM competitions WHERE plan IN ($in)", $plans));
    }

    public static function search(string $q, int $limit = 10): array
    {
        $like = '%' . self::escapeLike($q) . '%';
        $plans = self::plans();
        $in = implode(', ', array_fill(0, count($plans), '?'));

        // Recherche aussi sur les noms français (« Ligue des champions », « Angleterre »…)
        $codes = Format::competitionCodesMatching($q);
        $areas = Format::areasMatching($q);
        $extra = '';
        $extraParams = [];
        if ($codes) {
            $extra .= ' OR code IN (' . implode(', ', array_fill(0, count($codes), '?')) . ')';
            $extraParams = array_merge($extraParams, $codes);
        }
        if ($areas) {
            $extra .= ' OR area_name IN (' . implode(', ', array_fill(0, count($areas), '?')) . ')';
            $extraParams = array_merge($extraParams, $areas);
        }

        $rows = Database::fetchAll(
            "SELECT * FROM competitions
              WHERE (name LIKE ? OR code = ? OR area_name LIKE ?{$extra})
                AND (plan IS NULL OR plan IN ($in))
              ORDER BY name
              LIMIT ?",
            array_merge([$like, strtoupper($q), $like], $extraParams, $plans, [$limit])
        );
        return self::sortByPriority($rows);
    }

    public static function markTeamsSynced(string $key): void
    {
        if (ctype_digit($key)) {
            Database::run('UPDATE competitions SET teams_synced_at = UTC_TIMESTAMP() WHERE id = ?', [(int) $key]);
        } else {
            Database::run('UPDATE competitions SET teams_synced_at = UTC_TIMESTAMP() WHERE code = ?', [strtoupper($key)]);
        }
    }

    /** Prochaine compétition dont la liste d'équipes est absente ou date de plus de 7 jours. */
    public static function nextToSync(): ?array
    {
        $plans = self::plans();
        $in = implode(', ', array_fill(0, count($plans), '?'));
        return Database::fetch(
            "SELECT * FROM competitions
              WHERE plan IN ($in) AND code IS NOT NULL
                AND (teams_synced_at IS NULL OR teams_synced_at < UTC_TIMESTAMP() - INTERVAL 7 DAY)
              ORDER BY teams_synced_at IS NOT NULL, teams_synced_at
              LIMIT 1",
            $plans
        );
    }

    /** @return array{0:int,1:int} [compétitions indexées, total] */
    public static function syncProgress(): array
    {
        $plans = self::plans();
        $in = implode(', ', array_fill(0, count($plans), '?'));
        $row = Database::fetch(
            "SELECT COUNT(*) AS total, SUM(teams_synced_at IS NOT NULL) AS synced FROM competitions WHERE plan IN ($in)",
            $plans
        );
        return [(int) ($row['synced'] ?? 0), (int) ($row['total'] ?? 0)];
    }

    /** @return list<string> */
    public static function plans(): array
    {
        $plans = config('api.plans', ['TIER_ONE']);
        $plans = is_array($plans) ? array_values(array_filter($plans, 'is_string')) : [];
        return $plans ?: ['TIER_ONE'];
    }

    public static function sortByPriority(array $rows): array
    {
        usort($rows, static function (array $a, array $b): int {
            return [self::rank($a['code'] ?? null), (string) $a['name']] <=> [self::rank($b['code'] ?? null), (string) $b['name']];
        });
        return $rows;
    }

    public static function rank(?string $code): int
    {
        $index = $code !== null ? array_search($code, self::PRIORITY, true) : false;
        return $index === false ? 999 : (int) $index;
    }

    public static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
