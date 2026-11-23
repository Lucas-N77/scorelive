<?php
/**
 * Page équipe.
 *
 * @var array       $team
 * @var int         $teamId
 * @var string      $name
 * @var string      $area
 * @var string|null $flag
 * @var array       $finished
 * @var array       $upcoming
 * @var array       $form
 * @var string|null $matchesMessage
 * @var array       $squad
 * @var array|null  $coach
 * @var array       $competitions
 * @var array       $colors
 * @var bool        $isFavorite
 * @var string|null $website
 */

use App\Core\View;
use App\Services\Format;

$resultLabels = ['V' => 'Victoire', 'N' => 'Match nul', 'D' => 'Défaite'];
$info = [];
if (!empty($team['shortName']) && $team['shortName'] !== $name) {
    $info[] = ['Nom usuel', $team['shortName'] . (!empty($team['tla']) ? ' (' . $team['tla'] . ')' : '')];
}
if (!empty($team['founded'])) {
    $info[] = ['Fondé en', (string) $team['founded']];
}
if (!empty($team['venue'])) {
    $info[] = ['Stade', (string) $team['venue']];
}
if (!empty($team['address'])) {
    $info[] = ['Adresse', (string) $team['address']];
}
if ($coach && !empty($coach['name'])) {
    $nat = Format::area($coach['nationality'] ?? null);
    $info[] = ['Entraîneur', $coach['name'] . ($nat !== '' ? ' (' . $nat . ')' : '')];
}
?>
<section class="hero hero--team">
  <div class="hero__media"><?= crest($team['crest'] ?? null, 72, 'crest crest--xl') ?></div>
  <div class="hero__text">
    <p class="hero__kicker">
      <?php if ($flag): ?><?= crest($flag, 16, 'flag') ?><?php endif; ?>
      <?= e($area) ?>
    </p>
    <h1 class="hero__title"><?= e($name) ?></h1>
    <p class="hero__meta">
      <?php if (!empty($team['founded'])): ?><span>Fondé en <?= (int) $team['founded'] ?></span><?php endif; ?>
      <?php if (!empty($team['venue'])): ?><span><?= e($team['venue']) ?></span><?php endif; ?>
    </p>
    <?php if ($form): ?>
      <p class="hero__form">
        <span class="muted">Forme</span>
        <span class="form-chips">
          <?php foreach ($form as $r): ?>
            <span class="result-chip result-chip--<?= e(strtolower($r)) ?>" title="<?= e($resultLabels[$r] ?? '') ?>"><?= e($r) ?></span>
          <?php endforeach; ?>
        </span>
      </p>
    <?php endif; ?>
  </div>
  <div class="hero__actions">
    <?php View::partial('fav-button', ['type' => 'team', 'id' => $teamId, 'active' => $isFavorite, 'label' => $name]); ?>
  </div>
</section>

<div class="tabs" data-tabs data-tabs-default="resultats">
  <div class="tabs__list" role="tablist" aria-label="Sections de l’équipe" hidden>
    <button type="button" class="tabs__tab" role="tab" data-tab="resultats">Résultats</button>
    <button type="button" class="tabs__tab" role="tab" data-tab="calendrier">À venir</button>
    <?php if ($squad): ?><button type="button" class="tabs__tab" role="tab" data-tab="effectif">Effectif</button><?php endif; ?>
    <button type="button" class="tabs__tab" role="tab" data-tab="infos">Infos</button>
  </div>

  <?php if ($matchesMessage): ?>
    <div class="alert alert--warning" role="status"><?= icon('alert') ?><p><?= e($matchesMessage) ?></p></div>
  <?php endif; ?>

  <section class="tabs__panel" data-panel="resultats" role="tabpanel">
    <h2 class="panel-title">Derniers résultats</h2>
    <?php if ($finished): ?>
      <div class="card card--flush match-list">
        <?php foreach ($finished as $x): ?>
          <?php View::partial('match-row', ['m' => $x, 'showDate' => true, 'showComp' => true]); ?>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <?php View::partial('empty', ['title' => 'Aucun résultat récent', 'text' => 'Aucun match terminé ces 90 derniers jours.', 'icon' => 'calendar']); ?>
    <?php endif; ?>
  </section>

  <section class="tabs__panel" data-panel="calendrier" role="tabpanel">
    <h2 class="panel-title">Prochains matchs</h2>
    <?php if ($upcoming): ?>
      <div class="card card--flush match-list">
        <?php foreach ($upcoming as $x): ?>
          <?php View::partial('match-row', ['m' => $x, 'showDate' => true, 'showComp' => true, 'result' => null]); ?>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <?php View::partial('empty', ['title' => 'Aucun match programmé', 'text' => 'Le calendrier des 90 prochains jours est vide pour le moment.', 'icon' => 'calendar']); ?>
    <?php endif; ?>
  </section>

  <?php if ($squad): ?>
    <section class="tabs__panel" data-panel="effectif" role="tabpanel">
      <h2 class="panel-title">Effectif</h2>
      <div class="squad">
        <?php foreach ($squad as $group): ?>
          <div class="card card--flush squad__group">
            <h3 class="card__title"><?= e($group['label']) ?> <span class="muted">(<?= count($group['players']) ?>)</span></h3>
            <ul class="player-list">
              <?php foreach ($group['players'] as $p): ?>
                <li class="player-line">
                  <span class="player-line__num"><?= e((string) ($p['number'] ?? '')) ?></span>
                  <span class="player-line__name"><?= e($p['name']) ?></span>
                  <span class="player-line__meta">
                    <?= e(implode(' · ', array_filter([$p['position'], $p['nationality'], $p['age'] !== null ? $p['age'] . ' ans' : null]))) ?>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="tabs__panel card" data-panel="infos" role="tabpanel">
    <h2 class="panel-title">Infos du club</h2>
    <dl class="info-list">
      <?php foreach ($info as [$label, $value]): ?>
        <div class="info-list__row"><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div>
      <?php endforeach; ?>
      <?php if ($colors): ?>
        <div class="info-list__row">
          <dt>Couleurs</dt>
          <dd class="swatches">
            <?php foreach ($colors as $c): ?>
              <span class="swatch" style="background: <?= e($c['hex']) ?>" title="<?= e($c['name']) ?>"></span>
            <?php endforeach; ?>
            <span class="muted"><?= e((string) ($team['clubColors'] ?? '')) ?></span>
          </dd>
        </div>
      <?php endif; ?>
      <?php if ($website): ?>
        <div class="info-list__row">
          <dt>Site officiel</dt>
          <dd><a href="<?= e($website) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e(preg_replace('#^https?://(www\.)?#i', '', rtrim($website, '/'))) ?></a></dd>
        </div>
      <?php endif; ?>
    </dl>

    <?php if ($competitions): ?>
      <h3 class="section-subtitle">Compétitions de la saison</h3>
      <ul class="chip-list">
        <?php foreach ($competitions as $c): ?>
          <li>
            <?php if ($c['url']): ?>
              <a class="chip" href="<?= e($c['url']) ?>"><?= crest($c['emblem'], 16, 'crest crest--sm crest--emblem') ?><span><?= e($c['name']) ?></span></a>
            <?php else: ?>
              <span class="chip chip--muted" title="Non incluse dans votre offre"><?= crest($c['emblem'], 16, 'crest crest--sm crest--emblem') ?><span><?= e($c['name']) ?></span></span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
