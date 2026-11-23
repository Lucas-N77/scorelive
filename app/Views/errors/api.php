<?php
/**
 * Erreur de l'API football-data.org (clé invalide, quota, hors abonnement, panne).
 *
 * @var App\Services\ApiException $error
 */

use App\Services\ApiException;

$icons = [
    ApiException::INVALID_KEY => 'key',
    ApiException::QUOTA       => 'clock',
    ApiException::RESTRICTED  => 'shield',
    ApiException::NETWORK     => 'globe',
];
?>
<section class="state-page card">
  <div class="state-page__icon state-page__icon--<?= e($error->kind) ?>"><?= icon($icons[$error->kind] ?? 'alert') ?></div>
  <h1 class="state-page__title"><?= e($error->title()) ?></h1>
  <p class="state-page__text"><?= e($error->getMessage()) ?></p>

  <?php if ($error->kind === ApiException::INVALID_KEY): ?>
    <p class="state-page__text">Fichier à vérifier : <code class="path"><?= e(ROOT_PATH . '/config.php') ?></code>
      → <code>'api' => ['key' => '…']</code></p>
  <?php elseif ($error->kind === ApiException::QUOTA): ?>
    <p class="state-page__text muted">Rechargement automatique dans
      <strong data-countdown="<?= (int) $error->retryAfter ?>"><?= (int) $error->retryAfter ?></strong> s.</p>
  <?php elseif ($error->kind === ApiException::RESTRICTED): ?>
    <p class="state-page__text muted">Les 12 compétitions gratuites sont listées dans le menu « Compétitions ».</p>
  <?php endif; ?>

  <?php if ($error->apiMessage): ?>
    <p class="state-page__detail">Réponse de l’API : <?= e($error->apiMessage) ?></p>
  <?php endif; ?>

  <p class="state-page__actions">
    <a class="btn btn--primary" href="<?= e(current_url()) ?>"><?= icon('refresh') ?><span>Réessayer</span></a>
    <a class="btn btn--ghost" href="<?= e(url()) ?>">Accueil</a>
  </p>
</section>
