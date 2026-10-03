<div class="brand-lockup">
  <span class="brand-mark">PD</span>
  <div>
    <h1>PowerDNS-Admin-PHP</h1>
    <p>Masuk untuk mengelola zona otoritatif.</p>
  </div>
</div>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="/login" class="stack">
  <?= csrf_field() ?>
  <label>Username<input class="form-control" name="username" autocomplete="username" required></label>
  <label>Sandi<input class="form-control" type="password" name="password" autocomplete="current-password" required></label>
  <button class="btn btn-primary" type="submit">Masuk</button>
</form>
