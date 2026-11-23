<?php
/**
 * Page compétition : classement + matchs.
 *
 * @var array       $competition
 * @var string      $competitionKey
 * @var string      $name
 * @var array       $area
 * @var array       $season
 * @var int         $currentMatchday
 * @var int         $matchday
 * @var int|null    $maxMatchday
 * @var string      $mode          matchday | window
 * @var array       $tables
 * @var string|null $standingsMessage
 * @var array       $days
 * @var string|null $matchesMessage
 * @var bool        $isFavorite
 * @var string      $defaultTab
 */

use App\Core\View;
use App\Services\Format;

$seasonLabel = Format::season($season);
$matchdayUrl = static fn (int $n): string => url('competition/' . $competitionKey, ['journee' => $n]);
?>
<section class="hero">
  <div class="hero__media"><?= crest($competition['emblem'] ?? null, 64, 'crest crest--xl') ?></div>
  <div class="hero__text">
    <p class="hero__kicker">
      <?php if (!empty($area['flag'])): ?><?= crest($area['flag'], 16, 'flag') ?><?php endif; ?>
      <?= e(Format::area($area['name'] ?? null)) ?>
    </p>
    <h1 class="hero__title"><?= e($name) ?></h1>
    <p class="hero__meta">
      <?php if ($seasonLabel !== ''): ?><span>Saison <?= e($seasonLabel) ?></span><?php endif; ?>
      <?php if ($currentMatchday > 0): ?><span>Journée <?= (int) $currentMatchday ?></span><?php endif; ?>
    </p>
  </div>
  <div class="hero__actions">
    <?php View::partial('fav-button', ['type' => 'competition', 'id' => $competitionKey, 'active' => $isFavorite, 'label' => $name]); ?>
  </div>
</section>

