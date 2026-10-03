<?php if ($plain): ?><div class="alert alert-warning">Salin key ini sekarang: <code><?= e($plain) ?></code></div><?php endif; ?>
<form method="post" action="/apikeys" class="panel stack mb-3">
  <?= csrf_field() ?>
  <div class="row g-2">
    <div class="col-md-6"><input class="form-control" name="name" placeholder="Nama key" required></div>
    <div class="col-md-4"><select class="form-select" name="role"><option>user</option><?php if ($user['role']==='admin'): ?><option>operator</option><option>admin</option><?php endif; ?></select></div>
    <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Buat</button></div>
  </div>
  <p class="muted">Key disimpan sebagai SHA-256. Header pemakaian: X-API-Key. Bukan api-key PowerDNS.</p>
</form>
<div class="panel">
  <table class="table">
    <thead><tr><th>Nama</th><th>Prefiks</th><th>Peran</th><th>Pemilik</th><th>Dipakai</th></tr></thead>
    <tbody>
    <?php foreach ($keys as $k): ?>
      <tr><td><?= e($k['name']) ?></td><td><code><?= e($k['key_prefix']) ?></code></td><td><?= e($k['role']) ?></td><td><?= e($k['username']) ?></td><td><?= e((string) $k['last_used_at']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
