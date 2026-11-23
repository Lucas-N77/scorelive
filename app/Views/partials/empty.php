<?php
/**
 * État vide illustré.
 *
 * @var string      $title
 * @var string|null $text
 * @var string|null $icon
 * @var array|null  $action  [libellé, url]
 */
?>
<div class="empty">
  <div class="empty__icon"><?= icon($icon ?? 'ball') ?></div>
  <p class="empty__title"><?= e($title) ?></p>
  <?php if (!empty($text)): ?><p class="empty__text"><?= e($text) ?></p><?php endif; ?>
  <?php if (!empty($action)): ?>
    <a class="btn btn--ghost btn--small" href="<?= e($action[1]) ?>"><?= e($action[0]) ?></a>
  <?php endif; ?>
</div>
