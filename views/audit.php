<?php

declare(strict_types=1);

/**
 * @var array<int, array<string, mixed>> $rows
 */

?>
<div class="panel">
  <header>
    <h2>Log Jejak Audit Sistem</h2>
  </header>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th scope="col" style="white-space: nowrap;">Waktu</th>
          <th scope="col">User</th>
          <th scope="col">Aksi</th>
          <th scope="col">Zona</th>
          <th scope="col">Detail</th>
          <th scope="col">Alamat IP</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r) : ?>
        <tr>
          <td style="white-space: nowrap; font-size: 12px;"><?= e((string) $r['created_at']) ?></td>
          <td><strong><?= e((string) ($r['username'] ?: 'sistem')) ?></strong></td>
          <td><span class="badge bg-secondary"><?= e((string) $r['action']) ?></span></td>
          <td><?= e((string) ($r['zone_name'] ?: '–')) ?></td>
          <td style="word-break: break-all; max-width: 320px;"><?= e((string) ($r['detail'] ?: '–')) ?></td>
          <td><code><?= e((string) $r['ip']) ?></code></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows) : ?>
        <tr>
          <td colspan="6" class="text-center py-4 muted">Belum ada log aktivitas.</td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
