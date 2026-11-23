<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Competition;
use App\Models\Team;
use Throwable;

/**
 * Recherche d'équipes et de compétitions dans l'index local.
 */
final class SearchService
{
    /** @return array{competitions: list<array>, teams: list<array>} */
    public static function search(string $q, int $competitionLimit = 10, int $teamLimit = 30): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return ['competitions' => [], 'teams' => []];
        }
        $q = mb_substr($q, 0, 60);

        $competitions = array_map(static fn (array $c): array => [
            'display_name' => Format::competitionName($c),
            'name'         => (string) $c['name'],
            'area'         => Format::area($c['area_name'] ?? null),
            'emblem'       => $c['emblem'] ?? null,
            'code'         => $c['code'] ?? null,
            'url'          => url('competition/' . ($c['code'] ?: $c['id'])),
        ], Competition::search($q, $competitionLimit));

        $teams = array_map(static fn (array $t): array => [
            'id'    => (int) $t['id'],
            'name'  => (string) $t['name'],
            'short' => $t['short_name'] ?? null,
            'area'  => Format::area($t['area_name'] ?? null),
            'crest' => $t['crest'] ?? null,
            'url'   => url('equipe/' . (int) $t['id']),
        ], Team::search($q, $teamLimit));

        return ['competitions' => $competitions, 'teams' => $teams];
    }

    /**
     * Complète l'index des équipes en douceur : une compétition à la fois,
     * seulement s'il reste de la marge dans le quota de la minute en cours.
     */
    public static function growIndex(FootballData $api): void
    {
        if (!$api->hasKey()) {
            return;
        }
        try {
            if ($api->remainingSlots() < 4) {
                return;
            }
            $next = Competition::nextToSync();
            if ($next !== null && !empty($next['code'])) {
                $api->competitionTeams((string) $next['code']);
            }
        } catch (Throwable $e) {
            // L'index se complétera plus tard : la recherche reste fonctionnelle.
            if ($e instanceof ApiException && $e->kind === ApiException::RESTRICTED && isset($next['code'])) {
                Competition::markTeamsSynced((string) $next['code']);
            }
        }
    }
}
