<?php

declare(strict_types=1);

/**
 * @var array<string, mixed> $user
 * @var array<string, mixed> $profileUser
 * @var int $zones
 * @var int $dnssec
 * @var int $users
 * @var int $accounts
 * @var bool $apiOk
 * @var string $apiError
 * @var array<string, string> $stats
 * @var array<int, array<string, mixed>> $recent
 * @var string $phpVersion
 * @var string $memoryUsage
 * @var string $serverSoftware
 */

$statMetrics = [
    'udp-queries' => 'UDP Query',
    'udp-answers' => 'UDP Answers',
    'tcp-queries' => 'TCP Query',
    'packetcache-hit' => 'Cache Hit',
    'servfail-answers' => 'SERVFAIL',
];

$avatarUrl = !empty($profileUser['avatar_url']) ? (string) $profileUser['avatar_url'] : '';
$displayName = !empty($profileUser['display_name'])
    ? (string) $profileUser['display_name']
    : (string) $profileUser['username'];
$initials = strtoupper(substr((string) $profileUser['username'], 0, 2));
?>
<!-- Profile Greeting & Quick Actions Bar -->
<div class="panel mb-3 p-3">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-3">
      <a href="/profile" class="text-decoration-none">
        <div class="dashboard-avatar-wrap">
          <?php if ($avatarUrl !== '') : ?>
            <img src="<?= e($avatarUrl) ?>" alt="Avatar" class="dashboard-avatar-img">
          <?php else : ?>
            <div class="dashboard-avatar-initials"><?= e($initials) ?></div>
          <?php endif; ?>
        </div>
      </a>
      <div>
        <div class="d-flex align-items-center gap-2">
          <h2 class="h5 mb-0"><?= e($displayName) ?></h2>
          <span class="badge bg-primary text-uppercase small"><?= e((string) $profileUser['role']) ?></span>
        </div>
        <p class="text-secondary small mb-0">
          Signed in as <strong>@<?= e((string) $profileUser['username']) ?></strong> &bull;
          Last sign in: <?= e((string) ($profileUser['last_login_at'] ?? 'Current session')) ?>
        </p>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-sm btn-outline-light" href="/profile">
        <i class="fa-solid fa-user-gear me-1"></i> Edit Profile &amp; Password
      </a>
      <?php if (($user['role'] ?? '') === 'admin') : ?>
        <a class="btn btn-sm btn-outline-info" href="/backup">
          <i class="fa-solid fa-database me-1"></i> Backup &amp; Restore
        </a>
        <a class="btn btn-sm btn-outline-secondary" href="/settings">
          <i class="fa-solid fa-sliders me-1"></i> GUI Settings
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="stat-grid mb-3">
  <article>
    <span><i class="fa-solid fa-globe text-info"></i> Zones</span>
    <strong><?= (int) $zones ?></strong>
  </article>
  <article>
    <span><i class="fa-solid fa-shield-halved text-success"></i> Active DNSSEC</span>
    <strong><?= (int) $dnssec ?></strong>
  </article>
  <article>
    <span><i class="fa-solid fa-user-shield text-purple"></i> Users</span>
    <strong><?= (int) $users ?></strong>
  </article>
  <article>
    <span><i class="fa-solid fa-users text-warning"></i> Accounts</span>
    <strong><?= (int) $accounts ?></strong>
  </article>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <section class="panel h-100">
      <header>
        <h2><i class="fa-solid fa-server me-2 text-info"></i>PowerDNS Status</h2>
        <?php if ($apiOk) : ?>
          <span class="pill ok"><i class="fa-solid fa-circle-check me-1"></i>API Connected</span>
        <?php else : ?>
          <span class="pill bad"><i class="fa-solid fa-circle-xmark me-1"></i>API Disconnected</span>
        <?php endif; ?>
      </header>
      <?php if (!empty($apiError)) : ?>
        <div class="alert alert-danger" role="alert">
          <i class="fa-solid fa-triangle-exclamation me-1"></i><?= e($apiError) ?>
        </div>
      <?php endif; ?>
      <div class="stat-grid compact mb-3">
        <?php foreach ($statMetrics as $k => $label) : ?>
          <article>
            <span><i class="fa-solid fa-chart-simple text-secondary me-1"></i><?= e($label) ?></span>
            <strong><?= e($stats[$k] ?? '–') ?></strong>
          </article>
        <?php endforeach; ?>
      </div>

      <!-- Quick GUI Config & Actions Controls -->
      <div class="pt-2 border-top border-secondary-subtle">
        <h3 class="small fw-bold text-uppercase text-secondary mb-2">Quick Controls &amp; GUI Configuration</h3>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <form method="post" action="/zones/sync" class="d-inline">
            <?= csrfField() ?>
            <button class="btn btn-sm btn-outline-primary" type="submit" title="Sync zones from PowerDNS API">
              <i class="fa-solid fa-rotate me-1"></i> Sync Zones
            </button>
          </form>
          <?php if (($user['role'] ?? '') === 'admin') : ?>
            <a class="btn btn-sm btn-outline-info" href="/backup/download/db" title="Instant metadata SQL download">
              <i class="fa-solid fa-file-arrow-down me-1"></i> Download SQL
            </a>
            <a class="btn btn-sm btn-outline-success" href="/backup/download/config" title="Instant configuration JSON download">
              <i class="fa-solid fa-download me-1"></i> Download Config
            </a>
          <?php endif; ?>
          <button class="btn btn-sm btn-outline-warning theme-toggle-btn" type="button" aria-label="Toggle theme">
            <i class="fa-solid fa-sun me-1"></i> Toggle Theme
          </button>
        </div>
      </div>
    </section>
  </div>

  <div class="col-lg-5">
    <section class="panel h-100">
      <header>
        <h2><i class="fa-solid fa-clipboard-list me-2 text-info"></i>Recent Audit Logs</h2>
      </header>
      <ul class="feed mb-3">
        <?php foreach ($recent as $row) : ?>
          <li>
            <div class="d-flex justify-content-between align-items-center">
              <b><i class="fa-solid fa-clock-rotate-left text-secondary me-1"></i><?= e($row['action']) ?></b>
              <span class="text-truncate" style="max-width: 180px;"><?= e($row['zone_name']) ?></span>
            </div>
            <small><?= e($row['username'] ?: 'system') ?> · <?= e((string) $row['created_at']) ?></small>
          </li>
        <?php endforeach; ?>
        <?php if (!$recent) : ?>
          <li class="muted py-3 text-center">No audit log activity yet.</li>
        <?php endif; ?>
      </ul>

      <!-- System Environment Runtime Specs -->
      <div class="pt-2 border-top border-secondary-subtle small text-secondary">
        <div class="d-flex justify-content-between mb-1">
          <span><i class="fa-brands fa-php me-1 text-primary"></i> PHP Runtime:</span>
          <span class="text-light"><?= e($phpVersion) ?></span>
        </div>
        <div class="d-flex justify-content-between mb-1">
          <span><i class="fa-solid fa-memory me-1 text-info"></i> Memory Usage:</span>
          <span class="text-light"><?= e($memoryUsage) ?></span>
        </div>
        <div class="d-flex justify-content-between">
          <span><i class="fa-solid fa-microchip me-1 text-success"></i> Web Server:</span>
          <span class="text-light"><?= e($serverSoftware) ?></span>
        </div>
      </div>
    </section>
  </div>
</div>
