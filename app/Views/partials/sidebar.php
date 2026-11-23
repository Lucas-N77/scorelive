<?php
/**
 * Colonne latérale (grand écran) : compétitions de l'offre, favorites en tête.
 *
 * @var array       $competitions
 * @var array       $favorites   [code => true]
 * @var string|null $active
 */

use App\Services\Format;

$pinned = array_values(array_filter($competitions, static fn (array $c): bool => isset($favorites[(string) $c['code']])));
?>
<aside class="sidebar" aria-label="Compétitions">
  <?php if ($pinned): ?>
    <h2 class="sidebar__title"><?= icon('star') ?> Mes compétitions</h2>
    <ul class="sidebar__list">
      <?php foreach ($pinned as $c): ?>
        <li>
          <a class="sidebar__link<?= $active === $c['code'] ? ' is-active' : '' ?>" href="<?= e(url('competition/' . $c['code'])) ?>">
            <?= crest($c['emblem'], 22, 'crest crest--sm crest--emblem') ?>
            <span class="sidebar__text">
              <span class="sidebar__name"><?= e(Format::competitionName($c)) ?></span>
              <span class="sidebar__area"><?= e(Format::area($c['area_name'])) ?></span>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <h2 class="sidebar__title"><?= icon('trophy') ?> Compétitions</h2>
  <?php if ($competitions): ?>
    <ul class="sidebar__list">
      <?php foreach ($competitions as $c): ?>
        <li>
          <a class="sidebar__link<?= $active === $c['code'] ? ' is-active' : '' ?>" href="<?= e(url('competition/' . $c['code'])) ?>">
            <?= crest($c['emblem'], 22, 'crest crest--sm crest--emblem') ?>
            <span class="sidebar__text">
              <span class="sidebar__name"><?= e(Format::competitionName($c)) ?></span>
              <span class="sidebar__area"><?= e(Format::area($c['area_name'])) ?></span>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="sidebar__empty">Importez database.sql pour afficher les compétitions.</p>
  <?php endif; ?>
</aside>
