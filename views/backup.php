<?php

declare(strict_types=1);

/**
 * @var array<string, mixed> $user
 * @var array<string, int> $tableCounts
 * @var int $zoneCount
 * @var bool $apiOk
 */

$totalRows = array_sum($tableCounts);
?>
<div class="stat-grid mb-3">
  <article>
    <span><i class="fa-solid fa-database text-info"></i> Tabel Metadata</span>
    <strong><?= count($tableCounts) ?> <small class="text-secondary fs-6">(<?= $totalRows ?> baris)</small></strong>
  </article>
  <article>
    <span><i class="fa-solid fa-sliders text-success"></i> Opsi Pengaturan</span>
    <strong><?= (int) ($tableCounts['settings'] ?? 0) ?></strong>
  </article>
  <article>
    <span><i class="fa-solid fa-globe text-purple"></i> Zona PowerDNS</span>
    <strong><?= (int) $zoneCount ?></strong>
  </article>
  <article>
    <span><i class="fa-solid fa-server text-warning"></i> Status API</span>
    <strong>
      <?= $apiOk
        ? '<span class="text-success fs-6"><i class="fa-solid fa-circle-check me-1"></i>Tersambung</span>'
        : '<span class="text-danger fs-6"><i class="fa-solid fa-circle-xmark me-1"></i>Terputus</span>' ?>
    </strong>
  </article>
</div>

