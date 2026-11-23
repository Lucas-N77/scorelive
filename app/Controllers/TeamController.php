<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Competition;
use App\Models\Favorite;
use App\Services\ApiException;
use App\Services\Format;
use App\Services\MatchFeed;
use App\Services\MatchPresenter;

/**
 * Page d'une équipe : derniers résultats, prochains matchs, effectif, infos.
 */
final class TeamController extends Controller
{
    private const COLORS = [
        'navy blue' => '#1b2a4a', 'royal blue' => '#2a52be', 'sky blue' => '#74b9e8', 'light blue' => '#9ad0f5',
        'dark blue' => '#14306b', 'dark green' => '#0b5d1e', 'red' => '#d7263d', 'white' => '#ffffff', 'blue' => '#1f4fd1',
        'navy' => '#1b2a4a', 'black' => '#111318', 'yellow' => '#f5d000', 'gold' => '#d4a017', 'green' => '#1f9d55',
        'orange' => '#f47b20', 'purple' => '#6b2d8f', 'violet' => '#7f3fbf', 'claret' => '#7a1f3d', 'maroon' => '#7b1e2b',
        'burgundy' => '#800020', 'grey' => '#8a8f98', 'gray' => '#8a8f98', 'silver' => '#c0c4cc', 'brown' => '#7b4b2a',
        'amber' => '#ffbf00', 'pink' => '#f49ac2', 'cherry' => '#a4002b', 'tangerine' => '#f28500', 'lime' => '#9acd32',
        'turquoise' => '#2bb3b1', 'crimson' => '#b0102b', 'scarlet' => '#e0201b',
    ];

    public function show(string $id): void
    {
        $teamId = (int) $id;
        $api = $this->api();
        $team = $api->team($teamId);

        $matches = [];
        $matchesMessage = null;
        $today = Format::today();
        try {
            $data = $api->teamMatches($teamId, [
                'dateFrom' => $today->modify('-90 days')->format('Y-m-d'),
                'dateTo'   => $today->modify('+90 days')->format('Y-m-d'),
                'limit'    => 100,
            ]);
            $matches = is_array($data['matches'] ?? null) ? $data['matches'] : [];
        } catch (ApiException $e) {
            if (in_array($e->kind, [ApiException::MISSING_KEY, ApiException::INVALID_KEY], true)) {
                throw $e;
            }
            $matchesMessage = $e->getMessage();
        }

        $favorites = Favorite::forCurrentUser();
        $finished = [];
        $upcoming = [];
        $form = [];
        foreach ($matches as $m) {
            $vm = MatchPresenter::present($m, $favorites['teams']);
            $vm['result'] = Format::resultFor($m, $teamId);
            if ($vm['state'] === 'finished') {
                $finished[] = $vm;
            } elseif ($vm['state'] === 'live' || $vm['state'] === 'upcoming' || $vm['state'] === 'off') {
                $upcoming[] = $vm;
            }
        }
        usort($finished, static fn (array $a, array $b): int => MatchFeed::compareKickoff($b, $a));
        usort($upcoming, [MatchFeed::class, 'compareKickoff']);
        // Les matchs reportés/annulés passés n'ont rien à faire dans « à venir »
        $upcoming = array_values(array_filter($upcoming, static fn (array $vm): bool => $vm['state'] !== 'off' || ($vm['kickoff'] && $vm['kickoff'] >= $today)));
        foreach (array_slice($finished, 0, 5) as $vm) {
            if ($vm['result']) {
                $form[] = $vm['result'];
            }
        }

        $accessibleCodes = array_column(Competition::accessible(), 'code');
        $competitions = [];
        foreach (is_array($team['runningCompetitions'] ?? null) ? $team['runningCompetitions'] : [] as $c) {
            $code = (string) ($c['code'] ?? '');
            $competitions[] = [
                'name'   => Format::competitionName($c),
                'emblem' => $c['emblem'] ?? null,
                'url'    => $code !== '' && in_array($code, $accessibleCodes, true) ? url('competition/' . $code) : null,
            ];
        }

        $name = Format::teamName($team, false);
        $this->render('team', [
            'title'          => $name,
            'description'    => 'Résultats, calendrier et effectif : ' . $name . '.',
            'nav'            => 'team',
            'team'           => $team,
            'teamId'         => $teamId,
            'name'           => $name,
            'area'           => Format::area($team['area']['name'] ?? null),
            'flag'           => $team['area']['flag'] ?? null,
            'finished'       => array_slice($finished, 0, 10),
            'upcoming'       => array_slice($upcoming, 0, 10),
            'form'           => array_reverse($form),
            'matchesMessage' => $matchesMessage,
            'squad'          => $this->squad($team),
            'coach'          => $team['coach'] ?? null,
            'competitions'   => $competitions,
            'colors'         => $this->swatches((string) ($team['clubColors'] ?? '')),
            'isFavorite'     => isset($favorites['teams'][$teamId]),
            'website'        => $this->website($team['website'] ?? null),
        ]);
    }

    /** Effectif groupé par ligne (gardiens, défenseurs, milieux, attaquants). */
    private function squad(array $team): array
    {
        $labels = ['G' => 'Gardiens', 'D' => 'Défenseurs', 'M' => 'Milieux', 'A' => 'Attaquants', '?' => 'Autres'];
        $groups = array_fill_keys(array_keys($labels), []);
        foreach (is_array($team['squad'] ?? null) ? $team['squad'] : [] as $player) {
            $groups[Format::positionGroup($player['position'] ?? null)][] = [
                'name'        => (string) ($player['name'] ?? '?'),
                'number'      => $player['shirtNumber'] ?? null,
                'position'    => Format::position($player['position'] ?? null),
                'nationality' => Format::area($player['nationality'] ?? null),
                'age'         => Format::age($player['dateOfBirth'] ?? null),
            ];
        }
        $out = [];
        foreach ($groups as $key => $players) {
            if ($players) {
                usort($players, static fn (array $a, array $b): int => [$a['number'] ?? 999, $a['name']] <=> [$b['number'] ?? 999, $b['name']]);
                $out[] = ['label' => $labels[$key], 'players' => $players];
            }
        }
        return $out;
    }

    /** « Red / White » → pastilles de couleur. */
    private function swatches(string $clubColors): array
    {
        $swatches = [];
        foreach (preg_split('#\s*/\s*#', strtolower($clubColors)) ?: [] as $color) {
            $color = trim($color);
            if ($color === '') {
                continue;
            }
            foreach (self::COLORS as $name => $hex) {
                if (str_contains($color, $name)) {
                    $swatches[] = ['name' => $color, 'hex' => $hex];
                    break;
                }
            }
        }
        return $swatches;
    }

    private function website(mixed $url): ?string
    {
        return is_string($url) && preg_match('#^https?://[^\s"<>]+$#i', $url) ? $url : null;
    }
}
