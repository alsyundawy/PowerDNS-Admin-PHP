<?php

declare(strict_types=1);

/**
 * @var string $zone
 * @var array<string, mixed> $data
 * @var array{id?: int, name?: string, kind?: string, account_id?: int|null, account_name?: string|null}|null $local
 * @var string $soa
 * @var list<array{name: string, type: string, ttl: int, content: string, disabled: bool, comment: string}> $rows
 * @var list<string> $types
 * @var bool $canEdit
 * @var array{id: int, username: string, role: string, display_name: string}|null $user
 */
$cleanZone = rawurlencode(rtrim($zone, '.'));
?>
<div class="zone-head mb-3">
  <div>
    <p class="muted mb-1">
      <?= e((string) ($data['kind'] ?? '')) ?> · serial <?= e((string) ($data['serial'] ?? '')) ?>
      · <?= e(!empty($local['account_name']) ? (string) $local['account_name'] : 'tanpa akun') ?>
    </p>
    <code><?= e($soa) ?></code>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-primary" href="/zones/<?= e($cleanZone) ?>/dnssec">
      <i class="fa-solid fa-shield-halved me-1"></i>DNSSEC
    </a>
    <a
      class="btn btn-outline-secondary"
      href="/zones/<?= e($cleanZone) ?>/history"
      title="Riwayat versi & 1-Click Rollback"
    ><i class="fa-solid fa-clock-rotate-left me-1"></i>Riwayat</a>
    <a
      class="btn btn-outline-secondary"
      href="/zones/<?= e($cleanZone) ?>/export"
      title="Ekspor file zona BIND RFC 1035"
    ><i class="fa-solid fa-file-export me-1"></i>Ekspor BIND</a>
    <?php if ($canEdit) : ?>
      <form method="post" action="/zones/<?= e($cleanZone) ?>/notify" class="d-inline">
        <?= csrfField() ?>
        <button class="btn btn-outline-secondary" type="submit">
          <i class="fa-solid fa-bell me-1"></i>NOTIFY
        </button>
      </form>
      <form method="post" action="/zones/<?= e($cleanZone) ?>/axfr" class="d-inline">
        <?= csrfField() ?>
        <button class="btn btn-outline-secondary" type="submit">
          <i class="fa-solid fa-arrows-spin me-1"></i>AXFR
        </button>
      </form>
      <form method="post" action="/zones/<?= e($cleanZone) ?>/rectify" class="d-inline">
        <?= csrfField() ?>
        <button class="btn btn-outline-secondary" type="submit" title="Rectify zona DNSSEC">
          <i class="fa-solid fa-wrench me-1"></i>Rectify
        </button>
      </form>
    <?php endif; ?>
    <?php if (($user['role'] ?? '') === 'admin') : ?>
      <form
        method="post"
        action="/zones/<?= e($cleanZone) ?>/delete"
        class="d-inline"
        onsubmit="return confirm('Hapus zona ini dari PowerDNS?')"
      >
        <?= csrfField() ?>
        <button class="btn btn-outline-danger" type="submit">
          <i class="fa-solid fa-trash me-1"></i>Hapus
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php
$zoneRrsets = is_array($data['rrsets'] ?? null) ? $data['rrsets'] : [];
$rfcIssues = function_exists('lintZoneRrsets') ? lintZoneRrsets($zone, $zoneRrsets) : [];
?>
<?php if (!empty($rfcIssues)) : ?>
  <div class="alert alert-warning mb-3" role="alert">
    <div class="d-flex justify-content-between align-items-center mb-1">
      <strong class="d-flex align-items-center gap-1">
        <i class="fa-solid fa-triangle-exclamation"></i> Diagnostik Kepatuhan RFC (<?= count($rfcIssues) ?> Catatan)
      </strong>
      <button class="btn btn-xs btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#rfc-issues-collapse">
        Detail & Solusi
      </button>
    </div>
    <div class="collapse show mt-2" id="rfc-issues-collapse">
      <ul class="mb-0 ps-3 small">
        <?php foreach ($rfcIssues as $iss) : ?>
          <li class="mb-1">
            <span class="badge bg-<?= $iss['severity'] === 'error' ? 'danger' : 'warning text-dark' ?> me-1"><?= e($iss['code']) ?></span>
            <strong><?= e($iss['message']) ?></strong>
            <span class="text-secondary">&rarr; <?= e($iss['suggestion']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<?php if ($canEdit) : ?>
  <form method="post" action="/zones/<?= e($cleanZone) ?>/save" id="record-form">
    <?= csrfField() ?>
    <div class="panel">
      <div class="toolbar mb-3">
        <input
          class="form-control"
          id="record-filter"
          placeholder="Saring nama, tipe, atau isi record..."
          aria-label="Saring record"
        >
        <div class="d-flex gap-2 flex-wrap align-items-center">
          <?php if (!isReverseZone($zone)) : ?>
            <div class="form-check me-2 mb-0">
              <input
                class="form-check-input"
                type="checkbox"
                name="auto_ptr_sync"
                id="auto_ptr_sync"
                value="1"
                <?= setting('dns_auto_ptr_default', '1') !== '0' ? 'checked' : '' ?>
              >
              <label
                class="form-check-label small"
                for="auto_ptr_sync"
                title="Sinkronkan A/AAAA ke zona reverse otomatis jika tersedia"
              >
                Auto-PTR
              </label>
            </div>
          <?php endif; ?>
          <button class="btn btn-outline-primary" type="button" id="add-row">
            <i class="fa-solid fa-plus me-1"></i>Tambah baris
          </button>
          <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-check me-1"></i>Terapkan ke PowerDNS
          </button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table align-middle" id="record-table">
          <thead>
            <tr>
              <th scope="col" style="min-width: 140px;">Nama</th>
              <th scope="col" style="width: 110px;">Tipe</th>
              <th scope="col" style="width: 90px;">TTL</th>
              <th scope="col" style="min-width: 220px;">Isi</th>
              <th scope="col" style="width: 60px; text-align: center;">Off</th>
              <th scope="col" style="min-width: 150px;">Catatan</th>
              <th scope="col" style="width: 50px;"></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $i => $row) : ?>
            <tr>
              <td>
                <input
                  class="form-control"
                  name="r_name[<?= (int) $i ?>]"
                  value="<?= e($row['name']) ?>"
                  placeholder="@ atau subdomain"
                  aria-label="Nama host"
                >
              </td>
              <td>
                <select class="form-select" name="r_type[<?= (int) $i ?>]" aria-label="Tipe record">
                  <?php foreach ($types as $t) : ?>
                    <option value="<?= e($t) ?>" <?= $row['type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <input
                  class="form-control"
                  type="number"
                  min="30"
                  name="r_ttl[<?= (int) $i ?>]"
                  value="<?= (int) $row['ttl'] ?>"
                  aria-label="TTL"
                >
              </td>
              <td>
                <input
                  class="form-control"
                  name="r_content[<?= (int) $i ?>]"
                  value="<?= e($row['content']) ?>"
                  placeholder="Nilai record"
                  aria-label="Isi record"
                >
              </td>
              <td class="text-center">
                <input type="hidden" name="r_disabled[<?= (int) $i ?>]" value="0">
                <input
                  type="checkbox"
                  class="form-check-input"
                  name="r_disabled[<?= (int) $i ?>]"
                  value="1"
                  <?= !empty($row['disabled']) ? 'checked' : '' ?>
                  aria-label="Nonaktifkan record"
                >
              </td>
              <td>
                <input
                  class="form-control"
                  name="r_comment[<?= (int) $i ?>]"
                  value="<?= e($row['comment']) ?>"
                  placeholder="Keterangan opsional"
                  aria-label="Catatan"
                >
              </td>
              <td class="text-center">
                <button
                  class="btn btn-sm btn-outline-danger rm-row"
                  type="button"
                  aria-label="Hapus baris"
                ><i class="fa-solid fa-trash-can"></i></button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </form>

  <template id="row-template">
    <tr>
      <td><input class="form-control" name="r_name[]" placeholder="@ atau www" aria-label="Nama host"></td>
      <td>
        <select class="form-select" name="r_type[]" aria-label="Tipe record">
          <?php foreach ($types as $t) : ?>
                <?php if ($t !== 'SOA') : ?>
              <option value="<?= e($t) ?>"><?= e($t) ?></option>
                <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </td>
      <td><input class="form-control" type="number" min="30" name="r_ttl[]" value="3600" aria-label="TTL"></td>
      <td><input class="form-control" name="r_content[]" placeholder="Nilai record" aria-label="Isi record"></td>
      <td class="text-center">
        <input type="checkbox" class="form-check-input" name="r_disabled[]" value="1" aria-label="Nonaktifkan record">
      </td>
      <td><input class="form-control" name="r_comment[]" placeholder="Keterangan opsional" aria-label="Catatan"></td>
      <td class="text-center">
        <button
          class="btn btn-sm btn-outline-danger rm-row"
          type="button"
          aria-label="Hapus baris"
        ><i class="fa-solid fa-trash-can"></i></button>
      </td>
    </tr>
  </template>
<?php else : ?>
  <div class="panel">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th scope="col">Nama</th>
            <th scope="col">Tipe</th>
            <th scope="col">TTL</th>
            <th scope="col">Isi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row) : ?>
          <tr>
            <td><?= e($row['name']) ?></td>
            <td><span class="badge bg-secondary"><?= e($row['type']) ?></span></td>
            <td><?= (int) $row['ttl'] ?></td>
            <td><code><?= e($row['content']) ?></code></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php if (($user['role'] ?? '') === 'admin') : ?>
  <form method="post" action="/zones/<?= e($cleanZone) ?>/grant" class="panel stack">
    <?= csrfField() ?>
    <h2 class="h6 mb-2">Akses zona</h2>
    <div class="row g-2 align-items-center">
      <div class="col-md-5">
        <input class="form-control" name="username" placeholder="Username panel" required aria-label="Username">
      </div>
      <div class="col-md-4">
        <label class="form-check-label small d-flex align-items-center gap-2">
          <input type="checkbox" class="form-check-input" name="can_edit" value="1" checked> Boleh sunting record
        </label>
      </div>
      <div class="col-md-3">
        <button class="btn btn-outline-primary w-100" type="submit">Berikan izin</button>
      </div>
    </div>
  </form>
<?php endif; ?>
