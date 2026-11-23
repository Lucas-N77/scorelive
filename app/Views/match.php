<?php
/**
 * Page match.
 *
 * @var array                  $m          match présenté
 * @var string                 $round
 * @var array                  $events
 * @var bool                   $hasDetails
 * @var array|null             $lineups
 * @var array                  $stats
 * @var array                  $info
 * @var array|null             $h2h
 * @var array                  $favorites
 * @var DateTimeImmutable|null $kickoff
 */

use App\Core\View;
use App\Services\Format;

$state = $m['state'];
$extra = [];
if ($m['ht'] && $state !== 'upcoming') {
    $extra[] = 'MT ' . $m['ht'][0] . '-' . $m['ht'][1];
}
if ($m['note'] !== '') {
    $extra[] = $m['note'];
}
$totalGoals = (int) ($m['home']['score'] ?? 0) + (int) ($m['away']['score'] ?? 0);
$goalEvents = count(array_filter($events, static fn (array $ev): bool => $ev['type'] === 'goal'));
$scorersMissing = in_array($state, ['live', 'finished'], true) && $totalGoals > 0 && $goalEvents === 0;
$cardLabels = ['YELLOW' => 'Carton jaune', 'RED' => 'Carton rouge', 'YELLOW_RED' => 'Second carton jaune'];
?>
<nav class="breadcrumb" aria-label="Fil d’Ariane">
  <?php if ($m['competition']['url']): ?>
    <a class="breadcrumb__link" href="<?= e($m['competition']['url']) ?>">
      <?= crest($m['competition']['emblem'], 18, 'crest crest--sm crest--emblem') ?>
      <span><?= e($m['competition']['name']) ?></span>
    </a>
  <?php endif; ?>
  <?php if ($round !== ''): ?>
    <span class="breadcrumb__sep" aria-hidden="true">›</span><span><?= e($round) ?></span>
  <?php endif; ?>
</nav>

<section class="scoreboard scoreboard--<?= e($state) ?>" data-match-page
         data-match-id="<?= (int) $m['id'] ?>" data-state="<?= e($state) ?>" data-kickoff="<?= e($m['kickoff_iso']) ?>">
  <div class="scoreboard__team">
    <a class="scoreboard__link" href="<?= e($m['home']['url'] ?? '#') ?>">
      <?= crest($m['home']['crest'], 72, 'crest crest--xl') ?>
      <span class="scoreboard__name"><?= e($m['home']['full']) ?></span>
    </a>
    <?php if ($m['home']['id']): ?>
      <?php View::partial('fav-button', ['type' => 'team', 'id' => $m['home']['id'], 'active' => isset($favorites['teams'][$m['home']['id']]), 'compact' => true, 'label' => $m['home']['full']]); ?>
    <?php endif; ?>
  </div>

  <div class="scoreboard__center">
    <p class="scoreboard__date"><?= $kickoff ? e(ucfirst(Format::dayShort($kickoff)) . ' · ' . Format::time($kickoff)) : '' ?></p>
    <p class="scoreboard__score">
      <span class="<?= $m['home']['winner'] ? 'is-winner' : '' ?>" data-el="home"><?= $m['home']['score'] ?? '-' ?></span>
      <span class="scoreboard__sep" aria-hidden="true">-</span>
      <span class="<?= $m['away']['winner'] ? 'is-winner' : '' ?>" data-el="away"><?= $m['away']['score'] ?? '-' ?></span>
    </p>
    <p class="scoreboard__status">
      <span class="live-badge live-badge--lg" data-el="badge"<?= $state === 'live' ? '' : ' hidden' ?>>Live</span>
      <span class="scoreboard__clock<?= $m['tick'] ? ' has-tick' : '' ?>" data-el="clock"><?= e($state === 'upcoming' ? $m['label'] : $m['clock']) ?></span>
    </p>
    <p class="scoreboard__extra" data-el="note" data-note-mode="full"><?= e(implode(' · ', $extra)) ?></p>
  </div>

  <div class="scoreboard__team">
    <a class="scoreboard__link" href="<?= e($m['away']['url'] ?? '#') ?>">
      <?= crest($m['away']['crest'], 72, 'crest crest--xl') ?>
      <span class="scoreboard__name"><?= e($m['away']['full']) ?></span>
    </a>
    <?php if ($m['away']['id']): ?>
      <?php View::partial('fav-button', ['type' => 'team', 'id' => $m['away']['id'], 'active' => isset($favorites['teams'][$m['away']['id']]), 'compact' => true, 'label' => $m['away']['full']]); ?>
    <?php endif; ?>
  </div>
