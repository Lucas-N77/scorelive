<?php
/**
 * Page d'installation : clé d'API manquante (ou config.php absent).
 *
 * @var string $reason  key | config
 */

use App\Core\App;
use App\Core\Database;
use App\Core\View;

$configFile = ROOT_PATH . '/config.php';
$configExists = is_file($configFile);

$dbOk = false;
try {
    $dbOk = Database::value('SELECT COUNT(*) FROM competitions') !== null;
} catch (Throwable) {
    $dbOk = false;
}
$checks = [
    ['Fichier config.php', $configExists, $configExists ? 'présent' : 'absent : copiez config.example.php en config.php'],
    ['Base de données MySQL', $dbOk, $dbOk ? 'connectée' : 'injoignable : démarrez MySQL et importez database.sql'],
    ['Extension PHP cURL', function_exists('curl_init'), function_exists('curl_init') ? 'active' : 'manquante'],
    ['Clé d’API football-data.org', false, 'à renseigner'],
];
?>
<section class="setup">
  <div class="setup__hero">
    <?php View::partial('brand-mark', ['size' => 64]); ?>
    <div>
      <p class="setup__kicker">Bienvenue sur ScoreLive</p>
      <h1 class="setup__title">Il ne manque plus que votre clé d’API</h1>
      <p class="setup__lead">Les scores viennent de <strong>football-data.org</strong>. L’inscription est gratuite et la clé
        arrive par e-mail en une minute. Elle reste sur votre Mac : elle n’est jamais envoyée au navigateur.</p>
    </div>
  </div>

  <ol class="steps">
    <li class="steps__item">
      <span class="steps__num">1</span>
      <div>
        <h2 class="steps__title">Créez un compte gratuit</h2>
        <p>Sur <a href="https://www.football-data.org/client/register" target="_blank" rel="noopener noreferrer">football-data.org/client/register</a>
          (offre « Free » : 12 compétitions, 10 requêtes par minute).</p>
      </div>
    </li>
    <?php if (!$configExists): ?>
      <li class="steps__item">
        <span class="steps__num">2</span>
        <div>
          <h2 class="steps__title">Créez le fichier de configuration</h2>
          <p>Dans le dossier du projet, copiez <code>config.example.php</code> en <code>config.php</code>.</p>
        </div>
      </li>
    <?php endif; ?>
    <li class="steps__item">
      <span class="steps__num"><?= $configExists ? 2 : 3 ?></span>
      <div>
        <h2 class="steps__title">Collez la clé dans config.php</h2>
        <p>Ouvrez <code class="path"><?= e($configFile) ?></code> et remplacez la valeur vide :</p>
        <pre class="code"><code>'api' => [
    'key' => '<mark>votre-clé-reçue-par-e-mail</mark>',</code></pre>
      </div>
    </li>
    <li class="steps__item">
      <span class="steps__num"><?= $configExists ? 3 : 4 ?></span>
      <div>
        <h2 class="steps__title">Rechargez la page</h2>
        <p>Les matchs du jour s’affichent. Les réponses de l’API sont mises en cache 30 s pour respecter le quota gratuit.</p>
      </div>
    </li>
  </ol>

  <div class="card setup__checks">
    <h2 class="card__title">Vérifications</h2>
    <ul class="checks">
      <?php foreach ($checks as [$label, $ok, $detail]): ?>
        <li class="checks__item <?= $ok ? 'is-ok' : 'is-ko' ?>">
          <?= icon($ok ? 'check' : 'x') ?>
          <span><strong><?= e($label) ?></strong> : <?= e($detail) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <p class="setup__actions">
    <a class="btn btn--primary" href="<?= e(current_url()) ?>"><?= icon('refresh') ?><span>J’ai collé ma clé, recharger</span></a>
  </p>
</section>