<div class="row g-3">
  <!-- Database Metadata Backup & Restore -->
  <div class="col-lg-4">
    <div class="panel h-100 d-flex flex-column justify-content-between">
      <div>
        <div class="d-flex align-items-center gap-2 mb-2">
          <i class="fa-solid fa-database text-info fs-5"></i>
          <h2 class="h6 mb-0">Database Metadata (SQL)</h2>
        </div>
        <p class="text-secondary small mb-3">
          Mencadangkan 13 tabel internal panel (pengguna, akun, riwayat audit, snapshot, dan template)
          dalam format file SQL terenkapsulasi transaksi aman.
        </p>

        <div class="mb-3">
          <a class="btn btn-outline-info w-100 py-2" href="/backup/download/db">
            <i class="fa-solid fa-download me-1"></i> Unduh Cadangan SQL
          </a>
        </div>

        <hr class="border-secondary-subtle my-3">

        <h3 class="small fw-bold text-uppercase text-secondary mb-2">Pulihkan Database</h3>
        <form method="post" action="/backup/restore/db" enctype="multipart/form-data" class="stack"
              onsubmit="return confirm('PERINGATAN: Memulihkan database akan menimpa tabel metadata aplikasi saat ini. Lanjutkan?');">
          <?= csrfField() ?>
          <div>
            <label class="form-label small" for="db-file">Pilih Berkas Cadangan (.sql)</label>
            <input class="form-control form-control-sm" type="file" id="db-file" name="db_file" accept=".sql" required>
          </div>
          <button class="btn btn-sm btn-danger w-100" type="submit">
            <i class="fa-solid fa-rotate-left me-1"></i> Pulihkan Metadata SQL
          </button>
        </form>
      </div>
      <div class="mt-3 pt-2 border-top border-secondary-subtle">
        <small class="text-secondary d-block">
          <i class="fa-solid fa-shield-halved me-1"></i> Diperiksa otomatis terhadap injeksi perintah terlarang.
        </small>
      </div>
    </div>
  </div>

  <!-- Settings & Config Backup & Restore -->
  <div class="col-lg-4">
    <div class="panel h-100 d-flex flex-column justify-content-between">
      <div>
        <div class="d-flex align-items-center gap-2 mb-2">
          <i class="fa-solid fa-sliders text-success fs-5"></i>
          <h2 class="h6 mb-0">Pengaturan & Konfigurasi (JSON)</h2>
        </div>
        <p class="text-secondary small mb-3">
          Mencadangkan seluruh konfigurasi panel, preferensi branding, endpoint PowerDNS, dan footer
          dalam format JSON standar portabel.
        </p>

        <div class="mb-3">
          <a class="btn btn-outline-success w-100 py-2" href="/backup/download/config">
            <i class="fa-solid fa-download me-1"></i> Unduh Konfigurasi JSON
          </a>
        </div>

        <hr class="border-secondary-subtle my-3">

        <h3 class="small fw-bold text-uppercase text-secondary mb-2">Pulihkan Pengaturan</h3>
        <form method="post" action="/backup/restore/config" enctype="multipart/form-data" class="stack"
              onsubmit="return confirm('Perbarui pengaturan panel dengan berkas konfigurasi JSON ini?');">
          <?= csrfField() ?>
          <div>
            <label class="form-label small" for="config-file">Pilih Berkas Konfigurasi (.json)</label>
            <input
              class="form-control form-control-sm"
              type="file"
              id="config-file"
              name="config_file"
              accept=".json"
              required
            >
          </div>
          <button class="btn btn-sm btn-success w-100" type="submit">
            <i class="fa-solid fa-rotate-left me-1"></i> Pulihkan Pengaturan JSON
          </button>
        </form>
      </div>
      <div class="mt-3 pt-2 border-top border-secondary-subtle">
        <small class="text-secondary d-block">
          <i class="fa-solid fa-circle-info me-1"></i> Kunci rahasia API tetap terlindungi dengan aman.
        </small>
      </div>
    </div>
  </div>

  <!-- PowerDNS Zones Snapshot Backup & Restore -->
  <div class="col-lg-4">
    <div class="panel h-100 d-flex flex-column justify-content-between">
      <div>
        <div class="d-flex align-items-center gap-2 mb-2">
          <i class="fa-solid fa-globe text-purple fs-5"></i>
          <h2 class="h6 mb-0">Zona PowerDNS (Snapshot JSON)</h2>
        </div>
        <p class="text-secondary small mb-3">
          Mengekspor seluruh zona otoritatif beserta seluruh kumpulan RRset (A, AAAA, MX, TXT, SOA, NS, dll.)
          dan format BIND langsung via PowerDNS API v1.
        </p>

        <div class="mb-3">
          <a class="btn btn-outline-primary w-100 py-2 <?= !$apiOk ? 'disabled' : '' ?>" href="/backup/download/zones">
            <i class="fa-solid fa-download me-1"></i> Unduh Snapshot Semua Zona
          </a>
        </div>

        <hr class="border-secondary-subtle my-3">

        <h3 class="small fw-bold text-uppercase text-secondary mb-2">Pulihkan / Impor Zona</h3>
        <form method="post" action="/backup/restore/zones" enctype="multipart/form-data" class="stack"
              onsubmit="return confirm('Impor kumpulan zona ke PowerDNS Authoritative Server? Record yang ada akan disinkronkan.');">
          <?= csrfField() ?>
          <div>
            <label class="form-label small" for="zones-file">Pilih Berkas Zona (.json)</label>
            <input
              class="form-control form-control-sm"
              type="file"
              id="zones-file"
              name="zones_file"
              accept=".json"
              required
              <?= !$apiOk ? 'disabled' : '' ?>
            >
          </div>
          <button class="btn btn-sm btn-primary w-100" type="submit" <?= !$apiOk ? 'disabled' : '' ?>>
            <i class="fa-solid fa-cloud-arrow-up me-1"></i> Impor & Sinkronkan Zona
          </button>
        </form>
      </div>
      <div class="mt-3 pt-2 border-top border-secondary-subtle">
        <small class="text-secondary d-block">
          <i class="fa-solid fa-bolt me-1"></i> Membutuhkan koneksi aktif ke PowerDNS Authoritative API.
        </small>
      </div>
    </div>
  </div>
</div>

<div class="panel mt-3">
  <h3 class="h6 mb-2"><i class="fa-solid fa-terminal me-2 text-info"></i>Automasi Cadangan Harian (Linux Cron)</h3>
  <p class="text-secondary small mb-2">
    Anda dapat mengautomasi pencadangan database MySQL dan metadata secara berkala menggunakan cron job Linux:
  </p>
  <pre class="p-3 rounded bg-dark border border-secondary text-light small mb-0 font-monospace"
  ><code># Cadangan database harian setiap pukul 02:00 WIB
0 2 * * * mysqldump -u pdns -p'rahasia' pdns_admin | gzip &gt; /var/backups/pdns_admin_$(date +\%Y\%m\%d).sql.gz</code></pre>
</div>
