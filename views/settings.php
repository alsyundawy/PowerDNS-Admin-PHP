<?php

declare(strict_types=1);

/**
 * @var string $url
 * @var string $server
 * @var bool $verify
 * @var string $appName
 * @var string|null $appLogoUrl
 * @var string $appFooterText
 */
?>
<form method="post" action="/settings" enctype="multipart/form-data" class="stack">
  <?= csrfField() ?>

  <!-- PowerDNS Authoritative API Connection -->
  <div class="panel stack">
    <h2 class="h6 mb-2"><i class="fa-solid fa-server me-2 text-info"></i>Koneksi PowerDNS Authoritative API</h2>

    <div>
      <label class="form-label" for="setting-url">URL API PowerDNS</label>
      <input
        class="form-control"
        id="setting-url"
        name="pdns_api_url"
        value="<?= e($url) ?>"
        placeholder="http://127.0.0.1:8081"
        required
      >
      <div class="form-text">
        Endpoint webserver PowerDNS yang dikonfigurasi di <code>pdns.conf</code> (webserver=yes).
      </div>
    </div>

    <div>
      <label class="form-label" for="setting-server">Server ID</label>
      <input
        class="form-control"
        id="setting-server"
        name="pdns_server_id"
        value="<?= e($server) ?>"
        placeholder="localhost"
      >
      <div class="form-text">
        Default server id PowerDNS Authoritative adalah <code>localhost</code>.
      </div>
    </div>

    <div>
      <label class="form-label" for="setting-key">API Key Baru</label>
      <input
        class="form-control"
        type="password"
        id="setting-key"
        name="pdns_api_key"
        placeholder="Kosongkan jika tidak ingin mengubah kunci saat ini"
        autocomplete="off"
      >
      <div class="form-text">
        Kunci rahasia dienkripsi secara simetris dengan AES-256-GCM menggunakan appKey internal.
      </div>
    </div>

    <div class="form-check">
      <input
        class="form-check-input"
        type="checkbox"
        id="setting-tls"
        name="pdns_verify_tls"
        value="1"
        <?= $verify ? 'checked' : '' ?>
      >
      <label class="form-check-label" for="setting-tls">
        Verifikasi sertifikat TLS/SSL (Wajib aktif untuk endpoint HTTPS produksi)
      </label>
    </div>
  </div>

  <!-- Branding and Appearance Customization -->
  <div class="panel stack">
    <h2 class="h6 mb-2"><i class="fa-solid fa-palette me-2 text-warning"></i>Identitas & Branding Panel</h2>

    <div>
      <label class="form-label" for="setting-app-name">Nama Aplikasi</label>
      <input
        class="form-control"
        id="setting-app-name"
        name="app_name"
        value="<?= e($appName) ?>"
        placeholder="PowerDNS Admin"
        required
      >
      <div class="form-text">
        Nama panel yang tampil pada bilah navigasi, header sidebar, dan judul tab browser.
      </div>
    </div>

    <div>
      <label class="form-label" for="setting-logo-file">Logo Kustom Aplikasi</label>
      <?php if (!empty($appLogoUrl)) : ?>
        <div class="d-flex align-items-center gap-3 p-3 mb-2 rounded bg-dark border border-secondary-subtle">
          <div class="logo-preview-box">
            <img src="<?= e($appLogoUrl) ?>" alt="Logo Aplikasi" class="custom-logo-preview">
          </div>
          <div>
            <span class="d-block small text-light fw-bold">Logo Kustom Aktif</span>
            <span class="small text-secondary font-monospace"><?= e($appLogoUrl) ?></span>
            <div class="form-check mt-1">
              <input class="form-check-input" type="checkbox" id="remove-logo" name="remove_logo" value="1">
              <label class="form-check-label small text-danger" for="remove-logo">
                Hapus logo kustom & kembalikan ke ikon perisai default
              </label>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="row g-2">
        <div class="col-md-6">
          <label class="form-label small text-secondary" for="setting-logo-file">Unggah Berkas Logo (PNG, SVG, WEBP)</label>
          <input
            class="form-control form-control-sm"
            type="file"
            id="setting-logo-file"
            name="app_logo_file"
            accept="image/png,image/jpeg,image/webp,image/svg+xml"
          >
          <div class="form-text small">Maksimal 2 MB. Disarankan rasio persegi atau horizontal proporsional.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label small text-secondary" for="setting-logo-url">Atau Masukkan URL Logo Eksternal</label>
          <input
            class="form-control form-control-sm"
            id="setting-logo-url"
            name="app_logo_url"
            value="<?= e($appLogoUrl ?? '') ?>"
            placeholder="https://contoh.com/logo.svg"
          >
          <div class="form-text small">Mendukung protokol HTTPS dan jalur relatif.</div>
        </div>
      </div>
    </div>

    <div>
      <label class="form-label" for="setting-footer">Teks Footer Kustom</label>
      <input
        class="form-control"
        id="setting-footer"
        name="app_footer_text"
        value="<?= e($appFooterText) ?>"
        placeholder="PowerDNS-Admin-PHP &bull; Native High-Performance DNS Panel"
      >
      <div class="form-text">
        Teks informasi atau hak cipta yang muncul di bagian paling bawah dashboard dan halaman login.
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center pt-1">
    <button class="btn btn-primary px-4 py-2" type="submit">
      <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Semua Pengaturan
    </button>
    <a class="btn btn-outline-info" href="/backup">
      <i class="fa-solid fa-database me-1"></i> Buka Cadangan & Pemulihan
    </a>
  </div>
</form>
