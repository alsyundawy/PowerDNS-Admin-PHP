<?php

declare(strict_types=1);

/**
 * @var list<array{
 *   id: int,
 *   username: string,
 *   display_name: string,
 *   email: string,
 *   avatar_url?: string,
 *   role: string,
 *   active: int|bool,
 *   last_login_at: string|null
 * }> $users
 */
?>
<div class="panel mb-3">
  <h2 class="h6 mb-3">Tambah Pengguna Baru</h2>
  <form method="post" action="/users" class="row g-2 align-items-end">
    <?= csrfField() ?>
    <div class="col-md-2">
      <label class="form-label small" for="user-username">Username</label>
      <input class="form-control" id="user-username" name="username" placeholder="Username" required>
    </div>
    <div class="col-md-2">
      <label class="form-label small" for="user-display">Nama Tampilan</label>
      <input class="form-control" id="user-display" name="display_name" placeholder="Nama lengkap">
    </div>
    <div class="col-md-2">
      <label class="form-label small" for="user-email">Email</label>
      <input class="form-control" type="email" id="user-email" name="email" placeholder="email@example.com">
    </div>
    <div class="col-md-2">
      <label class="form-label small" for="user-role">Peran</label>
      <select class="form-select" id="user-role" name="role">
        <option value="user">User</option>
        <option value="operator">Operator</option>
        <option value="admin">Admin</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small" for="user-pass">Kata Sandi</label>
      <input
        class="form-control"
        type="password"
        id="user-pass"
        name="password"
        minlength="10"
        placeholder="Min. 10 karakter"
        required
      >
    </div>
    <div class="col-md-1">
      <input type="hidden" name="active" value="0">
      <div class="form-check pb-2">
        <input class="form-check-input" type="checkbox" id="user-active" name="active" value="1" checked>
        <label class="form-check-label small" for="user-active">Aktif</label>
      </div>
    </div>
    <div class="col-md-1">
      <button class="btn btn-primary w-100" type="submit">Tambah</button>
    </div>
  </form>
</div>

<div class="panel">
  <h2 class="h6 mb-3">Daftar Pengguna Sistem</h2>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th scope="col">Username</th>
          <th scope="col">Nama Tampilan</th>
          <th scope="col">Email</th>
          <th scope="col">Peran</th>
          <th scope="col">Status</th>
          <th scope="col">Login Terakhir</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($users as $row) :
          $rowAvatar = !empty($row['avatar_url']) ? (string) $row['avatar_url'] : '';
          $initial = strtoupper(substr((string) $row['username'], 0, 2));
      ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="user-table-avatar">
                <?php if ($rowAvatar !== '') : ?>
                  <img src="<?= e($rowAvatar) ?>" alt="Avatar" class="user-table-avatar-img">
                <?php else : ?>
                  <div class="user-table-avatar-initials"><?= e($initial) ?></div>
                <?php endif; ?>
              </div>
              <strong><?= e($row['username']) ?></strong>
            </div>
          </td>
          <td><?= e($row['display_name']) ?></td>
          <td><?= e($row['email'] ?: '–') ?></td>
          <td><span class="badge bg-secondary"><?= e($row['role']) ?></span></td>
          <td>
            <?= !empty($row['active'])
              ? '<span class="pill ok">aktif</span>'
              : '<span class="pill bad">nonaktif</span>' ?>
          </td>
          <td><?= e((string) ($row['last_login_at'] ?? '–')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
