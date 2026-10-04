<?php

declare(strict_types=1);

/**
 * @var string $zone
 * @var array<string, mixed> $user
 * @var list<array<string, mixed>> $snapshots
 * @var array<string, mixed>|null $selectedSnapshot
 * @var array<string, mixed> $currentZone
 */

$cleanZone = rawurlencode(rtrim($zone, '.'));
?>
<div class="zone-head mb-3">
  <div>
    <p class="muted mb-1">
      <a href="/zones/<?= e($cleanZone) ?>" class="text-decoration-none">← Kembali ke Editor Zona</a>
    </p>
    <h2 class="m-0">Riwayat Versi &amp; Rollback: <code><?= e($zone) ?></code></h2>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="/zones/<?= e($cleanZone) ?>">Editor Record</a>
    <a class="btn btn-outline-primary" href="/zones/<?= e($cleanZone) ?>/dnssec">DNSSEC</a>
  </div>
</div>

<?php if ($selectedSnapshot) : ?>
  <div class="panel mb-4" style="border-left: 4px solid var(--accent, #3b82f6);">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h3 class="m-0">Inspeksi Revisi #<?= (int) $selectedSnapshot['id'] ?></h3>
        <p class="muted small m-0">
          Disimpan pada <?= e((string) $selectedSnapshot['created_at']) ?>
          oleh <strong><?= e((string) ($selectedSnapshot['username'] ?: 'Sistem')) ?></strong>
          · Serial: <?= e((string) ($selectedSnapshot['serial'] ?? '—')) ?>
          · Catatan: <em><?= e((string) ($selectedSnapshot['comment'] ?: 'Tanpa catatan')) ?></em>
        </p>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="/zones/<?= e($cleanZone) ?>/history">Tutup Inspeksi</a>
        <form
          method="post"
          action="/zones/<?= e($cleanZone) ?>/rollback/<?= (int) $selectedSnapshot['id'] ?>"
          onsubmit="return confirm(
            'PERINGATAN: Mengembalikan seluruh record zona ke revisi #<?= (int) $selectedSnapshot['id'] ?>? ' +
            'Snapshot zona saat ini akan disimpan otomatis sebelum rollback.'
          )"
        >
          <?= csrfField() ?>
          <button class="btn btn-danger btn-sm" type="submit">Rollback ke Versi Ini</button>
        </form>
      </div>
    </div>

    <h4>Record dalam Revisi Ini (<?= count($selectedSnapshot['rrsets'] ?? []) ?> RRsets):</h4>
    <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
      <table class="table table-sm">
        <thead>
          <tr>
            <th>Nama</th>
            <th>Tipe</th>
            <th>TTL</th>
            <th>Konten</th>
            <th>Komentar</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($selectedSnapshot['rrsets'] ?? []) as $rr) : ?>
                <?php
                $rname = (string) ($rr['name'] ?? '');
              $rtype = (string) ($rr['type'] ?? '');
              $rttl = (int) ($rr['ttl'] ?? 3600);
              $rrecords = is_array($rr['records'] ?? null) ? $rr['records'] : [];
              $rcomments = is_array($rr['comments'] ?? null) ? $rr['comments'] : [];
              ?>
            <tr>
              <td><code><?= e($rname) ?></code></td>
              <td><span class="badge"><?= e($rtype) ?></span></td>
              <td><?= $rttl ?></td>
              <td>
                <?php foreach ($rrecords as $rec) : ?>
                  <div>
                    <code><?= e((string) ($rec['content'] ?? '')) ?></code>
                    <?php if (!empty($rec['disabled'])) : ?>
                      <span class="badge bg-secondary">nonaktif</span>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </td>
              <td class="muted small">
                <?php foreach ($rcomments as $cm) : ?>
                  <div><?= e((string) ($cm['content'] ?? '')) ?></div>
                <?php endforeach; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<div class="panel">
  <div class="panel-header mb-3">
    <h3 class="m-0">Daftar Snapshot Riwayat (<?= count($snapshots) ?> Tersedia)</h3>
    <p class="muted small m-0">
      Setiap kali ada penambahan, pengubahan, atau penghapusan record zona, snapshot versi otomatis dibuat.
    </p>
  </div>

  <?php if (empty($snapshots)) : ?>
    <div class="alert alert-info">
      Belum ada snapshot riwayat yang tersimpan untuk zona ini.
      Snapshot akan dibuat secara otomatis saat Anda melakukan perubahan record berikutnya.
    </div>
  <?php else : ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>#ID</th>
            <th>Waktu Dibuat</th>
            <th>Operator</th>
            <th>Serial SOA</th>
            <th>Catatan / Tindakan</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($snapshots as $s) : ?>
            <tr class="<?= (!empty($selectedSnapshot)
              && (int) $selectedSnapshot['id'] === (int) $s['id']) ? 'table-active' : '' ?>">
              <td><strong>#<?= (int) $s['id'] ?></strong></td>
              <td><?= e((string) $s['created_at']) ?></td>
              <td>
                <span class="badge bg-light text-dark">
                  <?= e((string) ($s['username'] ?: 'Sistem')) ?>
                </span>
              </td>
              <td><code><?= e((string) ($s['serial'] ?? '—')) ?></code></td>
              <td><?= e((string) ($s['comment'] ?: 'Pembaruan record zona')) ?></td>
              <td class="text-end">
                <a
                  class="btn btn-outline-secondary btn-sm"
                  href="/zones/<?= e($cleanZone) ?>/history?diff=<?= (int) $s['id'] ?>"
                >
                  Inspeksi Detail
                </a>
                <form
                  method="post"
                  action="/zones/<?= e($cleanZone) ?>/rollback/<?= (int) $s['id'] ?>"
                  class="d-inline ms-1"
                  onsubmit="return confirm(
                    'PERINGATAN: Apakah Anda yakin ingin me-rollback zona <?= e($zone) ?> ' +
                    'ke revisi #<?= (int) $s['id'] ?>?'
                  )"
                >
                  <?= csrfField() ?>
                  <button class="btn btn-danger btn-sm" type="submit" title="Rollback zona ke versi ini">
                    Rollback
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