<div class="tabs" data-tabs data-tabs-default="<?= e($defaultTab) ?>">
  <div class="tabs__list" role="tablist" aria-label="Sections de la compétition" hidden>
    <button type="button" class="tabs__tab" role="tab" data-tab="classement"><?= icon('list') ?><span>Classement</span></button>
    <button type="button" class="tabs__tab" role="tab" data-tab="matchs"><?= icon('calendar') ?><span>Matchs</span></button>
  </div>

  <section class="tabs__panel" data-panel="classement" role="tabpanel">
    <h2 class="panel-title">Classement</h2>
    <?php if ($tables): ?>
      <?php foreach ($tables as $table): ?>
        <div class="card card--flush">
          <?php if ($table['title'] !== ''): ?><h3 class="card__title"><?= e($table['title']) ?></h3><?php endif; ?>
          <div class="table-wrap">
            <table class="standings">
              <thead>
                <tr>
                  <th scope="col" class="standings__pos"><abbr title="Position">#</abbr></th>
                  <th scope="col" class="standings__team">Équipe</th>
                  <th scope="col"><abbr title="Matchs joués">J</abbr></th>
                  <th scope="col" class="hide-sm"><abbr title="Gagnés">G</abbr></th>
                  <th scope="col" class="hide-sm"><abbr title="Nuls">N</abbr></th>
                  <th scope="col" class="hide-sm"><abbr title="Perdus">P</abbr></th>
                  <th scope="col" class="hide-sm"><abbr title="Buts marqués : encaissés">Buts</abbr></th>
                  <th scope="col"><abbr title="Différence de buts">+/-</abbr></th>
                  <th scope="col" class="standings__pts"><abbr title="Points">Pts</abbr></th>
                  <?php if ($table['hasForm']): ?><th scope="col" class="hide-md">Forme</th><?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($table['rows'] as $row): ?>
                  <tr<?= $row['favorite'] ? ' class="is-fav"' : '' ?>>
                    <td class="standings__pos"><span class="pos-badge"><?= (int) $row['position'] ?></span></td>
                    <td class="standings__team">
                      <a href="<?= e($row['team']['url'] ?? '#') ?>" title="<?= e($row['team']['full']) ?>">
                        <?= crest($row['team']['crest'], 20) ?>
                        <span class="standings__name"><?= e($row['team']['name']) ?></span>
                      </a>
                    </td>
                    <td><?= (int) $row['played'] ?></td>
                    <td class="hide-sm"><?= (int) $row['won'] ?></td>
                    <td class="hide-sm"><?= (int) $row['draw'] ?></td>
                    <td class="hide-sm"><?= (int) $row['lost'] ?></td>
                    <td class="hide-sm"><?= (int) $row['gf'] ?>:<?= (int) $row['ga'] ?></td>
                    <td class="<?= $row['gd'] > 0 ? 'is-positive' : ($row['gd'] < 0 ? 'is-negative' : '') ?>"><?= $row['gd'] > 0 ? '+' . $row['gd'] : (int) $row['gd'] ?></td>
                    <td class="standings__pts"><?= (int) $row['points'] ?></td>
                    <?php if ($table['hasForm']): ?>
                      <td class="hide-md">
                        <span class="form-chips">
                          <?php foreach ($row['form'] as [$letter, $label]): ?>
                            <span class="result-chip result-chip--<?= e(strtolower($letter)) ?>" title="<?= e($label) ?>"><?= e($letter) ?></span>
                          <?php endforeach; ?>
                        </span>
                      </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <?php View::partial('empty', ['title' => $standingsMessage ?? 'Classement indisponible', 'icon' => 'list']); ?>
    <?php endif; ?>
  </section>

  <section class="tabs__panel" data-panel="matchs" role="tabpanel">
    <h2 class="panel-title">Matchs</h2>
    <?php if ($mode === 'matchday' && $matchday > 0): ?>
      <nav class="matchday-nav" aria-label="Choisir la journée">
        <?php if ($matchday > 1): ?>
          <a class="icon-btn" href="<?= e($matchdayUrl($matchday - 1)) ?>" aria-label="Journée précédente" rel="prev"><?= icon('left') ?></a>
        <?php else: ?>
          <span class="icon-btn is-disabled" aria-hidden="true"><?= icon('left') ?></span>
        <?php endif; ?>
        <span class="matchday-nav__label">
          Journée <?= (int) $matchday ?><?php if ($maxMatchday): ?> <small>sur <?= (int) $maxMatchday ?></small><?php endif; ?>
        </span>
        <?php if ($maxMatchday === null || $matchday < $maxMatchday): ?>
          <a class="icon-btn" href="<?= e($matchdayUrl($matchday + 1)) ?>" aria-label="Journée suivante" rel="next"><?= icon('right') ?></a>
        <?php else: ?>
          <span class="icon-btn is-disabled" aria-hidden="true"><?= icon('right') ?></span>
        <?php endif; ?>
      </nav>
      <?php if ($currentMatchday > 0 && $matchday !== $currentMatchday): ?>
        <p class="matchday-nav__back"><a href="<?= e($matchdayUrl($currentMatchday)) ?>">Revenir à la journée en cours (<?= (int) $currentMatchday ?>)</a></p>
      <?php endif; ?>
    <?php elseif ($mode === 'window'): ?>
      <p class="muted section-hint">Matchs des derniers et des prochains jours.</p>
    <?php endif; ?>

    <?php if ($matchesMessage): ?>
      <div class="alert alert--warning" role="status"><?= icon('alert') ?><p><?= e($matchesMessage) ?></p></div>
    <?php endif; ?>

    <?php if ($days): ?>
      <?php foreach ($days as $d): ?>
        <div class="card card--flush day-group">
          <h3 class="day-group__title"><?= e($d['long']) ?></h3>
          <div class="day-group__body">
            <?php foreach ($d['matches'] as $x): ?>
              <?php View::partial('match-row', ['m' => $x]); ?>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php elseif (!$matchesMessage): ?>
      <?php View::partial('empty', ['title' => 'Aucun match pour cette journée', 'icon' => 'calendar']); ?>
    <?php endif; ?>
  </section>
</div>
