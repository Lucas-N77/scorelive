<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Competition;
use App\Models\Team;
use App\Services\ApiException;
use App\Services\SearchService;

/**
 * Recherche d'équipes et de compétitions.
 */
final class SearchController extends Controller
{
    public function index(): void
    {
        $api = $this->api();
        if (!$api->hasKey()) {
            throw ApiException::missingKey();
        }

        $q = mb_substr(Request::query('q'), 0, 60);

        // Complète l'index des équipes au fil des recherches (si le quota le permet).
        SearchService::growIndex($api);

        $results = SearchService::search($q);
        [$synced, $total] = Competition::syncProgress();

        $this->render('search', [
            'title'        => $q !== '' ? 'Recherche « ' . $q . ' »' : 'Recherche',
            'nav'          => 'search',
            'q'            => $q,
            'results'      => $results,
            'synced'       => $synced,
            'total'        => $total,
            'teamCount'    => Team::count(),
            'competitions' => Competition::accessible(),
        ]);
    }
}
