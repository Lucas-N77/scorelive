<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\LoginAttempt;
use App\Models\User;
use PDOException;

/**
 * Inscription, connexion et déconnexion.
 */
final class AuthController extends Controller
{
    /** Hash factice : un identifiant inconnu coûte le même temps de calcul qu'un vrai compte. */
    private const DUMMY_HASH = '$2y$10$0dVmzS5J7sVtmfX7UT2wg.GKgyZStE3KHcDZ56oda9n5hcIYrF4X2';

    public function loginForm(): void
    {
        if (Auth::check()) {
            redirect(url());
        }
        $this->render('auth/login', [
            'title'  => 'Connexion',
            'nav'    => 'account',
            'retour' => safe_redirect_target(Request::query('retour'), ''),
        ]);
    }

    public function login(): void
    {
        $identifier = trim(Request::input('identifiant'));
        $password = Request::input('mot_de_passe');
        $retour = safe_redirect_target(Request::input('retour'), '');
        $back = url('connexion', ['retour' => $retour !== '' ? $retour : null]);
        $ip = Request::ip();

        if (LoginAttempt::tooMany($ip)) {
            $minutes = LoginAttempt::retryAfterMinutes($ip);
            Session::flash('error', sprintf(
                'Trop de tentatives de connexion. Réessayez dans %d minute%s.',
                $minutes,
                $minutes > 1 ? 's' : ''
            ));
            Session::flashInput(['identifiant' => $identifier]);
            redirect($back, 303);
        }

        if ($identifier === '' || $password === '') {
            Session::flash('error', 'Renseignez votre e-mail (ou pseudo) et votre mot de passe.');
            Session::flashInput(['identifiant' => $identifier]);
            redirect($back, 303);
        }

        $user = mb_strlen($identifier) <= 190 ? User::findForLogin($identifier) : null;
        $valid = password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH) && $user !== null;

        if (!$valid) {
            LoginAttempt::record($ip, $identifier);
            Session::flash('error', 'Identifiants incorrects.');
            Session::flashInput(['identifiant' => $identifier]);
            redirect($back, 303);
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            User::updatePasswordHash((int) $user['id'], $password);
        }
        LoginAttempt::clear($ip);
        User::touchLogin((int) $user['id']);
        $profile = User::find((int) $user['id']);
        if ($profile === null) {
            Session::flash('error', 'Compte introuvable.');
            redirect($back, 303);
        }
        Auth::login($profile);

        Session::flash('success', 'Bon retour, ' . $user['username'] . ' !');
        redirect($retour !== '' ? $retour : url(), 303);
    }

    public function registerForm(): void
    {
        if (Auth::check()) {
            redirect(url());
        }
        $this->render('auth/register', [
            'title'  => 'Créer un compte',
            'nav'    => 'account',
            'errors' => (array) Session::pull('_form_errors', []),
        ]);
    }

    public function register(): void
    {
        $username = trim(Request::input('pseudo'));
        $email = mb_strtolower(trim(Request::input('email')));
        $password = Request::input('mot_de_passe');
        $confirmation = Request::input('confirmation');

        $errors = [];
        if (!preg_match('/^[\p{L}\p{N}_.\-]{3,30}$/u', $username)) {
            $errors['pseudo'] = 'Le pseudo doit faire de 3 à 30 caractères (lettres, chiffres, point, tiret ou tiret bas).';
        }
        if (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Adresse e-mail invalide.';
        }
        if (mb_strlen($password) < 8) {
            $errors['mot_de_passe'] = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif (mb_strlen($password) > 200) {
            $errors['mot_de_passe'] = 'Le mot de passe est trop long (200 caractères maximum).';
        }
        if ($password !== $confirmation) {
            $errors['confirmation'] = 'Les deux mots de passe ne correspondent pas.';
        }
        if (!isset($errors['pseudo']) && User::usernameExists($username)) {
            $errors['pseudo'] = 'Ce pseudo est déjà utilisé.';
        }
        if (!isset($errors['email']) && User::emailExists($email)) {
            $errors['email'] = 'Un compte existe déjà avec cette adresse e-mail.';
        }

        if (!$errors) {
            try {
                $id = User::create($username, $email, $password);
            } catch (PDOException $e) {
                // Doublon créé entre la vérification et l'insertion
                if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                    throw $e;
                }
                $errors['email'] = 'Ce pseudo ou cette adresse e-mail est déjà utilisé.';
            }
        }

        if ($errors) {
            Session::set('_form_errors', $errors);
            Session::flashInput(['pseudo' => $username, 'email' => $email]);
            redirect(url('inscription'), 303);
        }

        Auth::login(User::find($id));
        Session::flash('success', 'Bienvenue sur ScoreLive, ' . $username . ' ! Touchez l’étoile d’une équipe ou d’une compétition pour la suivre.');
        redirect(url(), 303);
    }

    public function logout(): void
    {
        Auth::logout();
        Session::flash('info', 'Vous êtes déconnecté. À bientôt !');
        redirect(url(), 303);
    }
}
