<?php
/**
 * Table de routage : méthode HTTP + chemin → contrôleur.
 *
 * @var App\Core\Router $router
 */
declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\CompetitionController;
use App\Controllers\FavoriteController;
use App\Controllers\HomeController;
use App\Controllers\MatchController;
use App\Controllers\SearchController;
use App\Controllers\TeamController;

// Pages
$router->get('/', [HomeController::class, 'index']);
$router->get('/match/{id:\d+}', [MatchController::class, 'show']);
$router->get('/competition/{code:[A-Za-z0-9]+}', [CompetitionController::class, 'show']);
$router->get('/equipe/{id:\d+}', [TeamController::class, 'show']);
$router->get('/recherche', [SearchController::class, 'index']);

// Comptes
$router->get('/connexion', [AuthController::class, 'loginForm']);
$router->post('/connexion', [AuthController::class, 'login']);
$router->get('/inscription', [AuthController::class, 'registerForm']);
$router->post('/inscription', [AuthController::class, 'register']);
$router->post('/deconnexion', [AuthController::class, 'logout']);
$router->get('/compte', [AccountController::class, 'index']);

// Favoris
$router->get('/favoris', [FavoriteController::class, 'index']);
$router->post('/favoris/basculer', [FavoriteController::class, 'toggle']);

// Endpoints JSON
$router->get('/api/live', [ApiController::class, 'live']);
$router->get('/api/recherche', [ApiController::class, 'search']);
