<?php

declare(strict_types=1);

/**
 * @var string $zone
 * @var array<int, array<string, mixed>> $keys
 * @var array<string, mixed> $user
 * @var string|null $error
 * @var bool $cdsPublished
 * @var bool $cdnskeyPublished
 */

$cleanZone = rawurlencode(rtrim($zone, '.'));
?>
<div class="zone-head mb-3">
  <div>
    <p class="muted mb-1">
      <a href="/zones/<?= e($cleanZone) ?>" class="text-decoration-none">← Kembali ke Editor Zona</a>
    </p>
    <h2 class="m-0">DNSSEC &amp; Kunci Otoritatif: <code><?= e($zone) ?></code></h2>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="/zones/<?= e($cleanZone) ?>">Editor Record</a>
    <a class="btn btn-outline-secondary" href="/zones/<?= e($cleanZone) ?>/history">Riwayat &amp; Rollback</a>
  </div>
</div>

<?php if (!empty($error)) : ?>
  <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-md-7">
    <div class="panel h-100">
      <h2 class="h6 mb-2">Informasi DNSSEC</h2>
      <p class="muted mb-2">
        Kunci privat disimpan aman di backend PowerDNS dan tidak pernah terekspos.
        Kombinasi algoritma modern seperti <strong>Ed25519 (Alg 15)</strong>
        memberikan tanda tangan kriptografi tercepat dan paling efisien.
      </p>
      <ul class="muted small mb-0 ps-3">
        <li>
          <strong>CSK (Combined Signing Key)</strong>: Satu pasang kunci bertindak sebagai KSK sekaligus ZSK.
          Standar efisien PowerDNS modern.
        </li>
        <li>
          <strong>Split (KSK + ZSK)</strong>: Pemisahan peran Key-Signing Key dan Zone-Signing Key
          untuk kebutuhan audit atau kepatuhan tertentu.
        </li>
      </ul>
    </div>
  </div>

  <div class="col-md-5">
    <div
      class="panel h-100 border-start border-4 <?= ($cdsPublished || $cdnskeyPublished)
        ? 'border-success' : 'border-secondary' ?>"
    >
      <div class="d-flex justify-content-between align-items-start mb-2">
        <h2 class="h6 m-0">Otomasi RFC 7344 (CDS / CDNSKEY)</h2>
        <?php if ($cdsPublished || $cdnskeyPublished) : ?>
          <span class="badge bg-success">Aktif</span>
        <?php else : ?>
          <span class="badge bg-secondary">Nonaktif</span>
        <?php endif; ?>
      </div>
      <p class="muted small mb-3">
        Publikasikan record CDS/CDNSKEY otomatis agar registrar / parent TLD yang mendukung RFC 7344 &amp; 8078
        dapat menyinkronkan DS tanpa intervensi manual.
      </p>

      <?php if (in_array($user['role'] ?? '', ['admin', 'operator'], true)) : ?>
        <form method="post" action="/zones/<?= e($cleanZone) ?>/dnssec/cds">
            <?= csrfField() ?>
          <input
            type="hidden"
            name="enable_cds"
            value="<?= ($cdsPublished || $cdnskeyPublished) ? '0' : '1' ?>"
          >
            <?php if ($cdsPublished || $cdnskeyPublished) : ?>
            <button class="btn btn-outline-danger btn-sm w-100" type="submit">
              Nonaktifkan Publikasi CDS / CDNSKEY
            </button>
            <?php else : ?>
            <button class="btn btn-outline-success btn-sm w-100" type="submit">
              Aktifkan Publikasi Otomatis CDS / CDNSKEY
            </button>
            <?php endif; ?>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (in_array($user['role'] ?? '', ['admin', 'operator'], true)) : ?>
  <form method="post" action="/zones/<?= e($cleanZone) ?>/dnssec" class="panel stack mb-3">
    <?= csrfField() ?>
    <h2 class="h6 mb-2">Generate / Tambah Kunci DNSSEC</h2>
    <div class="row g-2 align-items-end">
      <div class="col-md-5">
        <label class="form-label" for="key-algo">Algoritma Kriptografi</label>
        <select class="form-select" id="key-algo" name="algorithm">
          <option value="ed25519" selected>Ed25519 (Alg 15, Curve25519) - Modern &amp; Cepat (Rekomendasi)</option>
          <option value="ecdsa256">ECDSA P-256 (Alg 13, SHA-256) - Standar Industri</option>
          <option value="ecdsa384">ECDSA P-384 (Alg 14, SHA-384) - Tingkat Tinggi</option>
          <option value="rsasha256">RSA/SHA-256 2048-bit (Alg 8) - Warisan Tradisional</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="key-mode">Mode Kunci</label>
        <select class="form-select" id="key-mode" name="mode">
          <option value="csk">CSK (Combined Signing Key)</option>
          <option value="split">Split (KSK + ZSK terpisah)</option>
        </select>
      </div>
      <div class="col-md-3">
        <button class="btn btn-primary w-100" type="submit">Buat Kunci Sekarang</button>
      </div>
    </div>
  </form>
<?php endif; ?>

<div class="panel">
  <h2 class="h6 mb-3">Daftar Cryptokey &amp; DS Record</h2>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th scope="col">ID</th>
          <th scope="col">Jenis</th>
          <th scope="col">Algoritma</th>
          <th scope="col">Bit</th>
          <th scope="col">Aktif</th>
          <th scope="col">Terbit</th>
          <th scope="col">Delegation Signer (DS)</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($keys as $key) : ?>
        <tr>
          <td><?= (int) ($key['id'] ?? 0) ?></td>
          <td><span class="badge bg-secondary"><?= e((string) ($key['keytype'] ?? '')) ?></span></td>
          <td><code><?= e((string) ($key['algorithm'] ?? '')) ?></code></td>
          <td><?= (int) ($key['bits'] ?? 0) ?></td>
          <td><?= !empty($key['active']) ? '<span class="pill ok">ya</span>' : '<span class="pill">tidak</span>' ?></td>
          <td>
            <?= !empty($key['published']) ? '<span class="pill ok">ya</span>' : '<span class="pill">tidak</span>' ?>
          </td>
          <td>
            <?php if (!empty($key['ds'])) : ?>
                <?php foreach ((array) $key['ds'] as $ds) : ?>
                <code><?= e((string) $ds) ?></code><br>
                <?php endforeach; ?>
            <?php else : ?>
              <span class="muted">–</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$keys) : ?>
        <tr>
          <td colspan="7" class="text-center py-4 muted">
            Belum ada cryptokey yang aktif pada zona ini. Klik tombol di atas untuk membuat kunci baru.
          </td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
