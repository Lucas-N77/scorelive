<?php
/**
 * Gabarit commun à toutes les pages.
 *
 * @var string      $content
 * @var string|null $title
 * @var string|null $description
 * @var string|null $nav
 * @var bool|null   $minimal   true : pages d'erreur/installation (aucun accès base de données)
 */

use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Models\Competition;
use App\Models\Favorite;
use App\Services\FootballData;

$minimal = !empty($minimal);
$pageTitle = !empty($title) ? $title . ' · ScoreLive' : 'ScoreLive · Scores de foot en direct';
$pageDescription = $description ?? 'ScoreLive : résultats et scores de football en direct, classements, calendriers et favoris.';
$nav = $nav ?? '';

$user = null;
$competitions = [];
$favoriteCompetitions = [];
if (!$minimal) {
    try {
        $user = Auth::user();
        $competitions = Competition::accessible();
        $favoriteCompetitions = Favorite::forCurrentUser()['competitions'];
    } catch (Throwable) {
        // La page reste affichable même si la base est momentanément indisponible.
    }
}
$flashes = session_status() === PHP_SESSION_ACTIVE ? Session::takeFlashes() : [];
$stale = FootballData::booted() ? FootballData::instance()->staleNotices() : [];
?><!doctype html>
<html lang="fr" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDescription) ?>">
<meta name="theme-color" content="#0a0f16">
<meta name="color-scheme" content="dark light">
<meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="ScoreLive">
<link rel="manifest" href="<?= e(public_url('manifest.json')) ?>">
<link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(asset('assets/img/apple-touch-icon.png')) ?>">
<script nonce="<?= e(App::nonce()) ?>">
(function () {
  try {
    var t = localStorage.getItem('scorelive-theme');
    if (t === 'light' || t === 'dark') { document.documentElement.setAttribute('data-theme', t); }
  } catch (e) {}
})();
</script>
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</head>
<body class="<?= $minimal ? 'is-minimal' : '' ?>"
      data-base="<?= e(App::basePath()) ?>"
      data-live-url="<?= e(url('api/live')) ?>"
      data-search-url="<?= e(url('api/recherche')) ?>"
      data-sw-url="<?= e(public_url('sw.js')) ?>"
      data-scope="<?= e(App::basePath() . '/') ?>">
<a class="skip-link" href="#main">Aller au contenu</a>

<?php View::partial('header', ['nav' => $nav, 'user' => $user, 'minimal' => $minimal]); ?>

<div class="shell<?= $minimal ? ' shell--minimal' : '' ?>">
  <?php if (!$minimal): ?>
    <?php View::partial('sidebar', [
        'competitions' => $competitions,
        'favorites'    => $favoriteCompetitions,
        'active'       => $navCompetition ?? null,
    ]); ?>
  <?php endif; ?>

  <main id="main" class="main" tabindex="-1">
    <?php View::partial('notices', ['flashes' => $flashes, 'stale' => $stale]); ?>
    <?= $content ?>
  </main>
</div>

<?php if (!$minimal): ?>
  <?php View::partial('tabbar', ['nav' => $nav, 'user' => $user]); ?>
<?php endif; ?>

<footer class="site-footer">
  <p>ScoreLive · Données fournies par <a href="https://www.football-data.org" target="_blank" rel="noopener noreferrer">football-data.org</a></p>
</footer>

<div class="toast-region" data-toasts aria-live="polite" aria-atomic="true"></div>
</body>
</html>
