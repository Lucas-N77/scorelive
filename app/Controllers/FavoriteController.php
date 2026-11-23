<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Competition;
use App\Models\Favorite;
use App\Models\Team;
use App\Services\ApiException;
use App\Services\Format;
use App\Services\MatchFeed;

/**
 * « Mes favoris » et bascule d'un favori (équipe ou compétition).
 */
final class FavoriteController extends Controller
{
    private const MAX_FAVORITES = 200;

    public function index(): void
    {
        $user = $this->requireUser();
        $userId = (int) $user['id'];
        $list = Favorite::listForUser($userId);
        $map = Favorite::mapForUser($userId);

        $groups = [];
        $apiMessage = null;
        $api = $this->api();
        if (!$api->hasKey()) {
            $apiMessage = 'Ajoutez votre clé d’API dans config.php pour voir les matchs du jour de vos favoris.';
        } elseif ($list) {
            try {
                $today = MatchFeed::onDay(MatchFeed::window($api), Format::today());
                $mine = array_values(array_filter($today, static function (array $m) use ($map): bool {
                    $competition = (string) ($m['competition']['code'] ?? ($m['competition']['id'] ?? ''));
                    return isset($map['teams'][(int) ($m['homeTeam']['id'] ?? 0)])
                        || isset($map['teams'][(int) ($m['awayTeam']['id'] ?? 0)])
                        || isset($map['competitions'][$competition]);
                }));
                $groups = MatchFeed::groupByCompetition($mine, $map['teams'], $map['competitions']);
            } catch (ApiException $e) {
                $apiMessage = $e->getMessage();
            }
        }

        $this->render('favorites', [
            'title'        => 'Mes favoris',
            'nav'          => 'favorites',
            'teams'        => array_values(array_filter($list, static fn (array $f): bool => $f['type'] === 'team')),
            'competitions' => array_values(array_filter($list, static fn (array $f): bool => $f['type'] === 'competition')),
            'groups'       => $groups,
            'apiMessage'   => $apiMessage,
        ]);
    }

    /** POST /favoris/basculer : ajoute ou retire un favori (formulaire classique ou fetch JSON). */
    public function toggle(): void
    {
        $user = $this->requireUser();
        $userId = (int) $user['id'];
        $type = Request::input('type');
        $id = trim(Request::input('id'));

        if (!in_array($type, Favorite::TYPES, true)
            || ($type === 'team' && !ctype_digit($id))
            || ($type === 'competition' && !preg_match('/^[A-Za-z0-9]{1,12}$/', $id))) {
            $this->fail('Favori invalide.', 422);
        }

        // Nom et logo proviennent de l'index local, jamais du formulaire.
        if ($type === 'team') {
            $row = Team::find((int) $id);
            $ref = (string) (int) $id;
            $name = $row ? (string) ($row['short_name'] ?: $row['name']) : '';
            $crest = $row['crest'] ?? null;
        } else {
            $row = Competition::findByKey($id);
            $ref = $row ? (string) ($row['code'] ?: $row['id']) : '';
            $name = $row ? Format::competitionName($row) : '';
            $crest = $row['emblem'] ?? null;
        }
        if ($row === null) {
            $this->fail('Élément introuvable : affichez sa page puis réessayez.', 404);
        }

        if (Favorite::exists($userId, $type, $ref)) {
            Favorite::remove($userId, $type, $ref);
            $active = false;
            $message = $name . ' retiré de vos favoris.';
        } else {
            if (Favorite::countForUser($userId) >= self::MAX_FAVORITES) {
                $this->fail('Vous avez atteint la limite de ' . self::MAX_FAVORITES . ' favoris.', 422);
            }
            Favorite::add($userId, $type, $ref, $name, $crest);
            $active = true;
            $message = $name . ' ajouté à vos favoris.';
        }

        if (Request::wantsJson()) {
            $this->json(['ok' => true, 'active' => $active, 'message' => $message]);
        }
        Session::flash('success', $message);
        redirect(Request::back(url('favoris')), 303);
    }

    private function fail(string $message, int $status): never
    {
        if (Request::wantsJson()) {
            $this->json(['ok' => false, 'error' => 'favorite', 'message' => $message], $status);
        }
        Session::flash('error', $message);
        redirect(Request::back(url('favoris')), 303);
    }
}
