<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

/**
 * Transforme les objets « match » de l'API en données prêtes à afficher
 * (vues PHP et endpoint JSON du rafraîchissement en direct).
 */
final class MatchPresenter
{
    /** @param array<int,true> $favTeams */
    public static function present(array $m, array $favTeams = []): array
    {
        $status = (string) ($m['status'] ?? 'SCHEDULED');
        $state = Format::state($status);
        $kickoff = Format::date($m['utcDate'] ?? null);
        [$homeScore, $awayScore] = Format::scores($m);
        $winner = $m['score']['winner'] ?? null;
        $competition = is_array($m['competition'] ?? null) ? $m['competition'] : [];
        $competitionKey = (string) ($competition['code'] ?? ($competition['id'] ?? ''));
        $penalties = Format::penalties($m);
        $duration = (string) ($m['score']['duration'] ?? 'REGULAR');
        $minute = $state === 'live' ? Format::minute($m) : null;

        $note = '';
        if ($duration === 'PENALTY_SHOOTOUT' && $penalties) {
            $note = 't.a.b. ' . $penalties[0] . '-' . $penalties[1];
        } elseif ($duration === 'EXTRA_TIME' && $state === 'finished') {
            $note = 'a.p.';
        }

        $home = self::side(is_array($m['homeTeam'] ?? null) ? $m['homeTeam'] : [], $homeScore, $winner === 'HOME_TEAM', $favTeams);
        $away = self::side(is_array($m['awayTeam'] ?? null) ? $m['awayTeam'] : [], $awayScore, $winner === 'AWAY_TEAM', $favTeams);

        return [
            'id'          => (int) ($m['id'] ?? 0),
            'url'         => url('match/' . (int) ($m['id'] ?? 0)),
            'status'      => $status,
            'state'       => $state,
            'label'       => Format::statusLabel($status),
            'clock'       => self::clock($m, $state, $minute, $kickoff),
            'tick'        => $minute !== null,
            'minute'      => $minute,
            'kickoff'     => $kickoff,
            'kickoff_iso' => $kickoff ? gmdate('Y-m-d\TH:i:s\Z', $kickoff->getTimestamp()) : '',
            'time'        => Format::time($kickoff),
            'home'        => $home,
            'away'        => $away,
            'ht'          => Format::halfTime($m),
            'penalties'   => $penalties,
            'note'        => $note,
            'competition' => [
                'key'    => $competitionKey,
                'name'   => Format::competitionName($competition),
                'emblem' => $competition['emblem'] ?? null,
                'url'    => $competitionKey !== '' ? url('competition/' . $competitionKey) : null,
            ],
            'matchday'    => isset($m['matchday']) ? (int) $m['matchday'] : null,
            'stage'       => (string) ($m['stage'] ?? ''),
            'group'       => Format::group($m['group'] ?? null),
            'favorite'    => $home['favorite'] || $away['favorite'],
        ];
    }

    private static function side(array $team, ?int $score, bool $winner, array $favTeams): array
    {
        $id = (int) ($team['id'] ?? 0);
        return [
            'id'       => $id,
            'name'     => Format::teamName($team),
            'full'     => Format::teamName($team, false),
            'crest'    => $team['crest'] ?? null,
            'url'      => $id > 0 ? url('equipe/' . $id) : null,
            'score'    => $score,
            'winner'   => $winner,
            'favorite' => $id > 0 && isset($favTeams[$id]),
        ];
    }

    /** Texte de la colonne horaire : minute de jeu, heure, « Terminé », « Reporté »… */
    private static function clock(array $m, string $state, ?string $minute, ?DateTimeImmutable $kickoff): string
    {
        return match ($state) {
            'live'     => $minute ?? Format::liveLabel($m),
            'finished' => ($m['status'] ?? '') === 'AWARDED' ? 'Tapis vert' : 'Terminé',
            'upcoming' => Format::time($kickoff),
            default    => Format::statusLabel((string) ($m['status'] ?? '')),
        };
    }

    /** Version compacte envoyée au navigateur toutes les 30 s. */
    public static function live(array $vm): array
    {
        return [
            'id'         => $vm['id'],
            'status'     => $vm['status'],
            'state'      => $vm['state'],
            'label'      => $vm['label'],
            'clock'      => $vm['clock'],
            'tick'       => $vm['tick'],
            'home'       => $vm['home']['score'],
            'away'       => $vm['away']['score'],
            'homeWinner' => $vm['home']['winner'],
            'awayWinner' => $vm['away']['winner'],
            'note'       => $vm['note'],
            'ht'         => $vm['ht'] ? $vm['ht'][0] . '-' . $vm['ht'][1] : null,
        ];
    }

