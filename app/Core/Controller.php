<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\FootballData;

/**
 * Classe de base des contrôleurs.
 */
abstract class Controller
{
    protected function render(string $view, array $data = [], int $status = 200): void
    {
        View::render($view, $data, 'layout', $status);
    }

    protected function api(): FootballData
    {
        return FootballData::instance();
    }

    protected function json(array $data, int $status = 200): never
    {
        App::json($data, $status);
    }

    /** Exige un utilisateur connecté (sinon redirection vers la connexion ou JSON 401). */
    protected function requireUser(): array
    {
        $user = Auth::user();
        if ($user !== null) {
            return $user;
        }
        if (Request::wantsJson()) {
            $this->json([
                'ok'      => false,
                'error'   => 'auth',
                'message' => 'Connectez-vous pour gérer vos favoris.',
                'login'   => url('connexion'),
            ], 401);
        }
        Session::flash('info', 'Connectez-vous pour accéder à cette page.');
        redirect(url('connexion', ['retour' => current_url()]));
    }
}
