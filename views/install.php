<?php

declare(strict_types=1);

/**
 * @var string|null $error
 */

?>
<div class="brand-lockup mb-4">
  <span class="brand-mark">PD</span>
  <div>
    <h1 class="h5 mb-0">Instalasi PowerDNS Admin</h1>
    <p class="muted small mb-0">Database panel terpisah dari database zona PowerDNS.</p>
  </div>
</div>

<?php if (!empty($error)) : ?>
  <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="/install" class="stack">
  <?= csrfField() ?>
  <div class="row g-2">
    <div class="col-8">
      <label class="form-label small" for="db-host">Host MySQL / MariaDB</label>
      <input class="form-control" id="db-host" name="db_host" value="127.0.0.1" required>
    </div>
    <div class="col-4">
      <label class="form-label small" for="db-port">Port</label>
      <input class="form-control" type="number" id="db-port" name="db_port" value="3306" required>
    </div>
  </div>

  <div>
    <label class="form-label small" for="db-name">Nama Database Panel</label>
    <input class="form-control" id="db-name" name="db_name" value="pda" required>
  </div>

  <div class="row g-2">
    <div class="col-6">
      <label class="form-label small" for="db-user">User Database</label>
      <input class="form-control" id="db-user" name="db_user" required>
    </div>
    <div class="col-6">
      <label class="form-label small" for="db-pass">Sandi Database</label>
      <input class="form-control" type="password" id="db-pass" name="db_pass">
    </div>
  </div>

  <div class="row g-2">
    <div class="col-6">
      <label class="form-label small" for="admin-user">Username Admin</label>
      <input class="form-control" id="admin-user" name="admin_user" value="admin" required>
    </div>
    <div class="col-6">
      <label class="form-label small" for="admin-pass">Sandi Admin</label>
      <input class="form-control" type="password" id="admin-pass" name="admin_pass"
             minlength="10" placeholder="Min. 10 karakter" required>
    </div>
  </div>

  <div>
    <label class="form-label small" for="pdns-url">URL API PowerDNS</label>
    <input class="form-control" id="pdns-url" name="pdns_url" placeholder="http://127.0.0.1:8081" required>
  </div>

  <div>
    <label class="form-label small" for="pdns-key">API Key PowerDNS</label>
    <input class="form-control" type="password" id="pdns-key" name="pdns_key"
           placeholder="Nilai api-key di pdns.conf" required>
  </div>

  <button class="btn btn-primary w-100 py-2 mt-2" type="submit">Pasang &amp; Inisialisasi Sistem</button>
</form>