    /** Libellé de la manche : « Journée 12 », « Huitièmes de finale · Groupe A »… */
    public static function round(array $m): string
    {
        $parts = [];
        $stage = (string) ($m['stage'] ?? '');
        if ($stage !== '' && !in_array($stage, ['REGULAR_SEASON', 'LEAGUE_STAGE', 'GROUP_STAGE'], true)) {
            $parts[] = Format::stage($stage);
        } elseif (!empty($m['matchday'])) {
            $parts[] = 'Journée ' . (int) $m['matchday'];
        }
        if (!empty($m['group'])) {
            $parts[] = Format::group($m['group']);
        }
        return implode(' · ', $parts);
    }

    // ---------------------------------------------------------------------
    // Page match : chronologie, compositions, statistiques, infos, face-à-face
    // ---------------------------------------------------------------------

    /** Buts, cartons et remplacements triés par minute, avec séparateurs de périodes. */
    public static function events(array $m): array
    {
        $homeId = (int) ($m['homeTeam']['id'] ?? 0);
        $events = [];

        $sideOf = static fn (mixed $team): string => (int) ($team['id'] ?? 0) === $homeId ? 'home' : 'away';

        $previous = [0, 0];
        foreach (is_array($m['goals'] ?? null) ? $m['goals'] : [] as $goal) {
            $side = $sideOf($goal['team'] ?? null);
            if (isset($goal['score']['home'], $goal['score']['away'])) {
                $now = [(int) $goal['score']['home'], (int) $goal['score']['away']];
                // La progression du score indique de façon fiable quelle équipe marque (y compris c.s.c.).
                if ($now[0] > $previous[0]) {
                    $side = 'home';
                } elseif ($now[1] > $previous[1]) {
                    $side = 'away';
                }
                $previous = $now;
            }
            $events[] = [
                'type'   => 'goal',
                'kind'   => (string) ($goal['type'] ?? 'REGULAR'),
                'minute' => self::minuteText($goal),
                'sort'   => self::minuteSort($goal),
                'side'   => $side,
                'player' => (string) ($goal['scorer']['name'] ?? 'Buteur inconnu'),
                'assist' => isset($goal['assist']['name']) ? (string) $goal['assist']['name'] : null,
                'score'  => isset($goal['score']['home'], $goal['score']['away']) ? $goal['score']['home'] . ' - ' . $goal['score']['away'] : null,
            ];
        }
        foreach (is_array($m['bookings'] ?? null) ? $m['bookings'] : [] as $booking) {
            $events[] = [
                'type'   => 'card',
                'kind'   => (string) ($booking['card'] ?? 'YELLOW'),
                'minute' => self::minuteText($booking),
                'sort'   => self::minuteSort($booking) + 0.001,
                'side'   => $sideOf($booking['team'] ?? null),
                'player' => (string) ($booking['player']['name'] ?? 'Joueur'),
            ];
        }
        foreach (is_array($m['substitutions'] ?? null) ? $m['substitutions'] : [] as $sub) {
            $events[] = [
                'type'   => 'sub',
                'kind'   => 'SUB',
                'minute' => self::minuteText($sub),
                'sort'   => self::minuteSort($sub) + 0.002,
                'side'   => $sideOf($sub['team'] ?? null),
                'player' => (string) ($sub['playerIn']['name'] ?? 'Entrant'),
                'out'    => isset($sub['playerOut']['name']) ? (string) $sub['playerOut']['name'] : null,
            ];
        }
        if (!$events) {
            return [];
        }
        usort($events, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        // Séparateurs « 1re période », « 2e période », « Prolongations »
        $ht = Format::halfTime($m);
        $periods = [
            1 => ['label' => '1re période', 'score' => $ht ? $ht[0] . ' - ' . $ht[1] : null],
            2 => ['label' => '2e période', 'score' => null],
            3 => ['label' => 'Prolongations', 'score' => null],
        ];
        $timeline = [];
        $current = 0;
        foreach ($events as $event) {
            $period = $event['sort'] < 46 ? 1 : ($event['sort'] < 91 ? 2 : 3);
            if ($period !== $current) {
                $current = $period;
                $timeline[] = ['type' => 'period'] + $periods[$period];
            }
            $timeline[] = $event;
        }
        return $timeline;
    }

    private static function minuteText(array $event): string
    {
        $minute = (int) ($event['minute'] ?? 0);
        $injury = (int) ($event['injuryTime'] ?? 0);
        return $minute . ($injury > 0 ? '+' . $injury : '') . '’';
    }

    private static function minuteSort(array $event): float
    {
        return (float) ($event['minute'] ?? 0) + ((int) ($event['injuryTime'] ?? 0)) / 100;
    }

    public static function lineups(array $m): ?array
    {
        $side = static function (mixed $team): array {
            $team = is_array($team) ? $team : [];
            $player = static fn (array $p): array => [
                'number'   => $p['shirtNumber'] ?? null,
                'name'     => (string) ($p['name'] ?? '?'),
                'position' => Format::position($p['position'] ?? null),
            ];
            return [
                'name'      => Format::teamName($team),
                'crest'     => $team['crest'] ?? null,
                'formation' => $team['formation'] ?? null,
                'coach'     => $team['coach']['name'] ?? null,
                'lineup'    => array_map($player, is_array($team['lineup'] ?? null) ? $team['lineup'] : []),
                'bench'     => array_map($player, is_array($team['bench'] ?? null) ? $team['bench'] : []),
            ];
        };
        $home = $side($m['homeTeam'] ?? null);
        $away = $side($m['awayTeam'] ?? null);
        return ($home['lineup'] || $away['lineup']) ? ['home' => $home, 'away' => $away] : null;
    }

    public static function statistics(array $m): array
    {
        $home = $m['homeTeam']['statistics'] ?? null;
        $away = $m['awayTeam']['statistics'] ?? null;
        if (!is_array($home) || !is_array($away)) {
            return [];
        }
        $order = ['ball_possession', 'shots', 'shots_on_goal', 'shots_off_goal', 'corner_kicks', 'fouls', 'offsides',
            'saves', 'free_kicks', 'goal_kicks', 'throw_ins', 'yellow_cards', 'yellow_red_cards', 'red_cards'];
        $rows = [];
        foreach ($order as $key) {
            if (!isset($home[$key]) && !isset($away[$key])) {
                continue;
            }
            $h = (int) ($home[$key] ?? 0);
            $a = (int) ($away[$key] ?? 0);
            $total = $h + $a;
            $rows[] = [
                'label'   => Format::statLabel($key),
                'home'    => $h,
                'away'    => $a,
                'homePct' => $total > 0 ? (int) round($h / $total * 100) : 50,
                'awayPct' => $total > 0 ? 100 - (int) round($h / $total * 100) : 50,
            ];
        }
        return $rows;
    }

    /** @return list<array{0:string,1:string}> */
    public static function info(array $m): array
    {
        $rows = [];
        $kickoff = Format::date($m['utcDate'] ?? null);
        if ($kickoff) {
            $rows[] = ['Coup d’envoi', ucfirst(Format::dayLong($kickoff)) . ' à ' . Format::time($kickoff)];
        }
        $competition = Format::competitionName($m['competition'] ?? null);
        $season = Format::season($m['season'] ?? null);
        if ($competition !== '') {
            $rows[] = ['Compétition', $competition . ($season !== '' ? ' ' . $season : '')];
        }
        $round = self::round($m);
        if ($round !== '') {
            $rows[] = ['Phase', $round];
        }
        $rows[] = ['Statut', Format::statusLabel((string) ($m['status'] ?? ''))];
        if (!empty($m['venue'])) {
            $rows[] = ['Stade', (string) $m['venue']];
        }
        if (!empty($m['attendance'])) {
            $rows[] = ['Affluence', number_format((int) $m['attendance'], 0, ',', ' ') . ' spectateurs'];
        }
        foreach (is_array($m['referees'] ?? null) ? $m['referees'] : [] as $ref) {
            if (in_array($ref['type'] ?? '', ['REFEREE', 'VIDEO_ASSISTANT_REFEREE_N1'], true) && !empty($ref['name'])) {
                $nationality = Format::area($ref['nationality'] ?? null);
                $rows[] = [Format::refereeRole($ref['type']), $ref['name'] . ($nationality !== '' ? ' (' . $nationality . ')' : '')];
            }
        }
        $updated = Format::date($m['lastUpdated'] ?? null);
        if ($updated) {
            $rows[] = ['Données mises à jour', Format::dateMedium($updated) . ' à ' . Format::time($updated)];
        }
        return $rows;
    }

    /** Bilan des confrontations précédentes, du point de vue des équipes du match affiché. */
    public static function headToHead(array $h2h, array $m, array $favTeams = []): ?array
    {
        $currentId = (int) ($m['id'] ?? 0);
        $homeId = (int) ($m['homeTeam']['id'] ?? 0);
        $matches = array_values(array_filter(
            is_array($h2h['matches'] ?? null) ? $h2h['matches'] : [],
            static fn (array $x): bool => (int) ($x['id'] ?? 0) !== $currentId
        ));
        if (!$matches || $homeId === 0) {
            return null;
        }
        usort($matches, static fn (array $a, array $b): int => strcmp((string) ($b['utcDate'] ?? ''), (string) ($a['utcDate'] ?? '')));

        $tally = ['home' => 0, 'draw' => 0, 'away' => 0];
        foreach ($matches as $x) {
            $result = Format::resultFor($x, $homeId);
            if ($result === 'V') {
                $tally['home']++;
            } elseif ($result === 'N') {
                $tally['draw']++;
            } elseif ($result === 'D') {
                $tally['away']++;
            }
        }
        $played = array_sum($tally);

        return [
            'played'  => $played,
            'tally'   => $tally,
            'pct'     => [
                'home' => $played ? (int) round($tally['home'] / $played * 100) : 0,
                'draw' => $played ? (int) round($tally['draw'] / $played * 100) : 0,
                'away' => $played ? (int) round($tally['away'] / $played * 100) : 0,
            ],
            'matches' => array_map(static fn (array $x): array => self::present($x, $favTeams), array_slice($matches, 0, 10)),
        ];
    }
}
