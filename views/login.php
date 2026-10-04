<?php

declare(strict_types=1);

/**
 * @var string|null $error
 */

$logoUrl = appLogoUrl();
$appName = appName();
?>
<div class="brand-lockup mb-4 text-center">
  <?php if ($logoUrl) : ?>
    <div class="mb-3 text-center">
      <img src="<?= e($logoUrl) ?>" alt="Logo" class="login-logo-img">
    </div>
  <?php else : ?>
    <span class="brand-mark mb-2 mx-auto"><i class="fa-solid fa-shield-halved"></i></span>
  <?php endif; ?>
  <div>
    <h1 class="h5 mb-1"><?= e($appName) ?></h1>
    <p class="muted small mb-0">Masuk untuk mengelola server DNS otoritatif.</p>
  </div>
</div>

<?php if (!empty($error)) : ?>
  <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="/login" class="stack">
  <?= csrfField() ?>
  <div>
    <label class="form-label small" for="login-username">Username</label>
    <input class="form-control" id="login-username" name="username" autocomplete="username" required autofocus>
  </div>
  <div>
    <label class="form-label small" for="login-password">Kata Sandi</label>
    <input class="form-control" type="password" id="login-password"
           name="password" autocomplete="current-password" required>
  </div>
  <button class="btn btn-primary w-100 py-2 mt-2" type="submit">Masuk ke Panel</button>
</form>

<div class="text-center mt-4 pt-3 border-top border-secondary-subtle">
  <small class="text-secondary d-block"><?= appFooterText() ?></small>
  <small class="text-secondary opacity-75">v0.2.1</small>
</div>
