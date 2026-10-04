<?php

declare(strict_types=1);

/**
 * @var array<int, array<string, mixed>> $keys
 * @var array<string, mixed> $user
 * @var string $plain
 */

?>
<?php if (!empty($plain)) : ?>
  <div class="alert alert-warning" role="alert">
    <strong>Simpan API Key ini sekarang:</strong>
    <code class="user-select-all d-block mt-2 p-2 bg-light border rounded"><?= e($plain) ?></code>
    <small class="d-block mt-1">Kunci lengkap tidak akan ditampilkan lagi setelah Anda meninggalkan halaman ini.</small>
  </div>
<?php endif; ?>

<form method="post" action="/apikeys" class="panel stack mb-3">
  <?= csrfField() ?>
  <h2 class="h6 mb-2">Buat API Key Baru</h2>
  <div class="row g-2 align-items-end">
    <div class="col-md-6">
      <label class="form-label small" for="key-name">Nama Identifier Key</label>
      <input class="form-control" id="key-name" name="name" placeholder="Contoh: CI/CD Deployer" required>
    </div>
    <div class="col-md-4">
      <label class="form-label small" for="key-role">Peran Otorisasi</label>
      <select class="form-select" id="key-role" name="role">
        <option value="user">User (Hanya zona yang diizinkan)</option>
        <?php if (($user['role'] ?? '') === 'admin') : ?>
          <option value="operator">Operator</option>
          <option value="admin">Admin (Akses penuh)</option>
        <?php endif; ?>
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary w-100" type="submit">Buat Key</button>
    </div>
  </div>
  <p class="muted small mb-0">
    Key panel disimpan sebagai hash SHA-256. Gunakan di header HTTP: <code>X-API-Key: &lt;key&gt;</code>
    saat memanggil endpoint <code>/api/v1/...</code> pada panel ini.
  </p>
</form>

<div class="panel">
  <h2 class="h6 mb-3">Daftar API Key Panel</h2>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th scope="col">Nama</th>
          <th scope="col">Prefiks</th>
          <th scope="col">Peran</th>
          <th scope="col">Pemilik</th>
          <th scope="col">Terakhir Dipakai</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($keys as $k) : ?>
        <tr>
          <td><strong><?= e($k['name']) ?></strong></td>
          <td><code><?= e($k['key_prefix']) ?>...</code></td>
          <td><span class="badge bg-secondary"><?= e($k['role']) ?></span></td>
          <td><?= e($k['username']) ?></td>
          <td><?= e((string) ($k['last_used_at'] ?: 'belum pernah')) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$keys) : ?>
        <tr>
          <td colspan="5" class="text-center py-4 muted">Belum ada API key aktif.</td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
