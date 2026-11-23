<?php
/**
 * Page introuvable.
 *
 * @var string|null $message
 */
?>
<section class="state-page card">
  <div class="state-page__icon"><?= icon('search') ?></div>
  <p class="state-page__code">404</p>
  <h1 class="state-page__title">Hors-jeu !</h1>
  <p class="state-page__text"><?= e($message ?? 'La page demandée n’existe pas.') ?></p>
  <p class="state-page__actions">
    <a class="btn btn--primary" href="<?= e(url()) ?>">Matchs du jour</a>
    <a class="btn btn--ghost" href="<?= e(url('recherche')) ?>">Rechercher</a>
  </p>
</section>
