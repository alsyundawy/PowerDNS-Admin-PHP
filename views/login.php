<?php

declare(strict_types=1);

/**
 * @var string|null $error
 */

$logoUrl = appLogoUrl();
$appName = appName();
?>
<?php if (!empty($is2FaChallenge)) : ?>
  <div class="brand-lockup mb-4 text-center">
    <span class="brand-mark mb-2 mx-auto"><i class="fa-solid fa-key"></i></span>
    <div>
      <h1 class="h5 mb-1">Autentikasi Dua Faktor (2FA)</h1>
      <p class="muted small mb-0">Masukkan 6-digit kode autentikator atau kode cadangan darurat Anda.</p>
    </div>
  </div>

  <?php if (!empty($error)) : ?>
    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" action="/login/2fa" class="stack">
    <?= csrfField() ?>
    <div>
      <label class="form-label small" for="totp-code">Kode 6-Digit / Kode Cadangan</label>
      <input class="form-control text-center font-monospace fs-5 py-2" id="totp-code" name="totp_code" inputmode="numeric" placeholder="123456" autocomplete="one-time-code" required autofocus>
    </div>
    <button class="btn btn-primary w-100 py-2 mt-2" type="submit">
      <i class="fa-solid fa-shield-halved me-1"></i> Verifikasi & Masuk
    </button>
    <a href="/login/cancel-2fa" class="btn btn-sm btn-outline-secondary w-100 mt-2">
      Batal & Kembali ke Login
    </a>
  </form>
<?php else : ?>
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
<?php endif; ?>

<div class="text-center mt-4 pt-3 border-top border-secondary-subtle">
  <small class="text-secondary d-block"><?= e(appFooterText()) ?></small>
  <small class="text-secondary opacity-75">v0.3.0</small>
</div>
