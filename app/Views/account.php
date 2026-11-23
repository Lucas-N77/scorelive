<?php
/**
 * Mon compte.
 *
 * @var array                  $user
 * @var int                    $favorites
 * @var DateTimeImmutable|null $memberSince
 * @var DateTimeImmutable|null $lastLogin
 * @var bool                   $hasKey
 * @var int|null               $remaining
 * @var int                    $rateLimit
 * @var int                    $cacheTtl
 */

use App\Services\Format;
?>
<section class="hero hero--account">
  <div class="hero__media"><span class="avatar avatar--xl" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $user['username'], 0, 1))) ?></span></div>
  <div class="hero__text">
    <p class="hero__kicker">Mon compte</p>
    <h1 class="hero__title"><?= e($user['username']) ?></h1>
    <p class="hero__meta"><span><?= e($user['email']) ?></span></p>
  </div>
</section>

<div class="grid-2">
  <section class="card">
    <h2 class="card__title">Profil</h2>
    <dl class="info-list">
      <div class="info-list__row"><dt>Pseudo</dt><dd><?= e($user['username']) ?></dd></div>
      <div class="info-list__row"><dt>E-mail</dt><dd><?= e($user['email']) ?></dd></div>
      <?php if ($memberSince): ?>
        <div class="info-list__row"><dt>Membre depuis</dt><dd><?= e(Format::dateMedium($memberSince)) ?></dd></div>
      <?php endif; ?>
      <?php if ($lastLogin): ?>
        <div class="info-list__row"><dt>Dernière connexion</dt><dd><?= e(Format::dateMedium($lastLogin) . ' à ' . Format::time($lastLogin)) ?></dd></div>
      <?php endif; ?>
      <div class="info-list__row"><dt>Favoris</dt><dd><a href="<?= e(url('favoris')) ?>"><?= e(plural($favorites, 'favori')) ?></a></dd></div>
    </dl>
  </section>

  <section class="card">
    <h2 class="card__title">Application</h2>
    <dl class="info-list">
      <div class="info-list__row">
        <dt>Thème</dt>
        <dd><button type="button" class="btn btn--ghost btn--small" data-theme-toggle><?= icon('moon') ?><span>Clair / sombre</span></button></dd>
      </div>
      <div class="info-list__row"><dt>Clé d’API</dt><dd><?= $hasKey ? 'configurée' : 'manquante (config.php)' ?></dd></div>
      <div class="info-list__row"><dt>Cache des scores</dt><dd><?= (int) $cacheTtl ?> secondes</dd></div>
      <?php if ($remaining !== null): ?>
        <div class="info-list__row"><dt>Requêtes API disponibles</dt><dd><?= (int) $remaining ?> / <?= (int) $rateLimit ?> cette minute</dd></div>
      <?php endif; ?>
    </dl>
  </section>
</div>

<form class="account-logout" method="post" action="<?= e(url('deconnexion')) ?>">
  <?= csrf_field() ?>
  <button class="btn btn--danger" type="submit"><?= icon('logout') ?><span>Se déconnecter</span></button>
</form>
