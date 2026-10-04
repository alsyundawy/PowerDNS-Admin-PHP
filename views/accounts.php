<?php

declare(strict_types=1);

/**
 * @var array<int, array<string, mixed>> $accounts
 * @var array<string, mixed> $user
 */

?>
<form method="post" action="/accounts" class="panel stack mb-3">
  <?= csrfField() ?>
  <h2 class="h6 mb-2">Tambah Akun Organisasi / Grup</h2>
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label small" for="account-name">Nama Akun</label>
      <input class="form-control" id="account-name" name="name" placeholder="Nama organisasi / klien" required>
    </div>
    <div class="col-md-3">
      <label class="form-label small" for="account-contact">Kontak</label>
      <input class="form-control" id="account-contact" name="contact" placeholder="Email / Telepon">
    </div>
    <div class="col-md-3">
      <label class="form-label small" for="account-notes">Catatan</label>
      <input class="form-control" id="account-notes" name="notes" placeholder="Catatan internal">
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary w-100" type="submit">Tambah Akun</button>
    </div>
  </div>
</form>

<div class="panel">
  <h2 class="h6 mb-3">Daftar Akun</h2>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th scope="col">Nama Akun</th>
          <th scope="col">Kontak</th>
          <th scope="col">Catatan</th>
          <th scope="col">Jumlah Zona</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($accounts as $a) : ?>
        <tr>
          <td><strong><?= e($a['name']) ?></strong></td>
          <td><?= e($a['contact'] ?: '–') ?></td>
          <td><?= e((string) ($a['notes'] ?? '–')) ?></td>
          <td><span class="badge bg-secondary"><?= (int) $a['zone_count'] ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$accounts) : ?>
        <tr>
          <td colspan="4" class="text-center py-4 muted">Belum ada akun.</td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
