<?php
/**
 * Bloc d'une compétition avec ses matchs (accueil, favoris).
 *
 * @var array $group
 */

use App\Core\View;
?>
<section class="comp-group<?= $group['favorite'] ? ' is-favorite' : '' ?>" data-group
         data-has-live="<?= (int) $group['counts']['live'] ?>"
         data-has-finished="<?= (int) $group['counts']['finished'] ?>"
         data-has-upcoming="<?= (int) $group['counts']['upcoming'] ?>">
  <header class="comp-group__head">
    <?php if ($group['url']): ?>
      <a class="comp-group__title" href="<?= e($group['url']) ?>">
    <?php else: ?>
      <span class="comp-group__title">
    <?php endif; ?>
        <?= crest($group['emblem'], 24, 'crest crest--comp crest--emblem') ?>
        <span class="comp-group__text">
          <span class="comp-group__area">
            <?php if ($group['flag']): ?><?= crest($group['flag'], 14, 'flag') ?><?php endif; ?>
            <?= e($group['area']) ?>
          </span>
          <span class="comp-group__name"><?= e($group['name']) ?></span>
        </span>
    <?php if ($group['url']): ?>
      </a>
    <?php else: ?>
      </span>
    <?php endif; ?>

    <div class="comp-group__actions">
      <?php if ($group['url']): ?>
        <?php View::partial('fav-button', [
            'type'    => 'competition',
            'id'      => $group['key'],
            'active'  => $group['favorite'],
            'compact' => true,
            'label'   => $group['name'],
        ]); ?>
        <a class="comp-group__link" href="<?= e($group['url']) ?>">
          <span>Classement</span><?= icon('right') ?>
        </a>
      <?php endif; ?>
    </div>
  </header>

  <div class="comp-group__body">
    <?php foreach ($group['matches'] as $m): ?>
      <?php View::partial('match-row', ['m' => $m]); ?>
    <?php endforeach; ?>
  </div>
</section>
