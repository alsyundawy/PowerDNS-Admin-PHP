<div class="brand-lockup">
  <span class="brand-mark">PD</span>
  <div>
    <h1>Instalasi pertama</h1>
    <p>Database panel terpisah dari database zona PowerDNS.</p>
  </div>
</div>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="/install" class="stack">
  <?= csrf_field() ?>
  <div class="row g-2">
    <div class="col-8"><label>Host MySQL<input class="form-control" name="db_host" value="127.0.0.1" required></label></div>
    <div class="col-4"><label>Port<input class="form-control" name="db_port" value="3306" required></label></div>
  </div>
  <label>Nama database<input class="form-control" name="db_name" value="pda" required></label>
  <label>User database<input class="form-control" name="db_user" required></label>
  <label>Sandi database<input class="form-control" type="password" name="db_pass"></label>
  <label>Admin panel<input class="form-control" name="admin_user" value="admin" required></label>
  <label>Sandi admin<input class="form-control" type="password" name="admin_pass" minlength="10" required></label>
  <label>URL API PowerDNS<input class="form-control" name="pdns_url" placeholder="http://127.0.0.1:8081" required></label>
  <label>API key PowerDNS<input class="form-control" name="pdns_key" required></label>
  <button class="btn btn-primary" type="submit">Pasang</button>
</form>
