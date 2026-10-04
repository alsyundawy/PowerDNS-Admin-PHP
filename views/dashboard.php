<?php

declare(strict_types=1);

/**
 * @var int $zones
 * @var int $dnssec
 * @var int $users
 * @var int $accounts
 * @var bool $apiOk
 * @var string $apiError
 * @var array<string, string> $stats
 * @var array<int, array<string, mixed>> $recent
 */
$statMetrics = [
    'udp-queries' => 'UDP query',
    'udp-answers' => 'UDP jawab',
    'tcp-queries' => 'TCP query',
    'packetcache-hit' => 'Cache hit',
    'servfail-answers' => 'SERVFAIL',
];
?>
<div class="stat-grid mb-3">
  <article><span>Zona</span><strong><?= (int) $zones ?></strong></article>
  <article><span>DNSSEC Aktif</span><strong><?= (int) $dnssec ?></strong></article>
  <article><span>Pengguna</span><strong><?= (int) $users ?></strong></article>
  <article><span>Akun</span><strong><?= (int) $accounts ?></strong></article>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <section class="panel h-100">
      <header>
        <h2>Status PowerDNS</h2>
        <?php if ($apiOk) : ?>
          <span class="pill ok">API Terhubung</span>
        <?php else : ?>
          <span class="pill bad">API Terputus</span>
        <?php endif; ?>
      </header>
      <?php if (!empty($apiError)) : ?>
        <div class="alert alert-danger" role="alert"><?= e($apiError) ?></div>
      <?php endif; ?>
      <div class="stat-grid compact">
        <?php foreach ($statMetrics as $k => $label) : ?>
          <article>
            <span><?= e($label) ?></span>
            <strong><?= e($stats[$k] ?? '–') ?></strong>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
  <div class="col-lg-5">
    <section class="panel h-100">
      <header>
        <h2>Audit Terbaru</h2>
      </header>
      <ul class="feed">
        <?php foreach ($recent as $row) : ?>
          <li>
            <div class="d-flex justify-content-between align-items-center">
              <b><?= e($row['action']) ?></b>
              <span class="text-truncate" style="max-width: 180px;"><?= e($row['zone_name']) ?></span>
            </div>
            <small><?= e($row['username'] ?: 'sistem') ?> · <?= e((string) $row['created_at']) ?></small>
          </li>
        <?php endforeach; ?>
        <?php if (!$recent) : ?>
          <li class="muted py-3 text-center">Belum ada jejak audit.</li>
        <?php endif; ?>
      </ul>
    </section>
  </div>
</div>
