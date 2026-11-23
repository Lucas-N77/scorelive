<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Competition;
use App\Models\Favorite;
use App\Services\Format;
use App\Services\MatchFeed;

/**
 * Accueil : matchs d'hier, d'aujourd'hui ou de demain, groupés par compétition.
 */
final class HomeController extends Controller
{
    private const DAYS = ['hier' => -1, 'aujourdhui' => 0, 'demain' => 1];
    private const FILTERS = ['tous' => 'all', 'direct' => 'live', 'termines' => 'finished', 'avenir' => 'upcoming'];

    public function index(): void
    {
        $dayKey = Request::query('jour', 'aujourdhui');
        if (!isset(self::DAYS[$dayKey])) {
            $dayKey = 'aujourdhui';
        }
        $filterKey = Request::query('filtre', 'tous');
        if (!isset(self::FILTERS[$filterKey])) {
            $filterKey = 'tous';
        }

        $today = Format::today();
        $day = $today->modify(self::DAYS[$dayKey] . ' day');

        $favorites = Favorite::forCurrentUser();
        $matches = MatchFeed::onDay(MatchFeed::window($this->api()), $day);
        $groups = MatchFeed::groupByCompetition($matches, $favorites['teams'], $favorites['competitions']);

        $days = [];
        foreach (self::DAYS as $key => $offset) {
            $date = $today->modify($offset . ' day');
            $days[] = [
                'key'    => $key,
                'label'  => ['hier' => 'Hier', 'aujourdhui' => 'Aujourd’hui', 'demain' => 'Demain'][$key],
                'date'   => Format::dayShort($date),
                'url'    => url('', ['jour' => $key === 'aujourdhui' ? null : $key, 'filtre' => $filterKey === 'tous' ? null : $filterKey]),
                'active' => $key === $dayKey,
            ];
        }

        $filters = [];
        foreach (self::FILTERS as $key => $state) {
            $filters[] = [
                'key'    => $key,
                'state'  => $state,
                'label'  => ['tous' => 'Tous', 'direct' => 'En direct', 'termines' => 'Terminés', 'avenir' => 'À venir'][$key],
                'url'    => url('', ['jour' => $dayKey === 'aujourdhui' ? null : $dayKey, 'filtre' => $key === 'tous' ? null : $key]),
                'active' => $key === $filterKey,
            ];
        }

        $titles = ['hier' => 'Matchs d’hier', 'aujourdhui' => 'Matchs du jour', 'demain' => 'Matchs de demain'];

        $this->render('home', [
            'title'        => $titles[$dayKey],
            'nav'          => $filterKey === 'direct' && $dayKey === 'aujourdhui' ? 'live' : 'home',
            'heading'      => $titles[$dayKey],
            'day'          => $day,
            'dayKey'       => $dayKey,
            'days'         => $days,
            'filters'      => $filters,
            'filterState'  => self::FILTERS[$filterKey],
            'groups'       => $groups,
            'counts'       => MatchFeed::counts($groups),
            'competitions' => Competition::accessible(),
        ]);
    }
}
