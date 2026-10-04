<?php

declare(strict_types=1);

/**
 * @var string $q
 * @var string $kind
 * @var list<array{
 *   name: string,
 *   kind: string,
 *   account_name: string|null,
 *   serial: int|string|null,
 *   dnssec: bool|int
 * }> $zones
 * @var array{id: int, username: string, role: string, display_name: string}|null $user
 */
?>
<div class="toolbar mb-3">
  <form class="search-inline" method="get" action="/zones">
    <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Cari nama zona..." aria-label="Cari zona">
    <select class="form-select" name="kind" aria-label="Jenis zona">
      <option value="">Semua jenis</option>
      <?php foreach (['Native', 'Master', 'Slave', 'Producer', 'Consumer'] as $k) : ?>
        <option value="<?= e($k) ?>" <?= $kind === $k ? 'selected' : '' ?>><?= e($k) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-primary" type="submit">Saring</button>
  </form>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (in_array($user['role'] ?? '', ['admin', 'operator'], true)) : ?>
      <form method="post" action="/zones/sync" class="d-inline">
        <?= csrfField() ?>
        <button class="btn btn-outline-secondary" type="submit">Sinkron dari PowerDNS</button>
      </form>
      <a class="btn btn-primary" href="/zones/new">Zona baru</a>
    <?php endif; ?>
  </div>
</div>

<div class="panel">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th scope="col">Zona</th>
          <th scope="col">Jenis</th>
          <th scope="col">Akun</th>
          <th scope="col">Serial</th>
          <th scope="col">DNSSEC</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($zones as $z) : ?>
        <tr>
          <td>
            <a class="fw-medium" href="/zones/<?= e(rawurlencode(rtrim($z['name'], '.'))) ?>">
              <?= e(dnsDisplay($z['name'])) ?>
            </a>
          </td>
          <td><span class="pill"><?= e($z['kind']) ?></span></td>
          <td><?= e($z['account_name'] ?: '–') ?></td>
          <td><?= e((string) ($z['serial'] ?? '')) ?></td>
          <td>
            <?php if (!empty($z['dnssec'])) : ?>
              <span class="pill ok">aktif</span>
            <?php else : ?>
              <span class="pill">tidak</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$zones) : ?>
        <tr>
          <td colspan="5" class="text-center py-4 muted">
            Belum ada zona di cache. Jalankan sinkron setelah API terhubung.
          </td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
