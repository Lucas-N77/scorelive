<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Competition;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Flux partagé des matchs d'hier, d'aujourd'hui et de demain.
 *
 * Une seule requête API (mise en cache 30 s) couvre les trois jours, pour tous les visiteurs :
 * l'accueil, les favoris et le rafraîchissement en direct lisent tous la même réponse.
 */
final class MatchFeed
{
    public static function window(FootballData $api): array
    {
        $utc = new DateTimeZone('UTC');
        $today = Format::today();
        // Bornes locales [hier 00:00 ; après-demain 00:00[ converties en dates UTC
        // (dateTo est traité comme exclusif par l'API : on prend un jour de marge).
        $from = $today->modify('-1 day')->setTimezone($utc)->format('Y-m-d');
        $to = $today->modify('+2 days')->setTimezone($utc)->modify('+1 day')->format('Y-m-d');

        $data = $api->matchesBetween($from, $to);
        return is_array($data['matches'] ?? null) ? $data['matches'] : [];
    }

    /** Matchs dont le coup d'envoi tombe le jour local $day. */
    public static function onDay(array $matches, DateTimeImmutable $day): array
    {
        $key = $day->format('Y-m-d');
        return array_values(array_filter($matches, static function (array $m) use ($key): bool {
            $date = Format::date($m['utcDate'] ?? null);
            return $date !== null && $date->format('Y-m-d') === $key;
        }));
    }

    /**
     * Regroupe par compétition : favorites d'abord, puis ordre de priorité, puis nom.
     *
     * @param array<int,true>    $favTeams
     * @param array<string,true> $favCompetitions
     */
    public static function groupByCompetition(array $matches, array $favTeams = [], array $favCompetitions = []): array
    {
        $groups = [];
        foreach ($matches as $m) {
            $competition = is_array($m['competition'] ?? null) ? $m['competition'] : [];
            $key = (string) ($competition['code'] ?? ($competition['id'] ?? 'autres'));
            if (!isset($groups[$key])) {
                $area = is_array($m['area'] ?? null) ? $m['area'] : [];
                $groups[$key] = [
                    'key'      => $key,
                    'name'     => Format::competitionName($competition) ?: 'Autres matchs',
                    'emblem'   => $competition['emblem'] ?? null,
                    'area'     => Format::area($area['name'] ?? null),
                    'flag'     => $area['flag'] ?? null,
                    'url'      => $key !== 'autres' ? url('competition/' . $key) : null,
                    'favorite' => isset($favCompetitions[$key]),
                    'matches'  => [],
                    'counts'   => ['live' => 0, 'finished' => 0, 'upcoming' => 0, 'off' => 0],
                ];
            }
            $vm = MatchPresenter::present($m, $favTeams);
            $groups[$key]['matches'][] = $vm;
            $groups[$key]['counts'][$vm['state']]++;
        }

        foreach ($groups as &$group) {
            usort($group['matches'], [self::class, 'compareKickoff']);
        }
        unset($group);

        uasort($groups, static function (array $a, array $b): int {
            return [!$a['favorite'], Competition::rank($a['key']), $a['name']]
               <=> [!$b['favorite'], Competition::rank($b['key']), $b['name']];
        });
        return array_values($groups);
    }

    /** Totaux par état pour les filtres. */
    public static function counts(array $groups): array
    {
        $counts = ['all' => 0, 'live' => 0, 'finished' => 0, 'upcoming' => 0, 'off' => 0];
        foreach ($groups as $group) {
            foreach ($group['counts'] as $state => $n) {
                $counts[$state] += $n;
                $counts['all'] += $n;
            }
        }
        return $counts;
    }

    public static function compareKickoff(array $a, array $b): int
    {
        return [$a['kickoff']?->getTimestamp() ?? 0, $a['home']['name']] <=> [$b['kickoff']?->getTimestamp() ?? 0, $b['home']['name']];
    }

    /** Regroupe des matchs présentés par jour local (pages compétition et équipe). */
    public static function groupByDay(array $presented): array
    {
        $days = [];
        foreach ($presented as $vm) {
            $key = $vm['kickoff'] ? $vm['kickoff']->format('Y-m-d') : 'tbd';
            $days[$key] ??= [
                'label'   => $vm['kickoff'] ? Format::relativeDay($vm['kickoff']) : 'Date à définir',
                'long'    => $vm['kickoff'] ? ucfirst(Format::dayLong($vm['kickoff'])) : 'Date à définir',
                'matches' => [],
            ];
            $days[$key]['matches'][] = $vm;
        }
        ksort($days);
        return array_values($days);
    }
}
