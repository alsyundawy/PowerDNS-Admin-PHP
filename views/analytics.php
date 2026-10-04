<?php

/**
 * Advanced DNS Telemetry & Visual Analytics View.
 * Displays packet cache hit ratio gauges, protocol distribution, and ring buffer top queries.
 */

declare(strict_types=1);

/**
 * @var array<string, mixed> $user
 * @var array<string, mixed> $metrics
 * @var array<int, array{item: string, count: int, percentage: float}> $topQueries
 * @var array<int, array{item: string, count: int, percentage: float}> $topRemotes
 * @var string $serverName
 * @var int $refreshSeconds
 * @var bool $anonymizeIp
 */
$cacheHitRatio = calculatePacketCacheRatio((int) ($metrics['packetcache-hit'] ?? 0), (int) ($metrics['packetcache-miss'] ?? 0));
$udpQueries = (int) ($metrics['udp-queries'] ?? 0);
$tcpQueries = (int) ($metrics['tcp-queries'] ?? 0);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h2 class="h5 mb-0"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>DNS Telemetry & Analytics</h2>
    <p class="text-secondary small mb-0">Real-time statistics, cache efficiency ratio, and ring buffers for PowerDNS Authoritative: <strong><?= e($serverName) ?></strong></p>
  </div>
  <div class="d-flex flex-wrap gap-2 align-items-center">
    <div class="dropdown">
      <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
        <i class="fa-solid fa-clock me-1"></i> Auto-refresh: <?= $refreshSeconds > 0 ? $refreshSeconds . 's' : 'Off' ?>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item <?= $refreshSeconds === 0 ? 'active' : '' ?>" href="/analytics?refresh=0&mask=<?= $anonymizeIp ? '1' : '0' ?>">Off</a></li>
        <li><a class="dropdown-item <?= $refreshSeconds === 15 ? 'active' : '' ?>" href="/analytics?refresh=15&mask=<?= $anonymizeIp ? '1' : '0' ?>">Every 15 Seconds</a></li>
        <li><a class="dropdown-item <?= $refreshSeconds === 30 ? 'active' : '' ?>" href="/analytics?refresh=30&mask=<?= $anonymizeIp ? '1' : '0' ?>">Every 30 Seconds</a></li>
        <li><a class="dropdown-item <?= $refreshSeconds === 60 ? 'active' : '' ?>" href="/analytics?refresh=60&mask=<?= $anonymizeIp ? '1' : '0' ?>">Every 60 Seconds</a></li>
      </ul>
    </div>
    <a href="/analytics/export?format=json" class="btn btn-sm btn-outline-primary" title="Download metrics snapshot in JSON format">
      <i class="fa-solid fa-download me-1"></i> Export JSON
    </a>
  </div>
</div>

<?php
$cacheHitGaugeColor = '#ef4444';
if ($cacheHitRatio >= 70) {
    $cacheHitGaugeColor = '#10b981';
} elseif ($cacheHitRatio >= 40) {
    $cacheHitGaugeColor = '#f59e0b';
}
?>
<!-- Primary Metric Gauges -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="panel text-center h-100 d-flex flex-column justify-content-center align-items-center py-4">
      <h3 class="h6 text-secondary mb-3"><i class="fa-solid fa-gauge-high me-1"></i>Packet Cache Hit Ratio</h3>
      <div class="my-2">
        <?= renderSvgDonutGauge($cacheHitRatio, 'Cache Hit', $cacheHitGaugeColor, 170) ?>
      </div>
      <div class="d-flex justify-content-center gap-3 mt-3 small text-secondary">
        <div>Hits: <strong class="text-success"><?= number_format((int) ($metrics['packetcache-hit'] ?? 0)) ?></strong></div>
        <div>Miss: <strong class="text-danger"><?= number_format((int) ($metrics['packetcache-miss'] ?? 0)) ?></strong></div>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="panel h-100 d-flex flex-column justify-content-between">
      <h3 class="h6 text-secondary mb-3"><i class="fa-solid fa-chart-column me-1"></i>Query Volume & Transport Protocol</h3>
      <div class="stat-grid mb-3">
        <article>
          <span>UDP Queries</span>
          <strong><?= number_format($udpQueries) ?></strong>
        </article>
        <article>
          <span>TCP Queries</span>
          <strong><?= number_format($tcpQueries) ?></strong>
        </article>
        <article>
          <span>Total DNS Queries</span>
          <strong><?= number_format((int) ($metrics['udp-queries'] ?? 0) + (int) ($metrics['tcp-queries'] ?? 0)) ?></strong>
        </article>
        <article>
          <span>Corrupt Packets</span>
          <strong><?= number_format((int) ($metrics['corrupt-packets'] ?? 0)) ?></strong>
        </article>
      </div>

      <div class="border-top border-secondary-subtle pt-3">
        <div class="d-flex justify-content-between small text-secondary mb-1">
          <span><i class="fa-solid fa-square text-primary me-1"></i>UDP: <?= number_format($udpQueries) ?></span>
          <span><i class="fa-solid fa-square text-purple me-1" style="color: #8b5cf6;"></i>TCP: <?= number_format($tcpQueries) ?></span>
        </div>
        <?= renderSvgProtocolRatio($udpQueries, $tcpQueries, 480, 16) ?>
      </div>
    </div>
  </div>
</div>

<!-- Ring Buffers Top Queried Domains & Remotes -->
<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="panel h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h6 mb-0"><i class="fa-solid fa-fire me-2 text-danger"></i>Top 10 Most Queried Domains</h3>
        <span class="badge bg-secondary-subtle text-secondary small">Ring Buffer</span>
      </div>
      <?= renderSvgHorizontalBarChart($topQueries, 460, 30) ?>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="panel h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h6 mb-0"><i class="fa-solid fa-network-wired me-2 text-info"></i>Top 10 Resolvers / Remote IPs</h3>
        <div class="form-check form-switch mb-0">
          <input class="form-check-input" type="checkbox" id="mask-ip" <?= $anonymizeIp ? 'checked' : '' ?> onchange="window.location.href='/analytics?refresh=<?= $refreshSeconds ?>&mask=' + (this.checked ? '1' : '0');">
          <label class="form-check-label small text-secondary" for="mask-ip">Mask IP (Privacy)</label>
        </div>
      </div>
      <?= renderSvgHorizontalBarChart($topRemotes, 460, 30) ?>
    </div>
  </div>
</div>

<?php if ($refreshSeconds > 0) : ?>
  <script>
    setTimeout(function() {
      window.location.reload();
    }, <?= $refreshSeconds * 1000 ?>);
  </script>
<?php endif; ?>
