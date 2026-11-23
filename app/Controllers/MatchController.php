<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Favorite;
use App\Services\ApiException;
use App\Services\Format;
use App\Services\MatchPresenter;

/**
 * Page d'un match : score, buteurs, statut, compositions, statistiques, face-à-face.
 */
final class MatchController extends Controller
{
    public function show(string $id): void
    {
        $api = $this->api();
        $match = $api->match((int) $id);

        // Face-à-face : facultatif, la page s'affiche même si cette requête échoue.
        $h2h = null;
        try {
            $h2h = $api->headToHead((int) $id, 10);
        } catch (ApiException $e) {
            if (in_array($e->kind, [ApiException::MISSING_KEY, ApiException::INVALID_KEY], true)) {
                throw $e;
            }
        }

        $favorites = Favorite::forCurrentUser();
        $vm = MatchPresenter::present($match, $favorites['teams']);
        $hasDetails = isset($match['goals']) || isset($match['bookings']) || isset($match['substitutions']);

        $this->render('match', [
            'title'       => $vm['home']['name'] . ' - ' . $vm['away']['name'],
            'description' => sprintf('%s - %s : score, buteurs et infos du match (%s).', $vm['home']['full'], $vm['away']['full'], $vm['competition']['name']),
            'nav'         => 'home',
            'm'           => $vm,
            'round'       => MatchPresenter::round($match),
            'events'      => MatchPresenter::events($match),
            'hasDetails'  => $hasDetails,
            'lineups'     => MatchPresenter::lineups($match),
            'stats'       => MatchPresenter::statistics($match),
            'info'        => MatchPresenter::info($match),
            'h2h'         => $h2h ? MatchPresenter::headToHead($h2h, $match, $favorites['teams']) : null,
            'favorites'   => $favorites,
            'kickoff'     => Format::date($match['utcDate'] ?? null),
        ]);
    }
}
