<?php
/**
 * Onglets en bas de l'écran (mobile).
 *
 * @var string     $nav
 * @var array|null $user
 */
$tabs = [
    'home'      => ['Matchs', url(), 'ball'],
    'live'      => ['Direct', url('', ['filtre' => 'direct']), 'live'],
    'favorites' => ['Favoris', url('favoris'), 'star'],
    'search'    => ['Recherche', url('recherche'), 'search'],
    'account'   => [$user ? 'Compte' : 'Connexion', $user ? url('compte') : url('connexion'), 'user'],
];
?>
<nav class="tabbar" aria-label="Navigation">
  <?php foreach ($tabs as $key => [$label, $href, $iconName]): ?>
    <a class="tabbar__item<?= $nav === $key ? ' is-active' : '' ?><?= $key === 'live' ? ' tabbar__item--live' : '' ?>"
       href="<?= e($href) ?>"<?= $nav === $key ? ' aria-current="page"' : '' ?>>
      <?= icon($iconName) ?>
      <span class="tabbar__label"><?= e($label) ?></span>
    </a>
  <?php endforeach; ?>
</nav>
