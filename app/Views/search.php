<?php
/**
 * Recherche d'équipes et de compétitions.
 *
 * @var string $q
 * @var array  $results
 * @var int    $synced
 * @var int    $total
 * @var int    $teamCount
 * @var array  $competitions
 */

use App\Services\Format;

$hasQuery = mb_strlen($q) >= 2;
$noResults = $hasQuery && !$results['competitions'] && !$results['teams'];
?>
<section class="page-head">
  <div class="page-head__text">
    <h1 class="page-title">Recherche</h1>
    <p class="page-subtitle">Équipes et compétitions</p>
  </div>
</section>

<form class="search-box" action="<?= e(url('recherche')) ?>" method="get" role="search" data-search-form>
  <?= icon('search', 'search-box__icon') ?>
  <input class="search-box__input" type="search" name="q" value="<?= e($q) ?>" placeholder="PSG, Arsenal, Ligue 1…"
         aria-label="Rechercher une équipe ou une compétition" autocomplete="off" maxlength="60" data-search-input
         <?= $hasQuery ? '' : 'autofocus' ?>>
  <button class="btn btn--primary search-box__submit" type="submit">Chercher</button>
</form>

<div class="search-results" data-search-results aria-live="polite">
  <?php if ($noResults): ?>
    <div class="empty">
      <div class="empty__icon"><?= icon('search') ?></div>
      <p class="empty__title">Aucun résultat pour « <?= e($q) ?> »</p>
      <p class="empty__text">Vérifiez l’orthographe ou essayez le nom court (« Barça », « Man City »…).</p>
    </div>
  <?php endif; ?>

  <?php if ($results['competitions']): ?>
    <h2 class="section-title"><?= icon('trophy') ?> Compétitions</h2>
    <ul class="result-list card card--flush">
      <?php foreach ($results['competitions'] as $c): ?>
        <li>
          <a class="result" href="<?= e($c['url']) ?>">
            <?= crest($c['emblem'], 28, 'crest crest--md crest--emblem') ?>
            <span class="result__text">
              <span class="result__name"><?= e($c['display_name']) ?></span>
              <span class="result__meta"><?= e($c['area']) ?></span>
            </span>
            <?= icon('right', 'result__chevron') ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($results['teams']): ?>
    <h2 class="section-title"><?= icon('shield') ?> Équipes</h2>
    <ul class="result-list card card--flush">
      <?php foreach ($results['teams'] as $t): ?>
        <li>
          <a class="result" href="<?= e($t['url']) ?>">
            <?= crest($t['crest'], 28, 'crest crest--md') ?>
            <span class="result__text">
              <span class="result__name"><?= e($t['name']) ?></span>
              <span class="result__meta"><?= e($t['area']) ?></span>
            </span>
            <?= icon('right', 'result__chevron') ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if (!$hasQuery): ?>
    <h2 class="section-title"><?= icon('trophy') ?> Toutes les compétitions</h2>
    <ul class="result-list card card--flush">
      <?php foreach ($competitions as $c): ?>
        <li>
          <a class="result" href="<?= e(url('competition/' . $c['code'])) ?>">
            <?= crest($c['emblem'], 28, 'crest crest--md crest--emblem') ?>
            <span class="result__text">
              <span class="result__name"><?= e(Format::competitionName($c)) ?></span>
              <span class="result__meta"><?= e(Format::area($c['area_name'])) ?></span>
            </span>
            <?= icon('right', 'result__chevron') ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<p class="search-index muted">
  <?= icon('info') ?>
  Index local : <?= e(plural($teamCount, 'équipe')) ?>, <?= (int) $synced ?>/<?= (int) $total ?> compétitions complètes.
  Il se remplit automatiquement au fil de la navigation.
</p>
