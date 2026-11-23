<?php
/**
 * Erreur interne.
 *
 * @var Throwable|null $exception  fourni uniquement si app.debug = true
 */
?>
<section class="state-page card">
  <div class="state-page__icon"><?= icon('alert') ?></div>
  <p class="state-page__code">500</p>
  <h1 class="state-page__title">Une erreur interne est survenue</h1>
  <p class="state-page__text">Le détail a été enregistré dans <code>storage/logs/php-error.log</code>.</p>
  <?php if (!empty($exception)): ?>
    <pre class="code code--error"><?= e($exception::class . ': ' . $exception->getMessage() . "\n" . $exception->getFile() . ':' . $exception->getLine() . "\n\n" . $exception->getTraceAsString()) ?></pre>
  <?php endif; ?>
  <p class="state-page__actions"><a class="btn btn--primary" href="<?= e(url()) ?>">Retour à l’accueil</a></p>
</section>
