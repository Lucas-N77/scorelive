<?php
/**
 * Base de données injoignable ou non importée.
 *
 * @var Throwable|null $exception  fourni uniquement si app.debug = true
 */
?>
<section class="state-page card">
  <div class="state-page__icon"><?= icon('alert') ?></div>
  <h1 class="state-page__title">Base de données indisponible</h1>
  <p class="state-page__text">ScoreLive n’arrive pas à utiliser la base MySQL « scorelive ».</p>
  <ol class="state-page__list">
    <li>Vérifiez que MySQL est démarré dans MAMP (voyant vert).</li>
    <li>Importez le schéma : <code>database.sql</code> (phpMyAdmin › Importer, ou la commande du README).</li>
    <li>Contrôlez les accès dans <code>config.php</code> (hôte 127.0.0.1, port 8889, root / root).</li>
  </ol>
  <?php if (!empty($exception)): ?>
    <pre class="code code--error"><?= e($exception->getMessage()) ?></pre>
  <?php endif; ?>
  <p class="state-page__actions"><a class="btn btn--primary" href="<?= e(current_url()) ?>"><?= icon('refresh') ?><span>Réessayer</span></a></p>
</section>
