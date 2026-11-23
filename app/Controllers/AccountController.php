<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Favorite;
use App\Services\Format;
use Throwable;

/**
 * Espace « Mon compte ».
 */
final class AccountController extends Controller
{
    public function index(): void
    {
        $user = $this->requireUser();
        $api = $this->api();

        $remaining = null;
        try {
            $remaining = $api->hasKey() ? $api->remainingSlots() : null;
        } catch (Throwable) {
            $remaining = null;
        }

        $this->render('account', [
            'title'      => 'Mon compte',
            'nav'        => 'account',
            'user'       => $user,
            'favorites'  => Favorite::countForUser((int) $user['id']),
            'memberSince' => Format::date(str_replace(' ', 'T', (string) $user['created_at']) . 'Z'),
            'lastLogin'  => $user['last_login_at'] ? Format::date(str_replace(' ', 'T', (string) $user['last_login_at']) . 'Z') : null,
            'hasKey'     => $api->hasKey(),
            'remaining'  => $remaining,
            'rateLimit'  => (int) config('api.rate_limit', 10),
            'cacheTtl'   => $api->liveTtl(),
        ]);
    }
}
