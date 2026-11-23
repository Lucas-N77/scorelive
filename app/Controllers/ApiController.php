<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\ApiException;
use App\Services\Format;
use App\Services\MatchFeed;
use App\Services\MatchPresenter;
use App\Services\SearchService;

/**
 * Endpoints JSON appelés en fetch() par le navigateur.
 */
final class ApiController extends Controller
{
    /**
     * Scores en direct : GET /api/live?ids=1,2,3
     * Lit le flux partagé (hier → demain, cache 30 s) : aucun appel API supplémentaire par visiteur.
     */
    public function live(): void
    {
        $api = $this->api();
        if (!$api->hasKey()) {
            throw ApiException::missingKey();
        }

        $ids = array_slice(array_filter(array_map('intval', explode(',', Request::query('ids')))), 0, 300);
        $wanted = $ids ? array_flip($ids) : null;

        $matches = [];
        $liveCount = 0;
        foreach (MatchFeed::window($api) as $m) {
            $id = (int) ($m['id'] ?? 0);
            $state = Format::state((string) ($m['status'] ?? ''));
            if ($state === 'live') {
                $liveCount++;
            }
            if ($wanted !== null && !isset($wanted[$id])) {
                continue;
            }
            $matches[(string) $id] = MatchPresenter::live(MatchPresenter::present($m));
        }

        $stale = $api->staleNotices();
        $this->json([
            'ok'         => true,
            'serverTime' => gmdate('Y-m-d\TH:i:s\Z'),
            'refresh'    => $api->liveTtl(),
            'liveCount'  => $liveCount,
            'stale'      => $stale !== [],
            'staleAge'   => $stale ? max(array_column($stale, 'age')) : 0,
            'matches'    => (object) $matches,
        ]);
    }

    /** Recherche instantanée dans l'index local : GET /api/recherche?q=psg */
    public function search(): void
    {
        $q = Request::query('q');
        if (mb_strlen($q) < 2) {
            $this->json(['ok' => true, 'q' => $q, 'competitions' => [], 'teams' => []]);
        }
        $results = SearchService::search($q, 8, 12);

        $this->json([
            'ok'           => true,
            'q'            => $q,
            'competitions' => array_map(static fn (array $c): array => [
                'name'   => $c['display_name'],
                'area'   => $c['area'],
                'emblem' => $c['emblem'],
                'url'    => $c['url'],
            ], $results['competitions']),
            'teams'        => array_map(static fn (array $t): array => [
                'name'   => $t['name'],
                'area'   => $t['area'],
                'crest'  => $t['crest'],
                'url'    => $t['url'],
            ], $results['teams']),
        ]);
    }
}
