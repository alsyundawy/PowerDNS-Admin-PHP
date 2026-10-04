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
    'udp-queries' => 'UDP Query',
    'udp-answers' => 'UDP Jawab',
    'tcp-queries' => 'TCP Query',
    'packetcache-hit' => 'Cache Hit',
    'servfail-answers' => 'SERVFAIL',
];
?>
<div class="stat-grid mb-3">
  <article>
    <span><i class="fa-solid fa-globe text-info"></i> Zona</span>
    <strong><?= (int) $zones ?></strong>
  </article>
  <article>
    <span><i class="fa-solid fa-shield-halved text-success"></i> DNSSEC Aktif</span>
    <strong><?= (int) $dnssec ?></strong>
  </article>
  <article>
    <span><i class="fa-solid fa-user-shield text-purple"></i> Pengguna</span>
    <strong><?= (int) $users ?></strong>
  </article>
  <article>
    <span><i class="fa-solid fa-users text-warning"></i> Akun</span>
    <strong><?= (int) $accounts ?></strong>
  </article>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <section class="panel h-100">
      <header>
        <h2><i class="fa-solid fa-server me-2 text-info"></i>Status PowerDNS</h2>
        <?php if ($apiOk) : ?>
          <span class="pill ok"><i class="fa-solid fa-circle-check me-1"></i>API Terhubung</span>
        <?php else : ?>
          <span class="pill bad"><i class="fa-solid fa-circle-xmark me-1"></i>API Terputus</span>
        <?php endif; ?>
      </header>
      <?php if (!empty($apiError)) : ?>
        <div class="alert alert-danger" role="alert">
          <i class="fa-solid fa-triangle-exclamation me-1"></i><?= e($apiError) ?>
        </div>
      <?php endif; ?>
      <div class="stat-grid compact">
        <?php foreach ($statMetrics as $k => $label) : ?>
          <article>
            <span><i class="fa-solid fa-chart-simple text-secondary me-1"></i><?= e($label) ?></span>
            <strong><?= e($stats[$k] ?? '–') ?></strong>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
  <div class="col-lg-5">
    <section class="panel h-100">
      <header>
        <h2><i class="fa-solid fa-clipboard-list me-2 text-info"></i>Audit Terbaru</h2>
      </header>
      <ul class="feed">
        <?php foreach ($recent as $row) : ?>
          <li>
            <div class="d-flex justify-content-between align-items-center">
              <b><i class="fa-solid fa-clock-rotate-left text-secondary me-1"></i><?= e($row['action']) ?></b>
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
