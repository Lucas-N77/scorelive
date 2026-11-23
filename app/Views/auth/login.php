<?php
/**
 * Formulaire de connexion.
 *
 * @var string $retour
 */

use App\Core\View;
?>
<section class="auth">
  <div class="auth__card card">
    <div class="auth__head">
      <?php View::partial('brand-mark', ['size' => 48]); ?>
      <h1 class="auth__title">Connexion</h1>
      <p class="auth__subtitle">Retrouvez vos équipes et compétitions favorites sur tous vos appareils.</p>
    </div>

    <form class="form" method="post" action="<?= e(url('connexion')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="retour" value="<?= e($retour) ?>">

      <div class="field">
        <label class="field__label" for="identifiant">E-mail ou pseudo</label>
        <input class="field__input" id="identifiant" name="identifiant" type="text" required maxlength="190"
               autocomplete="username" autocapitalize="none" spellcheck="false" value="<?= e(old('identifiant')) ?>">
      </div>

      <div class="field">
        <label class="field__label" for="mot_de_passe">Mot de passe</label>
        <input class="field__input" id="mot_de_passe" name="mot_de_passe" type="password" required maxlength="200"
               autocomplete="current-password">
      </div>

      <button class="btn btn--primary btn--block" type="submit">Se connecter</button>
    </form>

    <p class="auth__foot">Pas encore de compte ? <a href="<?= e(url('inscription')) ?>">Créer un compte</a></p>
  </div>
</section>
