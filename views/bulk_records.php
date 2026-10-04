<?php

/**
 * Cross-Zone Bulk Record Operations View.
 * Enables mass record searching, batch IP migrations, and atomic replacements across all authoritative zones.
 */

declare(strict_types=1);

/**
 * @var array<string, mixed> $user
 * @var string $query
 * @var string $typeFilter
 * @var array<int, array<string, mixed>> $results
 * @var array{zones_modified?: int, records_replaced?: int, errors?: array<int, string>}|null $replaceResult
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h2 class="h5 mb-0"><i class="fa-solid fa-list-check me-2 text-info"></i>Operasi Rekam Massal (Bulk Records)</h2>
    <p class="text-secondary small mb-0">Cari dan ganti nilai record (IP, CNAME, TXT) secara serempak di seluruh zona otoritatif.</p>
  </div>
</div>

<?php if (!empty($replaceResult)) : ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <h6 class="alert-heading mb-1"><i class="fa-solid fa-circle-check me-1"></i> Penggantian Massal Selesai</h6>
    <div>Berhasil mengubah <strong><?= (int) ($replaceResult['records_replaced'] ?? 0) ?></strong> record di <strong><?= (int) ($replaceResult['zones_modified'] ?? 0) ?></strong> zona. Snapshot keamanan otomatis telah disimpan untuk pemulihan (rollback).</div>
    <?php if (!empty($replaceResult['errors'])) : ?>
      <div class="mt-2 pt-2 border-top border-success-subtle text-danger small">
        <strong>Peringatan kesalahan:</strong>
        <ul class="mb-0">
          <?php foreach ($replaceResult['errors'] as $err) : ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
  </div>
<?php endif; ?>

<div class="panel mb-4">
  <form method="get" action="/bulk-records" class="row g-2 align-items-end">
    <div class="col-md-7 col-lg-8">
      <label class="form-label small" for="bulk-query">Kata Kunci / Nilai Konten (IP, FQDN, Teks)</label>
      <input class="form-control form-control-sm" id="bulk-query" name="q" value="<?= e($query) ?>" placeholder="Contoh: 192.0.2.1 atau old-host.example.com" required>
    </div>
    <div class="col-md-3 col-lg-2">
      <label class="form-label small" for="bulk-type">Filter Tipe</label>
      <select class="form-select form-select-sm" id="bulk-type" name="type">
        <option value="">Semua Tipe</option>
        <?php foreach (['A', 'AAAA', 'CNAME', 'TXT', 'MX', 'PTR', 'NS', 'ALIAS'] as $t) : ?>
          <option value="<?= $t ?>" <?= $typeFilter === $t ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 col-lg-2">
      <button class="btn btn-sm btn-primary w-100" type="submit">
        <i class="fa-solid fa-magnifying-glass me-1"></i> Cari Record
      </button>
    </div>
  </form>
</div>

<?php if ($query !== '') : ?>
  <div class="panel mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="h6 mb-0">Hasil Pencarian: <strong><?= count($results) ?></strong> record ditemukan</h3>
      <?php if (!empty($results) && in_array($user['role'] ?? '', ['admin', 'operator'], true)) : ?>
        <button class="btn btn-sm btn-warning" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-replace">
          <i class="fa-solid fa-pen-to-square me-1"></i> Buka Panel Ganti Massal
        </button>
      <?php endif; ?>
    </div>

    <!-- Batch Replace Collapse Form -->
    <div class="collapse mb-3" id="collapse-replace">
      <div class="card card-body bg-dark-subtle border-warning-subtle">
        <h6 class="card-title text-warning mb-2"><i class="fa-solid fa-triangle-exclamation me-1"></i> Form Penggantian Massal Antar-Zona</h6>
        <form method="post" action="/bulk-records/replace" onsubmit="return confirm('Apakah Anda yakin ingin mengganti record ini di semua zona yang terdampak? Snapshot keamanan akan dibuat otomatis.');">
          <?= csrfField() ?>
          <input type="hidden" name="q" value="<?= e($query) ?>">
          <input type="hidden" name="type" value="<?= e($typeFilter) ?>">
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small" for="replace-target">Teks / Konten yang Diganti</label>
              <input class="form-control form-control-sm font-monospace" id="replace-target" name="target" value="<?= e($query) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small" for="replace-new">Konten Baru Pengganti</label>
              <input class="form-control form-control-sm font-monospace" id="replace-new" name="replacement" placeholder="Contoh: 198.51.100.1 atau new-host.example.com" required>
            </div>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <small class="text-secondary">Sistem akan membuat snapshot versi zona sebelum perubahan.</small>
            <button class="btn btn-sm btn-danger" type="submit">
              <i class="fa-solid fa-bolt me-1"></i> Terapkan Penggantian Massal
            </button>
          </div>
        </form>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Zona Otoritatif</th>
            <th>Nama Record (FQDN)</th>
            <th>Tipe</th>
            <th>TTL</th>
            <th>Konten / RDATA</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($results)) : ?>
            <tr>
              <td colspan="6" class="text-center text-secondary py-4">
                Tidak ada record yang cocok dengan kata kunci "<strong><?= e($query) ?></strong>".
              </td>
            </tr>
          <?php else : ?>
            <?php foreach ($results as $r) : ?>
              <tr>
                <td>
                  <a href="/zones/<?= rawurlencode((string) $r['zone']) ?>" class="fw-bold text-decoration-none">
                    <?= e((string) $r['zone']) ?>
                  </a>
                </td>
                <td><code><?= e((string) $r['name']) ?></code></td>
                <td><span class="badge bg-secondary-subtle text-secondary"><?= e((string) $r['type']) ?></span></td>
                <td><small><?= (int) $r['ttl'] ?>s</small></td>
                <td><code class="text-break"><?= e((string) $r['content']) ?></code></td>
                <td class="text-end">
                  <a href="/zones/<?= rawurlencode((string) $r['zone']) ?>" class="btn btn-xs btn-outline-secondary">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Buka Zona
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
