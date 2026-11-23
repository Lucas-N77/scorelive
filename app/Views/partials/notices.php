<?php
/**
 * Messages flash et avertissement « données en cache ».
 *
 * @var array $flashes
 * @var array $stale
 */

use App\Services\Format;

$icons = ['success' => 'check', 'error' => 'alert', 'info' => 'info', 'warning' => 'alert'];
?>
<?php foreach ($flashes as $flash): ?>
  <?php $type = in_array($flash['type'] ?? '', ['success', 'error', 'info', 'warning'], true) ? $flash['type'] : 'info'; ?>
  <div class="alert alert--<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
    <?= icon($icons[$type]) ?>
    <p><?= e($flash['message'] ?? '') ?></p>
  </div>
<?php endforeach; ?>

<?php if ($stale): ?>
  <?php
    $age = max(array_column($stale, 'age'));
    $retry = max(array_column($stale, 'retryAfter'));
    $quota = in_array('quota', array_column($stale, 'reason'), true);
  ?>
  <div class="alert alert--warning" role="status">
    <?= icon('clock') ?>
    <p>
      <strong>Données en cache (<?= e(Format::ago($age)) ?>).</strong>
      <?= $quota
          ? 'La limite de 10 requêtes par minute de l’API est atteinte'
          : 'L’API football-data.org ne répond pas pour le moment' ?>
      <?= $retry > 0 ? ' : nouvel essai dans ' . (int) $retry . ' s.' : '.' ?>
    </p>
  </div>
<?php endif; ?>
