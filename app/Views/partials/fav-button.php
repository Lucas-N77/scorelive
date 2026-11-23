<?php
/**
 * Bouton « suivre » (étoile) pour une équipe ou une compétition.
 * Fonctionne en formulaire classique (avec jeton CSRF) et en fetch() via app.js.
 *
 * @var string      $type     team | competition
 * @var string|int  $id
 * @var bool|null   $active
 * @var bool|null   $compact
 * @var string|null $label    nom affiché dans l'infobulle
 */

use App\Core\Auth;

$active = !empty($active);
$compact = !empty($compact);
$label = isset($label) && $label !== '' ? (string) $label : '';
$on = 'Retirer des favoris';
$off = 'Ajouter aux favoris';
$aria = static fn (bool $state): string => ($state ? $on : $off) . ($label !== '' ? ' : ' . $label : '');
?>
<?php if (Auth::check()): ?>
  <form class="fav-form" method="post" action="<?= e(url('favoris/basculer')) ?>" data-fav-form>
    <?= csrf_field() ?>
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <input type="hidden" name="id" value="<?= e((string) $id) ?>">
    <button type="submit"
            class="fav-btn<?= $compact ? ' fav-btn--compact' : '' ?><?= $active ? ' is-active' : '' ?>"
            aria-pressed="<?= $active ? 'true' : 'false' ?>"
            aria-label="<?= e($aria($active)) ?>"
            title="<?= e($active ? $on : $off) ?>"
            data-label-on="<?= e($aria(true)) ?>"
            data-label-off="<?= e($aria(false)) ?>">
      <?= icon('star') ?>
      <?php if (!$compact): ?>
        <span class="fav-btn__text" data-fav-text data-text-on="Suivi" data-text-off="Suivre"><?= $active ? 'Suivi' : 'Suivre' ?></span>
      <?php endif; ?>
    </button>
  </form>
<?php else: ?>
  <a class="fav-btn<?= $compact ? ' fav-btn--compact' : '' ?>"
     href="<?= e(url('connexion', ['retour' => current_url()])) ?>"
     title="Connectez-vous pour suivre" aria-label="Connectez-vous pour ajouter aux favoris<?= $label !== '' ? ' : ' . e($label) : '' ?>">
    <?= icon('star') ?>
    <?php if (!$compact): ?><span class="fav-btn__text">Suivre</span><?php endif; ?>
  </a>
<?php endif; ?>
