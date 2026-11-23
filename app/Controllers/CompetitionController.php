<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Favorite;
use App\Services\ApiException;
use App\Services\Format;
use App\Services\MatchFeed;
use App\Services\MatchPresenter;

/**
 * Page d'une compétition : classement et matchs (navigation par journée).
 */
final class CompetitionController extends Controller
{
    public function show(string $code): void
    {
        $code = strtoupper($code);
        $api = $this->api();
        $favorites = Favorite::forCurrentUser();

        // 1. Classement (contient aussi la compétition, la zone et la saison en cours)
        $standings = null;
        $standingsMessage = null;
        try {
            $standings = $api->standings($code);
        } catch (ApiException $e) {
            if (!in_array($e->kind, [ApiException::NOT_FOUND, ApiException::BAD_REQUEST], true)) {
                throw $e;
            }
            $standingsMessage = 'Aucun classement n’est disponible pour cette compétition.';
        }

        if ($standings !== null) {
            $competition = is_array($standings['competition'] ?? null) ? $standings['competition'] : [];
            $area = is_array($standings['area'] ?? null) ? $standings['area'] : [];
            $season = is_array($standings['season'] ?? null) ? $standings['season'] : [];
        } else {
            $info = $api->competition($code);
            $competition = $info;
            $area = is_array($info['area'] ?? null) ? $info['area'] : [];
            $season = is_array($info['currentSeason'] ?? null) ? $info['currentSeason'] : [];
        }

        $tables = $this->tables($standings, $favorites['teams']);
        if ($standings !== null && !$tables) {
            $standingsMessage = 'Le classement n’est pas encore disponible.';
        }

        // 2. Matchs : journée demandée, sinon journée en cours, sinon fenêtre de dates
        $current = (int) ($season['currentMatchday'] ?? 0);
        $requested = (int) Request::query('journee', '0');
        $matchday = $requested > 0 ? min($requested, 99) : $current;
        $maxMatchday = $this->maxMatchday($competition, $tables);
        if ($maxMatchday !== null && $matchday > $maxMatchday) {
            $matchday = $maxMatchday;
        }

        $matches = [];
        $mode = 'matchday';
        $matchesMessage = null;
        try {
            if ($matchday > 0) {
                $matches = $api->competitionMatches($code, ['matchday' => $matchday])['matches'] ?? [];
            }
            if (!$matches && $requested === 0) {
                $mode = 'window';
                $today = Format::today();
                $matches = $api->competitionMatches($code, [
                    'dateFrom' => $today->modify('-4 days')->format('Y-m-d'),
                    'dateTo'   => $today->modify('+6 days')->format('Y-m-d'),
                ])['matches'] ?? [];
            }
        } catch (ApiException $e) {
            if (in_array($e->kind, [ApiException::MISSING_KEY, ApiException::INVALID_KEY, ApiException::RESTRICTED], true)) {
                throw $e;
            }
            $matchesMessage = $e->getMessage();
        }

        $presented = array_map(static fn (array $m): array => MatchPresenter::present($m, $favorites['teams']), is_array($matches) ? $matches : []);
        usort($presented, [MatchFeed::class, 'compareKickoff']);

        $key = (string) ($competition['code'] ?? $code);
        $name = Format::competitionName($competition) ?: $code;

        $this->render('competition', [
            'title'            => $name,
            'description'      => 'Classement, résultats et calendrier : ' . $name . '.',
            'nav'              => 'competition',
            'navCompetition'   => $key,
            'competition'      => $competition,
            'competitionKey'   => $key,
            'name'             => $name,
            'area'             => $area,
            'season'           => $season,
            'currentMatchday'  => $current,
            'matchday'         => $matchday,
            'maxMatchday'      => $maxMatchday,
            'mode'             => $mode,
            'tables'           => $tables,
            'standingsMessage' => $standingsMessage,
            'days'             => MatchFeed::groupByDay($presented),
            'matchesMessage'   => $matchesMessage,
            'isFavorite'       => isset($favorites['competitions'][$key]),
            'defaultTab'       => $requested > 0 || !$tables ? 'matchs' : 'classement',
        ]);
    }

    /** Tableaux « TOTAL » du classement (un par groupe pour les compétitions à poules). */
    private function tables(?array $standings, array $favTeams): array
    {
        if ($standings === null) {
            return [];
        }
        $groups = array_values(array_filter(
            is_array($standings['standings'] ?? null) ? $standings['standings'] : [],
            static fn (array $s): bool => ($s['type'] ?? 'TOTAL') === 'TOTAL' && !empty($s['table'])
        ));

        $tables = [];
        foreach ($groups as $standing) {
            $rows = [];
            $hasForm = false;
            foreach ($standing['table'] as $row) {
                $team = is_array($row['team'] ?? null) ? $row['team'] : [];
                $teamId = (int) ($team['id'] ?? 0);
                $form = $this->form($row['form'] ?? null);
                $hasForm = $hasForm || $form !== [];
                $rows[] = [
                    'position' => (int) ($row['position'] ?? 0),
                    'team'     => [
                        'id'    => $teamId,
                        'name'  => Format::teamName($team),
                        'full'  => Format::teamName($team, false),
                        'crest' => $team['crest'] ?? null,
                        'url'   => $teamId > 0 ? url('equipe/' . $teamId) : null,
                    ],
                    'played'   => (int) ($row['playedGames'] ?? 0),
                    'won'      => (int) ($row['won'] ?? 0),
                    'draw'     => (int) ($row['draw'] ?? 0),
                    'lost'     => (int) ($row['lost'] ?? 0),
                    'gf'       => (int) ($row['goalsFor'] ?? 0),
                    'ga'       => (int) ($row['goalsAgainst'] ?? 0),
                    'gd'       => (int) ($row['goalDifference'] ?? 0),
                    'points'   => (int) ($row['points'] ?? 0),
                    'form'     => $form,
                    'favorite' => isset($favTeams[$teamId]),
                ];
            }
            $title = Format::group($standing['group'] ?? null);
            if ($title === '' && count($groups) > 1) {
                $title = Format::stage($standing['stage'] ?? null);
            }
            $tables[] = ['title' => $title, 'rows' => $rows, 'hasForm' => $hasForm];
        }
        return $tables;
    }

    /** « W,D,L » → [['V', 'Victoire'], …] (5 derniers matchs). */
    private function form(mixed $form): array
    {
        if (!is_string($form) || $form === '') {
            return [];
        }
        $map = ['W' => ['V', 'Victoire'], 'D' => ['N', 'Match nul'], 'L' => ['D', 'Défaite']];
        $out = [];
        foreach (array_slice(explode(',', $form), 0, 5) as $letter) {
            $letter = strtoupper(trim($letter));
            if (isset($map[$letter])) {
                $out[] = $map[$letter];
            }
        }
        return $out;
    }

    /** Nombre de journées d'un championnat à poule unique (aller-retour), sinon inconnu. */
    private function maxMatchday(array $competition, array $tables): ?int
    {
        if (($competition['type'] ?? '') !== 'LEAGUE' || count($tables) !== 1) {
            return null;
        }
        $teams = count($tables[0]['rows']);
        return $teams >= 2 ? 2 * ($teams - 1) : null;
    }
}
