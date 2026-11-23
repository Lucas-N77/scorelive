<?php
/**
 * Ligne de match (accueil, compétition, équipe, favoris).
 * Les attributs data-* permettent la mise à jour en direct par assets/js/app.js.
 *
 * @var array     $m         match présenté (MatchPresenter::present)
 * @var bool|null $showDate  affiche la date courte (pages équipe)
 * @var bool|null $showComp  affiche la compétition sous les équipes
 * @var string|null $result  V/N/D du point de vue d'une équipe
 */

use App\Services\Format;

$showDate = !empty($showDate);
$showYear = !empty($showYear);
$showComp = !empty($showComp);
$result = $result ?? ($m['result'] ?? null);
$resultLabels = ['V' => 'Victoire', 'N' => 'Match nul', 'D' => 'Défaite'];
?>
<a class="match match--<?= e($m['state']) ?><?= $m['favorite'] ? ' is-fav' : '' ?>"
   href="<?= e($m['url']) ?>"
   data-match-id="<?= (int) $m['id'] ?>"
   data-state="<?= e($m['state']) ?>"
   data-kickoff="<?= e($m['kickoff_iso']) ?>">
  <span class="match__clock">
    <?php if ($showDate && $m['kickoff']): ?>
      <span class="match__date"><?= e($showYear ? $m['kickoff']->format('d/m/y') : Format::dayMonth($m['kickoff'])) ?></span>
    <?php endif; ?>
    <span class="match__time<?= $m['tick'] ? ' has-tick' : '' ?>" data-el="clock"><?= e($m['clock']) ?></span>
    <span class="live-badge" data-el="badge"<?= $m['state'] === 'live' ? '' : ' hidden' ?>>Live</span>
    <span class="match__note" data-el="note"><?= e($m['note']) ?></span>
  </span>

  <span class="match__teams">
    <?php foreach (['home', 'away'] as $side): $team = $m[$side]; ?>
      <span class="match__team<?= $team['winner'] ? ' is-winner' : '' ?>" data-el="<?= $side ?>-team">
        <?= crest($team['crest'], 20, 'crest') ?>
        <span class="match__name"><?= e($team['name']) ?></span>
        <?php if ($team['favorite']): ?>
          <span class="match__fav" title="Équipe suivie"><?= icon('star') ?></span>
        <?php endif; ?>
      </span>
    <?php endforeach; ?>
    <?php if ($showComp && $m['competition']['name'] !== ''): ?>
      <span class="match__comp"><?= e($m['competition']['name']) ?></span>
    <?php endif; ?>
  </span>

  <span class="match__score">
    <span class="match__goals<?= $m['home']['winner'] ? ' is-winner' : '' ?>" data-el="home"><?= $m['home']['score'] ?? '-' ?></span>
    <span class="match__goals<?= $m['away']['winner'] ? ' is-winner' : '' ?>" data-el="away"><?= $m['away']['score'] ?? '-' ?></span>
  </span>

  <?php if ($result !== null && isset($resultLabels[$result])): ?>
    <span class="result-chip result-chip--<?= e(strtolower($result)) ?>" title="<?= e($resultLabels[$result]) ?>"><?= e($result) ?></span>
  <?php endif; ?>
</a>
