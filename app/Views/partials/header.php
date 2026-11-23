<?php
/**
 * En-tête : logo, menu (ordinateur), recherche, thème, compte.
 *
 * @var string     $nav
 * @var array|null $user
 * @var bool       $minimal
 */

use App\Core\View;

$links = [
    'home'      => ['Matchs', url(), 'ball'],
    'live'      => ['En direct', url('', ['filtre' => 'direct']), 'live'],
    'favorites' => ['Mes favoris', url('favoris'), 'star'],
    'search'    => ['Recherche', url('recherche'), 'search'],
];
?>
<header class="topbar">
  <div class="topbar__inner">
    <a class="brand" href="<?= e(url()) ?>" aria-label="ScoreLive, accueil">
      <?php View::partial('brand-mark', ['size' => 34, 'class' => 'brand__mark']); ?>
      <span class="brand__name">Score<span class="brand__accent">Live</span></span>
    </a>

    <?php if (!$minimal): ?>
      <nav class="topnav" aria-label="Navigation principale">
        <?php foreach ($links as $key => [$label, $href, $iconName]): ?>
          <a class="topnav__link<?= $nav === $key ? ' is-active' : '' ?><?= $key === 'live' ? ' topnav__link--live' : '' ?>"
             href="<?= e($href) ?>"<?= $nav === $key ? ' aria-current="page"' : '' ?>>
            <?= icon($iconName) ?><span><?= e($label) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>

      <form class="topsearch" action="<?= e(url('recherche')) ?>" method="get" role="search">
        <?= icon('search', 'topsearch__icon') ?>
        <input class="topsearch__input" type="search" name="q" placeholder="Équipe, compétition…"
               aria-label="Rechercher une équipe ou une compétition" autocomplete="off" maxlength="60">
      </form>
    <?php endif; ?>

    <div class="topbar__actions">
      <?php if (!$minimal): ?>
        <a class="icon-btn topbar__search-btn" href="<?= e(url('recherche')) ?>" aria-label="Rechercher">
          <?= icon('search') ?>
        </a>
      <?php endif; ?>
      <button type="button" class="icon-btn theme-toggle" data-theme-toggle
              aria-label="Passer au thème clair" title="Thème clair / sombre">
        <?= icon('sun', 'theme-toggle__sun') ?><?= icon('moon', 'theme-toggle__moon') ?>
      </button>
      <?php if ($user): ?>
        <a class="account-chip" href="<?= e(url('compte')) ?>" title="Mon compte">
          <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $user['username'], 0, 1))) ?></span>
          <span class="account-chip__name"><?= e($user['username']) ?></span>
        </a>
      <?php elseif (!$minimal): ?>
        <a class="btn btn--primary btn--small topbar__login" href="<?= e(url('connexion')) ?>">Connexion</a>
      <?php endif; ?>
    </div>
  </div>
</header>
