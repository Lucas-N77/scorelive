<?php
/**
 * Accueil : matchs du jour.
 *
 * @var string            $heading
 * @var DateTimeImmutable $day
 * @var string            $dayKey
 * @var array             $days
 * @var array             $filters
 * @var string            $filterState  all | live | finished | upcoming
 * @var array             $groups
 * @var array             $counts
 * @var array             $competitions
 */

use App\Core\View;
use App\Services\Format;

$emptyTexts = [
    'all'      => 'Aucun match de ces compétitions n’est programmé ce jour-là.',
    'live'     => 'Aucun match n’est en cours pour le moment.',
    'finished' => 'Aucun match terminé pour l’instant.',
    'upcoming' => 'Plus aucun match à venir ce jour-là.',
];
$hasMatchesForFilter = $filterState === 'all' ? $counts['all'] > 0 : $counts[$filterState] > 0;
?>
<section class="page-head">
  <div class="page-head__text">
    <h1 class="page-title"><?= e($heading) ?></h1>
    <p class="page-subtitle"><?= e(ucfirst(Format::dayLong($day))) ?></p>
  </div>
  <p class="live-status" data-live-status hidden>
    <span class="live-status__dot" aria-hidden="true"></span>
    <span data-live-status-text>Actualisation automatique</span>
  </p>
</section>

<nav class="day-switch" aria-label="Choisir le jour">
  <?php foreach ($days as $d): ?>
    <a class="day-switch__item<?= $d['active'] ? ' is-active' : '' ?>" href="<?= e($d['url']) ?>"<?= $d['active'] ? ' aria-current="page"' : '' ?>>
      <span class="day-switch__label"><?= e($d['label']) ?></span>
      <span class="day-switch__date"><?= e($d['date']) ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<div class="filters" data-filters>
  <?php foreach ($filters as $f): ?>
    <a class="chip<?= $f['active'] ? ' is-active' : '' ?><?= $f['state'] === 'live' ? ' chip--live' : '' ?>"
       href="<?= e($f['url']) ?>" data-filter="<?= e($f['state']) ?>" data-filter-key="<?= e($f['key']) ?>"
       aria-pressed="<?= $f['active'] ? 'true' : 'false' ?>">
      <?php if ($f['state'] === 'live'): ?><span class="chip__dot" aria-hidden="true"></span><?php endif; ?>
      <span><?= e($f['label']) ?></span>
      <span class="chip__count" data-count="<?= e($f['state']) ?>"><?= (int) $counts[$f['state']] ?></span>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($competitions): ?>
  <nav class="comp-strip" aria-label="Accès rapide aux compétitions">
    <?php foreach ($competitions as $c): ?>
      <a class="comp-strip__item" href="<?= e(url('competition/' . $c['code'])) ?>">
        <?= crest($c['emblem'], 18, 'crest crest--sm crest--emblem') ?>
        <span><?= e(Format::competitionName($c)) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<div class="match-board" data-match-board data-filter-state="<?= e($filterState) ?>"
     data-empty-texts="<?= e(json_encode($emptyTexts, JSON_UNESCAPED_UNICODE)) ?>">
  <?php if (!$groups): ?>
    <?php View::partial('empty', [
        'title'  => $dayKey === 'aujourdhui' ? 'Pas de match aujourd’hui' : 'Pas de match ce jour-là',
        'text'   => 'Aucun match des 12 compétitions de l’offre gratuite n’est programmé. Consultez un autre jour ou les classements.',
        'icon'   => 'calendar',
        'action' => $dayKey === 'demain' ? ['Voir les matchs d’hier', url('', ['jour' => 'hier'])] : ['Voir les matchs de demain', url('', ['jour' => 'demain'])],
    ]); ?>
  <?php else: ?>
    <?php foreach ($groups as $group): ?>
      <?php View::partial('competition-group', ['group' => $group]); ?>
    <?php endforeach; ?>

    <div class="empty empty--filter" data-filter-empty<?= $hasMatchesForFilter ? ' hidden' : '' ?>>
      <div class="empty__icon"><?= icon('clock') ?></div>
      <p class="empty__title" data-filter-empty-text><?= e($emptyTexts[$filterState]) ?></p>
      <a class="btn btn--ghost btn--small" href="<?= e(url('', ['jour' => $dayKey === 'aujourdhui' ? null : $dayKey])) ?>" data-filter-reset>Voir tous les matchs</a>
    </div>
  <?php endif; ?>
</div>
