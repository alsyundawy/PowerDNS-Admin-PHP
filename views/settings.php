<?php

declare(strict_types=1);

/**
 * @var string $url
 * @var string $server
 * @var bool $verify
 * @var string $appName
 * @var string|null $appLogoUrl
 * @var string $appFooterText
 * @var string $defaultTheme
 * @var int $defaultTtl
 * @var string $defaultNs
 * @var string $defaultSoaEmail
 * @var int $defaultSoaRefresh
 * @var int $defaultSoaRetry
 * @var int $defaultSoaExpire
 * @var int $defaultSoaMinimum
 * @var bool $autoPtrDefault
 * @var int $sessionLifetime
 * @var int $maxLoginAttempts
 * @var int $lockoutSeconds
 * @var bool $forceHsts
 * @var int $maxSnapshots
 * @var int $auditRetentionDays
 * @var string $rdnsPattern
 * @var string $publicResolvers
 */
?>
<form method="post" action="/settings" enctype="multipart/form-data" class="stack">
  <?= csrfField() ?>

  <!-- 1. PowerDNS Authoritative API Connection -->
  <div class="panel stack">
    <h2 class="h6 mb-2"><i class="fa-solid fa-server me-2 text-info"></i>1. Koneksi PowerDNS Authoritative API</h2>

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

    <div class="row g-3">
      <div class="col-md-6">
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
      <div class="col-md-6">
        <label class="form-label" for="setting-key">API Key Baru</label>
        <input
          class="form-control"
          type="password"
          id="setting-key"
          name="pdns_api_key"
          placeholder="Kosongkan jika tidak ingin mengubah kunci"
          autocomplete="off"
        >
        <div class="form-text">
          Dienkripsi simetris dengan AES-256-GCM menggunakan appKey internal panel.
        </div>
      </div>
    </div>

    <div class="form-check mt-2">
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

  <!-- 2. DNS Defaults & Policies -->
  <div class="panel stack">
    <h2 class="h6 mb-2"><i class="fa-solid fa-network-wired me-2 text-primary"></i>2. Kebijakan &amp; Parameter Default DNS</h2>

    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label" for="setting-default-ttl">Default TTL (Detik)</label>
        <input
          class="form-control"
          type="number"
          min="30"
          max="604800"
          id="setting-default-ttl"
          name="dns_default_ttl"
          value="<?= (int) $defaultTtl ?>"
          required
        >
        <div class="form-text">Fallback TTL untuk record baru atau impor zona tanpa TTL eksplisit (standar: 3600).</div>
      </div>
      <div class="col-md-8">
        <label class="form-label" for="setting-default-ns">Default Nameservers Otoritatif</label>
        <input
          class="form-control"
          id="setting-default-ns"
          name="dns_default_ns"
          value="<?= e($defaultNs) ?>"
          placeholder="ns1.example.com, ns2.example.com"
        >
        <div class="form-text">Daftar NS otomatis yang dipra-isi saat pembuatan zona baru (pisahkan dengan koma).</div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label" for="setting-soa-email">Default SOA Hostmaster / RNAME Email</label>
        <input
          class="form-control"
          id="setting-soa-email"
          name="dns_default_soa_email"
          value="<?= e($defaultSoaEmail) ?>"
          placeholder="hostmaster.example.com"
        >
        <div class="form-text">Email penanggung jawab zona dalam format titik (contoh: <code>admin.example.com</code>).</div>
      </div>
      <div class="col-md-6">
        <div class="form-check pt-4">
          <input
            class="form-check-input"
            type="checkbox"
            id="setting-auto-ptr"
            name="dns_auto_ptr_default"
            value="1"
            <?= $autoPtrDefault ? 'checked' : '' ?>
          >
          <label class="form-check-label" for="setting-auto-ptr">
            Centang opsi <strong>Auto-PTR Sync</strong> secara default saat membuka zona forward
          </label>
        </div>
      </div>
    </div>

    <div class="p-3 rounded bg-dark border border-secondary-subtle">
      <span class="d-block small text-light fw-bold mb-2">Parameter Waktu Siklus SOA (RFC 1035 Standards):</span>
      <div class="row g-2">
        <div class="col-sm-3">
          <label class="form-label small text-secondary" for="soa-refresh">Refresh (detik)</label>
          <input class="form-control form-control-sm" type="number" id="soa-refresh" name="dns_default_soa_refresh" value="<?= (int) $defaultSoaRefresh ?>">
        </div>
        <div class="col-sm-3">
          <label class="form-label small text-secondary" for="soa-retry">Retry (detik)</label>
          <input class="form-control form-control-sm" type="number" id="soa-retry" name="dns_default_soa_retry" value="<?= (int) $defaultSoaRetry ?>">
        </div>
        <div class="col-sm-3">
          <label class="form-label small text-secondary" for="soa-expire">Expire (detik)</label>
          <input class="form-control form-control-sm" type="number" id="soa-expire" name="dns_default_soa_expire" value="<?= (int) $defaultSoaExpire ?>">
        </div>
        <div class="col-sm-3">
          <label class="form-label small text-secondary" for="soa-minimum">Negative TTL (detik)</label>
          <input class="form-control form-control-sm" type="number" id="soa-minimum" name="dns_default_soa_minimum" value="<?= (int) $defaultSoaMinimum ?>">
        </div>
      </div>
    </div>
  </div>

  <!-- 3. Branding and Appearance Customization -->
  <div class="panel stack">
    <h2 class="h6 mb-2"><i class="fa-solid fa-palette me-2 text-warning"></i>3. Identitas &amp; Branding Panel</h2>

    <div class="row g-3">
      <div class="col-md-8">
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
      <div class="col-md-4">
        <label class="form-label" for="setting-theme">Tema Default Antarmuka</label>
        <select class="form-select" id="setting-theme" name="app_default_theme">
          <option value="dark" <?= $defaultTheme === 'dark' ? 'selected' : '' ?>>OLED Dark (Visual Subnet Calc)</option>
          <option value="light" <?= $defaultTheme === 'light' ? 'selected' : '' ?>>Daylight Light (WCAG AAA)</option>
        </select>
        <div class="form-text">Tema awal untuk pengunjung dan sesi pengguna baru.</div>
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
                Hapus logo kustom &amp; kembalikan ke ikon perisai default
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

  <!-- 4. Security, Session & Rate Limiting Policies -->
  <div class="panel stack">
    <h2 class="h6 mb-2"><i class="fa-solid fa-shield-halved me-2 text-danger"></i>4. Keamanan, Sesi &amp; Kebijakan Login</h2>

    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label" for="setting-session">Masa Kedaluwarsa Sesi (Menit)</label>
        <input
          class="form-control"
          type="number"
          min="5"
          max="10080"
          id="setting-session"
          name="session_lifetime_minutes"
          value="<?= (int) $sessionLifetime ?>"
          required
        >
        <div class="form-text">Batas idle time sebelum pengguna otomatis logout (default: 120 menit).</div>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="setting-max-attempts">Batas Percobaan Login Gagal</label>
        <input
          class="form-control"
          type="number"
          min="1"
          max="50"
          id="setting-max-attempts"
          name="login_max_attempts"
          value="<?= (int) $maxLoginAttempts ?>"
          required
        >
        <div class="form-text">Maksimal kesalahan sandi berturut-turut sebelum lockout (default: 5).</div>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="setting-lockout">Durasi Lockout (Detik)</label>
        <input
          class="form-control"
          type="number"
          min="30"
          max="86400"
          id="setting-lockout"
          name="login_lockout_seconds"
          value="<?= (int) $lockoutSeconds ?>"
          required
        >
        <div class="form-text">Lama penalti pemblokiran brute-force (default: 900 detik / 15 menit).</div>
      </div>
    </div>

    <div class="form-check mt-2">
      <input
        class="form-check-input"
        type="checkbox"
        id="setting-hsts"
        name="security_force_hsts"
        value="1"
        <?= $forceHsts ? 'checked' : '' ?>
      >
      <label class="form-check-label" for="setting-hsts">
        Kirim Header <code>Strict-Transport-Security (HSTS)</code> pada setiap koneksi HTTPS (max-age=31536000)
      </label>
    </div>
  </div>

  <!-- 5. History Retention & Snapshots -->
  <div class="panel stack">
    <h2 class="h6 mb-2"><i class="fa-solid fa-clock-rotate-left me-2 text-success"></i>5. Retensi Riwayat Zona &amp; Jejak Audit</h2>

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label" for="setting-max-snapshots">Batas Maksimal Snapshot Per Zona</label>
        <input
          class="form-control"
          type="number"
          min="1"
          max="500"
          id="setting-max-snapshots"
          name="history_max_snapshots"
          value="<?= (int) $maxSnapshots ?>"
          required
        >
        <div class="form-text">Jumlah titik pemulihan (rollback) otomatis yang disimpan untuk setiap zona DNS.</div>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="setting-audit-retention">Masa Retensi Log Jejak Audit (Hari)</label>
        <input
          class="form-control"
          type="number"
          min="1"
          max="3650"
          id="setting-audit-retention"
          name="audit_retention_days"
          value="<?= (int) $auditRetentionDays ?>"
          required
        >
        <div class="form-text">Riwayat aktivitas pengguna dan perubahan API yang disimpan di tabel audit log.</div>
      </div>
    </div>
  </div>

  <!-- 6. Diagnostic Tools & rDNS Settings -->
  <div class="panel stack">
    <h2 class="h6 mb-2"><i class="fa-solid fa-screwdriver-wrench me-2 text-info"></i>6. Pengaturan Alat Bantu Jaringan &amp; rDNS</h2>

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label" for="setting-rdns-pattern">Pola Naming Default Batch PTR Generator</label>
        <input
          class="form-control font-monospace"
          id="setting-rdns-pattern"
          name="rdns_default_naming_pattern"
          value="<?= e($rdnsPattern) ?>"
          required
        >
        <div class="form-text">
          Makro tersedia: <code>[ID]</code>, <code>[HEX]</code>, <code>[HEX16]</code>, <code>[IP]</code>, <code>[IP_DASH]</code>, <code>[OCTET4]</code>, <code>[DOMAIN]</code>.
        </div>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="setting-resolvers">Daftar Recursive DNS Resolvers Publik</label>
        <input
          class="form-control font-monospace"
          id="setting-resolvers"
          name="dns_public_resolvers"
          value="<?= e($publicResolvers) ?>"
          required
        >
        <div class="form-text">Daftar IP resolver pembanding untuk alat DNS Lookup &amp; Propagation Inspector (pisahkan koma).</div>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center pt-2">
    <button class="btn btn-primary px-4 py-2" type="submit">
      <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Semua Pengaturan
    </button>
    <a class="btn btn-outline-info" href="/backup">
      <i class="fa-solid fa-database me-1"></i> Buka Cadangan &amp; Pemulihan
    </a>
  </div>
</form>
