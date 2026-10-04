<?php

declare(strict_types=1);

/**
 * @var array<string, mixed> $user
 * @var array<string, mixed> $profile
 */

$avatarUrl = !empty($profile['avatar_url']) ? (string) $profile['avatar_url'] : '';
$displayName = !empty($profile['display_name']) ? (string) $profile['display_name'] : (string) $profile['username'];
$initials = strtoupper(substr((string) $profile['username'], 0, 2));
?>
<div class="row g-3">
  <div class="col-lg-4">
    <!-- User Profile Summary Card -->
    <div class="panel text-center mb-3">
      <div class="avatar-wrapper mx-auto mb-3">
        <?php if ($avatarUrl !== '') : ?>
          <img src="<?= e($avatarUrl) ?>" alt="Avatar" class="profile-avatar-img">
        <?php else : ?>
          <div class="profile-avatar-placeholder">
            <span><?= e($initials) ?></span>
          </div>
        <?php endif; ?>
      </div>
      <h2 class="h5 mb-1"><?= e($displayName) ?></h2>
      <p class="text-secondary small mb-2">@<?= e((string) $profile['username']) ?></p>
      <div class="d-flex justify-content-center gap-2 mb-3">
        <span class="badge bg-primary text-uppercase"><?= e((string) $profile['role']) ?></span>
        <?php if (!empty($profile['active'])) : ?>
          <span class="badge bg-success-subtle text-success">Aktif</span>
        <?php else : ?>
          <span class="badge bg-danger-subtle text-danger">Nonaktif</span>
        <?php endif; ?>
      </div>

      <div class="text-start border-top border-secondary-subtle pt-3 small text-secondary">
        <div class="d-flex justify-content-between mb-1">
          <span><i class="fa-solid fa-clock-rotate-left me-1"></i> Login Terakhir:</span>
          <span class="text-light"><?= e((string) ($profile['last_login_at'] ?? 'Belum pernah')) ?></span>
        </div>
        <div class="d-flex justify-content-between">
          <span><i class="fa-solid fa-calendar me-1"></i> Terdaftar Sejak:</span>
          <span class="text-light"><?= e(substr((string) ($profile['created_at'] ?? '–'), 0, 10)) ?></span>
        </div>
      </div>
    </div>

    <!-- Avatar Upload / Removal Form -->
    <div class="panel">
      <h3 class="h6 mb-3"><i class="fa-solid fa-camera me-2 text-info"></i>Foto Profil</h3>
      <form method="post" action="/profile/avatar" enctype="multipart/form-data" class="stack">
        <?= csrfField() ?>
        <div>
          <label class="form-label small" for="avatar-file">Pilih Gambar Baru (PNG, JPG, WEBP, SVG)</label>
          <input
            class="form-control form-control-sm"
            type="file"
            id="avatar-file"
            name="avatar"
            accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml"
            required
          >
          <div class="form-text small">Maksimal 2 MB. Gambar akan disimpan secara aman.</div>
        </div>
        <button class="btn btn-sm btn-primary w-100" type="submit">
          <i class="fa-solid fa-upload me-1"></i> Unggah Foto Profil
        </button>
      </form>

      <?php if ($avatarUrl !== '') : ?>
        <form method="post" action="/profile/avatar/delete" class="mt-2"
              onsubmit="return confirm('Hapus foto profil dan gunakan avatar inisial default?');">
          <?= csrfField() ?>
          <button class="btn btn-sm btn-outline-danger w-100" type="submit">
            <i class="fa-solid fa-trash me-1"></i> Hapus Foto Profil
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-8">
    <!-- User Information Form -->
    <div class="panel mb-3">
      <h3 class="h6 mb-3"><i class="fa-solid fa-user-pen me-2 text-info"></i>Informasi Akun</h3>
      <form method="post" action="/profile/update" class="stack">
        <?= csrfField() ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small" for="prof-username">Username</label>
            <input class="form-control" id="prof-username" value="<?= e((string) $profile['username']) ?>" disabled>
            <div class="form-text small">Username sistem permanen dan tidak dapat diubah.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label small" for="prof-role">Peran Sistem</label>
            <input class="form-control" id="prof-role" value="<?= e((string) $profile['role']) ?>" disabled>
            <div class="form-text small">Tingkat hak akses pada panel administrasi.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label small" for="prof-display">Nama Tampilan</label>
            <input
              class="form-control"
              id="prof-display"
              name="display_name"
              value="<?= e((string) $profile['display_name']) ?>"
              placeholder="Nama Lengkap"
              required
            >
          </div>
          <div class="col-md-6">
            <label class="form-label small" for="prof-email">Alamat Email</label>
            <input
              class="form-control"
              type="email"
              id="prof-email"
              name="email"
              value="<?= e((string) $profile['email']) ?>"
              placeholder="nama@domain.com"
            >
          </div>
        </div>
        <div class="pt-2">
          <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Perubahan Profil
          </button>
        </div>
      </form>
    </div>

    <!-- Password Change Form -->
    <div class="panel">
      <h3 class="h6 mb-3"><i class="fa-solid fa-key me-2 text-warning"></i>Ganti Kata Sandi</h3>
      <form method="post" action="/profile/password" class="stack">
        <?= csrfField() ?>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label small" for="pass-curr">Kata Sandi Saat Ini</label>
            <input
              class="form-control"
              type="password"
              id="pass-curr"
              name="current_password"
              autocomplete="current-password"
              required
            >
          </div>
          <div class="col-md-6">
            <label class="form-label small" for="pass-new">Kata Sandi Baru</label>
            <input
              class="form-control"
              type="password"
              id="pass-new"
              name="new_password"
              minlength="8"
              autocomplete="new-password"
              placeholder="Minimal 8 karakter"
              required
            >
          </div>
          <div class="col-md-6">
            <label class="form-label small" for="pass-conf">Konfirmasi Kata Sandi Baru</label>
            <input
              class="form-control"
              type="password"
              id="pass-conf"
              name="confirm_password"
              minlength="8"
              autocomplete="new-password"
              placeholder="Ulangi kata sandi baru"
              required
            >
          </div>
        </div>
        <div class="pt-2">
          <button class="btn btn-warning" type="submit">
            <i class="fa-solid fa-shield-halved me-1"></i> Perbarui Kata Sandi
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
