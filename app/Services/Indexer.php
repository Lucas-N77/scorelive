<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Competition;
use App\Models\Team;
use Throwable;

/**
 * Alimente l'index local (tables teams / competitions) à partir de chaque réponse
 * de l'API : la recherche fonctionne ainsi sans requête supplémentaire.
 */
final class Indexer
{
    public static function ingest(string $endpoint, array $data): void
    {
        try {
            $teams = [];
            $competitions = [];

            $addTeam = static function (mixed $t) use (&$teams): void {
                if (!is_array($t) || empty($t['id']) || empty($t['name'])) {
                    return;
                }
                $id = (int) $t['id'];
                $teams[$id] = [
                    'id'         => $id,
                    'name'       => mb_substr((string) $t['name'], 0, 150),
                    'short_name' => isset($t['shortName']) && $t['shortName'] !== '' ? mb_substr((string) $t['shortName'], 0, 100) : null,
                    'tla'        => isset($t['tla']) && $t['tla'] !== '' ? mb_substr((string) $t['tla'], 0, 10) : null,
                    'crest'      => self::url($t['crest'] ?? null),
                    'area_name'  => isset($t['area']['name']) ? mb_substr((string) $t['area']['name'], 0, 100) : ($teams[$id]['area_name'] ?? null),
                ];
            };

            $addCompetition = static function (mixed $c, mixed $area = null) use (&$competitions): void {
                if (!is_array($c) || empty($c['id']) || empty($c['name'])) {
                    return;
                }
                $area = is_array($area) ? $area : (is_array($c['area'] ?? null) ? $c['area'] : []);
                $id = (int) $c['id'];
                $competitions[$id] = [
                    'id'        => $id,
                    'code'      => isset($c['code']) && $c['code'] !== '' ? mb_substr((string) $c['code'], 0, 12) : null,
                    'name'      => mb_substr((string) $c['name'], 0, 150),
                    'type'      => isset($c['type']) ? mb_substr((string) $c['type'], 0, 20) : null,
                    'emblem'    => self::url($c['emblem'] ?? null),
                    'plan'      => isset($c['plan']) ? mb_substr((string) $c['plan'], 0, 20) : null,
                    'area_name' => isset($area['name']) ? mb_substr((string) $area['name'], 0, 100) : null,
                    'area_code' => isset($area['code']) ? mb_substr((string) $area['code'], 0, 10) : null,
                    'area_flag' => self::url($area['flag'] ?? null),
                ];
            };

            // Listes de matchs (/matches, /competitions/X/matches, /teams/X/matches, head2head)
            foreach (is_array($data['matches'] ?? null) ? $data['matches'] : [] as $m) {
                $addCompetition($m['competition'] ?? null, $m['area'] ?? null);
                $addTeam($m['homeTeam'] ?? null);
                $addTeam($m['awayTeam'] ?? null);
            }
            // Match seul (/matches/{id})
            if (isset($data['homeTeam'], $data['awayTeam'])) {
                $addCompetition($data['competition'] ?? null, $data['area'] ?? null);
                $addTeam($data['homeTeam']);
                $addTeam($data['awayTeam']);
            }
            // Liste des compétitions (/competitions)
            foreach (is_array($data['competitions'] ?? null) ? $data['competitions'] : [] as $c) {
                $addCompetition($c);
            }
            // Ressources rattachées à une compétition (classement, équipes, matchs)
            if (is_array($data['competition'] ?? null)) {
                $addCompetition($data['competition'], $data['area'] ?? null);
            }
            foreach (is_array($data['standings'] ?? null) ? $data['standings'] : [] as $standing) {
                foreach (is_array($standing['table'] ?? null) ? $standing['table'] : [] as $row) {
                    $addTeam($row['team'] ?? null);
                }
            }
            foreach (is_array($data['teams'] ?? null) ? $data['teams'] : [] as $t) {
                $addTeam($t);
            }
            // Fiche équipe (/teams/{id})
            if (preg_match('#^teams/\d+$#', $endpoint) && isset($data['id'], $data['name'])) {
                $addTeam($data);
                foreach (is_array($data['runningCompetitions'] ?? null) ? $data['runningCompetitions'] : [] as $c) {
                    $addCompetition($c, null);
                }
            }
            // Fiche compétition (/competitions/{code})
            if (preg_match('#^competitions/[^/?]+$#', $endpoint) && isset($data['id'], $data['name'])) {
                $addCompetition($data);
            }

            Competition::upsertMany(array_values($competitions));
            Team::upsertMany(array_values($teams));

            if (preg_match('#^competitions/([^/?]+)/teams#', $endpoint, $m)) {
                Competition::markTeamsSynced(rawurldecode($m[1]));
            }
        } catch (Throwable $e) {
            error_log('[ScoreLive] Indexation impossible : ' . $e->getMessage());
        }
    }

    private static function url(mixed $value): ?string
    {
        return is_string($value) && preg_match('#^https?://#i', $value) && strlen($value) <= 255 ? $value : null;
    }
}
