<?php
/**
 * Erreur HTTP générique (403, 405…).
 *
 * @var int         $status
 * @var string|null $message
 */
?>
<section class="state-page card">
  <div class="state-page__icon"><?= icon('alert') ?></div>
  <p class="state-page__code"><?= (int) $status ?></p>
  <h1 class="state-page__title">Action impossible</h1>
  <p class="state-page__text"><?= e($message ?: 'La requête n’a pas pu aboutir.') ?></p>
  <p class="state-page__actions"><a class="btn btn--primary" href="<?= e(url()) ?>">Retour à l’accueil</a></p>
</section>
