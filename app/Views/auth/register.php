<?php
/**
 * Formulaire d'inscription.
 *
 * @var array<string,string> $errors
 */

use App\Core\View;

$fieldError = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<p class="field__error" id="err-' . e($field) . '">' . e($errors[$field]) . '</p>'
        : '';
};
$invalid = static fn (string $field): string => isset($errors[$field])
    ? ' aria-invalid="true" aria-describedby="err-' . e($field) . '"'
    : '';
?>
<section class="auth">
  <div class="auth__card card">
    <div class="auth__head">
      <?php View::partial('brand-mark', ['size' => 48]); ?>
      <h1 class="auth__title">Créer un compte</h1>
      <p class="auth__subtitle">Gratuit. Suivez vos équipes et compétitions favorites.</p>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert--error" role="alert"><?= icon('alert') ?><p>Corrigez les champs signalés ci-dessous.</p></div>
    <?php endif; ?>

    <form class="form" method="post" action="<?= e(url('inscription')) ?>">
      <?= csrf_field() ?>

      <div class="field<?= isset($errors['pseudo']) ? ' has-error' : '' ?>">
        <label class="field__label" for="pseudo">Pseudo</label>
        <input class="field__input" id="pseudo" name="pseudo" type="text" required minlength="3" maxlength="30"
               autocomplete="nickname" autocapitalize="none" spellcheck="false" value="<?= e(old('pseudo')) ?>"<?= $invalid('pseudo') ?>>
        <?= $fieldError('pseudo') ?>
      </div>

      <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
        <label class="field__label" for="email">Adresse e-mail</label>
        <input class="field__input" id="email" name="email" type="email" required maxlength="190"
               autocomplete="email" autocapitalize="none" spellcheck="false" value="<?= e(old('email')) ?>"<?= $invalid('email') ?>>
        <?= $fieldError('email') ?>
      </div>

      <div class="field<?= isset($errors['mot_de_passe']) ? ' has-error' : '' ?>">
        <label class="field__label" for="mot_de_passe">Mot de passe</label>
        <input class="field__input" id="mot_de_passe" name="mot_de_passe" type="password" required minlength="8" maxlength="200"
               autocomplete="new-password"<?= $invalid('mot_de_passe') ?>>
        <p class="field__hint">8 caractères minimum.</p>
        <?= $fieldError('mot_de_passe') ?>
      </div>

      <div class="field<?= isset($errors['confirmation']) ? ' has-error' : '' ?>">
        <label class="field__label" for="confirmation">Confirmez le mot de passe</label>
        <input class="field__input" id="confirmation" name="confirmation" type="password" required minlength="8" maxlength="200"
               autocomplete="new-password"<?= $invalid('confirmation') ?>>
        <?= $fieldError('confirmation') ?>
      </div>

      <button class="btn btn--primary btn--block" type="submit">Créer mon compte</button>
    </form>

    <p class="auth__foot">Déjà inscrit ? <a href="<?= e(url('connexion')) ?>">Se connecter</a></p>
  </div>
</section>
