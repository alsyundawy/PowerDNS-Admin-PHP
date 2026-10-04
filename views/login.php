<?php

declare(strict_types=1);

/**
 * @var string|null $error
 */

?>
<div class="brand-lockup mb-4">
  <span class="brand-mark">PD</span>
  <div>
    <h1 class="h5 mb-0">PowerDNS Admin PHP</h1>
    <p class="muted small mb-0">Masuk untuk mengelola DNS server otoritatif.</p>
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
