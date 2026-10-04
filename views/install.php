<?php

declare(strict_types=1);

/**
 * @var string|null $error
 */

?>
<div class="brand-lockup mb-4">
  <span class="brand-mark">PD</span>
  <div>
    <h1 class="h5 mb-0">PowerDNS Admin Installation</h1>
    <p class="muted small mb-0">Panel database is isolated from the PowerDNS authoritative database.</p>
  </div>
</div>

<?php if (!empty($error)) : ?>
  <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="/install" class="stack">
  <?= csrfField() ?>
  <div class="row g-2">
    <div class="col-8">
      <label class="form-label small" for="db-host">MySQL / MariaDB Host</label>
      <input class="form-control" id="db-host" name="db_host" value="127.0.0.1" required>
    </div>
    <div class="col-4">
      <label class="form-label small" for="db-port">Port</label>
      <input class="form-control" type="number" id="db-port" name="db_port" value="3306" required>
    </div>
  </div>

  <div>
    <label class="form-label small" for="db-name">Panel Database Name</label>
    <input class="form-control" id="db-name" name="db_name" value="pda" required>
  </div>

  <div class="row g-2">
    <div class="col-6">
      <label class="form-label small" for="db-user">Database User</label>
      <input class="form-control" id="db-user" name="db_user" required>
    </div>
    <div class="col-6">
      <label class="form-label small" for="db-pass">Database Password</label>
      <input class="form-control" type="password" id="db-pass" name="db_pass">
    </div>
  </div>

  <div class="row g-2">
    <div class="col-6">
      <label class="form-label small" for="admin-user">Admin Username</label>
      <input class="form-control" id="admin-user" name="admin_user" value="admin" required>
    </div>
    <div class="col-6">
      <label class="form-label small" for="admin-pass">Admin Password</label>
      <input class="form-control" type="password" id="admin-pass" name="admin_pass"
             minlength="10" placeholder="Min. 10 characters" required>
    </div>
  </div>

  <div>
    <label class="form-label small" for="pdns-url">PowerDNS API URL</label>
    <input class="form-control" id="pdns-url" name="pdns_url" placeholder="http://127.0.0.1:8081" required>
  </div>

  <div>
    <label class="form-label small" for="pdns-key">PowerDNS API Key</label>
    <input class="form-control" type="password" id="pdns-key" name="pdns_key"
           placeholder="api-key value from pdns.conf" required>
  </div>

  <button class="btn btn-primary w-100 py-2 mt-2" type="submit">Install &amp; Initialize System</button>
</form>