</section>

<div class="tabs" data-tabs data-tabs-default="resume">
  <div class="tabs__list" role="tablist" aria-label="Sections du match" hidden>
    <button type="button" class="tabs__tab" role="tab" data-tab="resume">Résumé</button>
    <?php if ($lineups): ?><button type="button" class="tabs__tab" role="tab" data-tab="compos">Compos</button><?php endif; ?>
    <?php if ($stats): ?><button type="button" class="tabs__tab" role="tab" data-tab="stats">Stats</button><?php endif; ?>
    <?php if ($h2h): ?><button type="button" class="tabs__tab" role="tab" data-tab="h2h">Face-à-face</button><?php endif; ?>
    <button type="button" class="tabs__tab" role="tab" data-tab="infos">Infos</button>
  </div>

  <section class="tabs__panel card" data-panel="resume" role="tabpanel">
    <h2 class="panel-title">Résumé du match</h2>
    <?php if ($scorersMissing): ?>
      <div class="notice">
        <?= icon('info') ?>
        <p>Le détail des buteurs n’est pas fourni par votre offre football-data.org : l’offre gratuite donne le score,
           le statut et les informations du match.</p>
      </div>
    <?php endif; ?>

    <?php if ($events): ?>
      <ol class="timeline">
        <?php foreach ($events as $ev): ?>
          <?php if ($ev['type'] === 'period'): ?>
            <li class="timeline__period">
              <span><?= e($ev['label']) ?></span>
              <?php if (!empty($ev['score'])): ?><strong><?= e($ev['score']) ?></strong><?php endif; ?>
            </li>
          <?php else: ?>
            <li class="timeline__event timeline__event--<?= e($ev['side']) ?> timeline__event--<?= e($ev['type']) ?>">
              <span class="timeline__minute"><?= e($ev['minute']) ?></span>
              <span class="timeline__icon">
                <?php if ($ev['type'] === 'goal'): ?>
                  <?= icon('ball', $ev['kind'] === 'OWN' ? 'is-own-goal' : '') ?>
                <?php elseif ($ev['type'] === 'card'): ?>
                  <span class="card-icon card-icon--<?= e(strtolower(str_replace('_', '-', $ev['kind']))) ?>" title="<?= e($cardLabels[$ev['kind']] ?? 'Carton') ?>"></span>
                <?php else: ?>
                  <?= icon('swap') ?>
                <?php endif; ?>
              </span>
              <span class="timeline__body">
                <span class="timeline__player"><?= e($ev['player']) ?></span>
                <span class="timeline__detail">
                  <?php if ($ev['type'] === 'goal'): ?>
                    <?= $ev['kind'] === 'PENALTY' ? 'Penalty' : ($ev['kind'] === 'OWN' ? 'Contre son camp' : 'But') ?>
                    <?php if (!empty($ev['assist'])): ?> · passe de <?= e($ev['assist']) ?><?php endif; ?>
                  <?php elseif ($ev['type'] === 'card'): ?>
                    <?= e($cardLabels[$ev['kind']] ?? 'Carton') ?>
                  <?php else: ?>
                    Remplace <?= e($ev['out'] ?? '?') ?>
                  <?php endif; ?>
                </span>
              </span>
              <?php if ($ev['type'] === 'goal' && !empty($ev['score'])): ?>
                <span class="timeline__score"><?= e($ev['score']) ?></span>
              <?php endif; ?>
            </li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ol>
    <?php elseif ($state === 'upcoming'): ?>
      <?php View::partial('empty', [
          'title' => 'Le match n’a pas encore commencé',
          'text'  => $kickoff ? 'Coup d’envoi ' . Format::relativeDay($kickoff) . ' à ' . Format::time($kickoff) . '.' : null,
          'icon'  => 'clock',
      ]); ?>
    <?php elseif (!$scorersMissing): ?>
      <?php View::partial('empty', [
          'title' => in_array($state, ['live', 'finished'], true) && $totalGoals === 0 ? 'Aucun but pour le moment' : 'Aucun événement disponible',
          'text'  => $state === 'off' ? 'Statut du match : ' . mb_strtolower($m['label']) . '.' : null,
          'icon'  => 'ball',
      ]); ?>
    <?php endif; ?>
  </section>

  <?php if ($lineups): ?>
    <section class="tabs__panel card" data-panel="compos" role="tabpanel">
      <h2 class="panel-title">Compositions</h2>
      <div class="lineups">
        <?php foreach (['home', 'away'] as $side): $l = $lineups[$side]; ?>
          <div class="lineup">
            <h3 class="lineup__team">
              <?= crest($l['crest'], 22) ?><span><?= e($l['name']) ?></span>
              <?php if ($l['formation']): ?><span class="lineup__formation"><?= e($l['formation']) ?></span><?php endif; ?>
            </h3>
            <ul class="player-list">
              <?php foreach ($l['lineup'] as $p): ?>
                <li class="player-line">
                  <span class="player-line__num"><?= e((string) ($p['number'] ?? '')) ?></span>
                  <span class="player-line__name"><?= e($p['name']) ?></span>
                  <span class="player-line__meta"><?= e($p['position']) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
            <?php if ($l['bench']): ?>
              <h4 class="lineup__subtitle">Remplaçants</h4>
              <ul class="player-list player-list--bench">
                <?php foreach ($l['bench'] as $p): ?>
                  <li class="player-line">
                    <span class="player-line__num"><?= e((string) ($p['number'] ?? '')) ?></span>
                    <span class="player-line__name"><?= e($p['name']) ?></span>
                    <span class="player-line__meta"><?= e($p['position']) ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <?php if ($l['coach']): ?><p class="lineup__coach">Entraîneur : <strong><?= e($l['coach']) ?></strong></p><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($stats): ?>
    <section class="tabs__panel card" data-panel="stats" role="tabpanel">
      <h2 class="panel-title">Statistiques</h2>
      <ul class="stats">
        <?php foreach ($stats as $s): ?>
          <li class="stat">
            <div class="stat__head">
              <span class="stat__value<?= $s['home'] > $s['away'] ? ' is-best' : '' ?>"><?= (int) $s['home'] ?></span>
              <span class="stat__label"><?= e($s['label']) ?></span>
              <span class="stat__value<?= $s['away'] > $s['home'] ? ' is-best' : '' ?>"><?= (int) $s['away'] ?></span>
            </div>
            <div class="stat__bars" aria-hidden="true">
              <span class="stat__bar stat__bar--home"><span style="width: <?= (int) $s['homePct'] ?>%"></span></span>
              <span class="stat__bar stat__bar--away"><span style="width: <?= (int) $s['awayPct'] ?>%"></span></span>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <?php if ($h2h): ?>
    <section class="tabs__panel card" data-panel="h2h" role="tabpanel">
      <h2 class="panel-title">Face-à-face</h2>
      <?php if ($h2h['played'] > 0): ?>
        <div class="h2h-summary">
          <div class="h2h-summary__item"><strong><?= (int) $h2h['tally']['home'] ?></strong><span>Victoires <?= e($m['home']['name']) ?></span></div>
          <div class="h2h-summary__item"><strong><?= (int) $h2h['tally']['draw'] ?></strong><span>Nuls</span></div>
          <div class="h2h-summary__item"><strong><?= (int) $h2h['tally']['away'] ?></strong><span>Victoires <?= e($m['away']['name']) ?></span></div>
        </div>
        <div class="h2h-bar" aria-hidden="true">
          <span class="h2h-bar__home" style="width: <?= (int) $h2h['pct']['home'] ?>%"></span>
          <span class="h2h-bar__draw" style="width: <?= (int) $h2h['pct']['draw'] ?>%"></span>
          <span class="h2h-bar__away" style="width: <?= (int) $h2h['pct']['away'] ?>%"></span>
        </div>
      <?php endif; ?>
      <h3 class="section-subtitle">Dernières confrontations</h3>
      <div class="match-list">
        <?php foreach ($h2h['matches'] as $x): ?>
          <?php View::partial('match-row', ['m' => $x, 'showDate' => true, 'showYear' => true, 'showComp' => true]); ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="tabs__panel card" data-panel="infos" role="tabpanel">
    <h2 class="panel-title">Infos du match</h2>
    <dl class="info-list">
      <?php foreach ($info as [$label, $value]): ?>
        <div class="info-list__row"><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div>
      <?php endforeach; ?>
    </dl>
  </section>
</div>
