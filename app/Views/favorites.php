<?php
/**
 * Mes favoris.
 *
 * @var array       $teams
 * @var array       $competitions
 * @var array       $groups
 * @var string|null $apiMessage
 */

use App\Core\View;
?>
<section class="page-head">
  <div class="page-head__text">
    <h1 class="page-title">Mes favoris</h1>
    <p class="page-subtitle"><?= e(plural(count($teams), 'équipe')) ?> · <?= e(plural(count($competitions), 'compétition')) ?></p>
  </div>
  <p class="live-status" data-live-status hidden>
    <span class="live-status__dot" aria-hidden="true"></span>
    <span data-live-status-text>Actualisation automatique</span>
  </p>
</section>

<?php if (!$teams && !$competitions): ?>
  <?php View::partial('empty', [
      'title'  => 'Aucun favori pour l’instant',
      'text'   => 'Touchez l’étoile sur la page d’une équipe ou d’une compétition pour la retrouver ici.',
      'icon'   => 'star',
      'action' => ['Rechercher une équipe', url('recherche')],
  ]); ?>
<?php else: ?>

  <h2 class="section-title"><?= icon('ball') ?> Aujourd’hui</h2>
  <?php if ($apiMessage): ?>
    <div class="alert alert--warning" role="status"><?= icon('alert') ?><p><?= e($apiMessage) ?></p></div>
  <?php elseif ($groups): ?>
    <div class="match-board" data-match-board data-filter-state="all">
      <?php foreach ($groups as $group): ?>
        <?php View::partial('competition-group', ['group' => $group]); ?>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="muted section-hint">Aucun match de vos favoris aujourd’hui.</p>
  <?php endif; ?>

  <?php if ($competitions): ?>
    <h2 class="section-title"><?= icon('trophy') ?> Compétitions suivies</h2>
    <ul class="fav-grid">
      <?php foreach ($competitions as $f): ?>
        <li class="fav-card" data-fav-card>
          <a class="fav-card__link" href="<?= e(url('competition/' . $f['ref_id'])) ?>">
            <?= crest($f['crest'], 36, 'crest crest--lg') ?>
            <span class="fav-card__name"><?= e($f['name']) ?></span>
          </a>
          <?php View::partial('fav-button', ['type' => 'competition', 'id' => $f['ref_id'], 'active' => true, 'compact' => true, 'label' => $f['name']]); ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($teams): ?>
    <h2 class="section-title"><?= icon('shield') ?> Équipes suivies</h2>
    <ul class="fav-grid">
      <?php foreach ($teams as $f): ?>
        <li class="fav-card" data-fav-card>
          <a class="fav-card__link" href="<?= e(url('equipe/' . $f['ref_id'])) ?>">
            <?= crest($f['crest'], 36, 'crest crest--lg') ?>
            <span class="fav-card__name"><?= e($f['name']) ?></span>
          </a>
          <?php View::partial('fav-button', ['type' => 'team', 'id' => $f['ref_id'], 'active' => true, 'compact' => true, 'label' => $f['name']]); ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
<?php endif; ?>
