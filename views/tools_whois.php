<?php

declare(strict_types=1);

/**
 * @var string $title
 * @var array<string, mixed> $user
 * @var string $query
 * @var string $server
 * @var string $mode
 * @var array<string, mixed>|null $rdapResult
 * @var array<string, mixed>|null $socketResult
 * @var string|null $error
 */

?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h2 class="h4 mb-1 text-light">
      <i class="fa-solid fa-id-card text-info me-2"></i>WHOIS &amp; RDAP Lookup
    </h2>
    <p class="text-secondary small mb-0">Inspect domain registration data and IP address allocations (RFC 9082 RDAP JSON &amp; RFC 3912 Port 43 Socket).</p>
  </div>
</div>

<?php if (!empty($error)) : ?>
  <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
    <i class="fa-solid fa-circle-exclamation flex-shrink-0"></i>
    <div><?= e($error) ?></div>
  </div>
<?php endif; ?>

<div class="panel">
  <form method="get" action="/tools/whois" class="row g-2 align-items-end mb-2">
    <div class="col-md-6 col-12">
      <label for="whois-query-input" class="form-label small fw-semibold">Domain or IP Address</label>
      <div class="input-group">
        <span class="input-group-text bg-dark border-secondary text-secondary">
          <i class="fa-solid fa-magnifying-glass"></i>
        </span>
        <input type="text" class="form-control" id="whois-query-input" name="query"
               value="<?= e($query ?? '') ?>"
               placeholder="Example: powerdns.com or 8.8.8.8 or 2001:4860:4860::8888" required autofocus>
      </div>
    </div>
    <div class="col-md-3 col-6">
      <label for="whois-mode-select" class="form-label small fw-semibold">Protocol</label>
      <select class="form-select" id="whois-mode-select" name="mode">
        <option value="rdap" <?= ($mode ?? 'rdap') === 'rdap' ? 'selected' : '' ?>>RDAP (Modern JSON)</option>
        <option value="socket" <?= ($mode ?? 'rdap') === 'socket' ? 'selected' : '' ?>>WHOIS (Port 43 Socket)</option>
      </select>
    </div>
    <div class="col-md-3 col-6">
      <button type="submit" class="btn btn-primary w-100">
        <i class="fa-solid fa-search me-1"></i>WHOIS Lookup
      </button>
    </div>
  </form>

  <div class="d-flex flex-wrap gap-2 pt-1">
    <span class="text-secondary small align-self-center">Quick examples:</span>
    <a href="/tools/whois?query=google.com&mode=rdap" class="badge bg-secondary-subtle text-light text-decoration-none">google.com</a>
    <a href="/tools/whois?query=powerdns.com&mode=rdap" class="badge bg-secondary-subtle text-light text-decoration-none">powerdns.com</a>
    <a href="/tools/whois?query=1.1.1.1&mode=rdap" class="badge bg-secondary-subtle text-light text-decoration-none">1.1.1.1</a>
    <a href="/tools/whois?query=2001%3A4860%3A4860%3A%3A8888&mode=rdap" class="badge bg-secondary-subtle text-light text-decoration-none">Google DNS IPv6</a>
  </div>
</div>

<?php if (!empty($rdapResult) && ($rdapResult['success'] ?? false)) : ?>
  <?php $d = $rdapResult['data'] ?? []; ?>
  <div class="panel">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div>
        <h3 class="h5 mb-0 text-info fw-bold">
          <i class="fa-solid fa-circle-check text-success me-2"></i><?= e((string) ($d['name'] ?? $d['handle'] ?? $query)) ?>
        </h3>
        <small class="text-secondary">Protocol: RDAP HTTPS &bull; Type: <?= e((string) ($rdapResult['type'] ?? 'Domain')) ?></small>
      </div>
      <div class="d-flex gap-2">
        <a href="/tools/dns-lookup?domain=<?= urlencode((string) ($d['name'] ?? $query)) ?>" class="btn btn-sm btn-outline-info">
          <i class="fa-solid fa-satellite-dish me-1"></i>DNS Lookup &raquo;
        </a>
      </div>
    </div>

    <!-- RDAP Summary Cards -->
    <div class="ipcalc-result-grid mb-3">
      <?php if (!empty($d['registrar'])) : ?>
        <div class="ipcalc-card">
          <div class="ipcalc-card-title">Registrar</div>
          <div class="ipcalc-card-value text-light"><?= e((string) $d['registrar']) ?></div>
        </div>
      <?php endif; ?>
      <?php if (!empty($d['country'])) : ?>
        <div class="ipcalc-card">
          <div class="ipcalc-card-title">Country</div>
          <div class="ipcalc-card-value text-light"><?= e((string) $d['country']) ?></div>
        </div>
      <?php endif; ?>
      <?php if (!empty($d['startAddress'])) : ?>
        <div class="ipcalc-card">
          <div class="ipcalc-card-title">IP Address Range</div>
          <div class="ipcalc-card-value text-light"><?= e((string) $d['startAddress']) ?> - <?= e((string) $d['endAddress']) ?></div>
        </div>
      <?php endif; ?>
      <?php if (!empty($d['type'])) : ?>
        <div class="ipcalc-card">
          <div class="ipcalc-card-title">Allocation Type</div>
          <div class="ipcalc-card-value text-light"><?= e((string) $d['type']) ?></div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Status Flags -->
    <?php if (!empty($d['status']) && is_array($d['status'])) : ?>
      <div class="card mb-3 border-secondary">
        <div class="card-header py-2 small fw-semibold text-secondary">
          <i class="fa-solid fa-shield-halved me-1"></i>Status EPP / Domain State
        </div>
        <div class="card-body py-2 d-flex flex-wrap gap-2">
          <?php foreach ($d['status'] as $st) : ?>
            <span class="pill ok"><?= e((string) $st) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Events Timeline -->
    <?php if (!empty($d['events']) && is_array($d['events'])) : ?>
      <div class="card mb-3 border-secondary">
        <div class="card-header py-2 small fw-semibold text-secondary">
          <i class="fa-solid fa-calendar-days me-1"></i>Important Events Timeline
        </div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead>
              <tr>
                <th scope="col" class="text-secondary" style="width: 220px;">Event</th>
                <th scope="col" class="text-secondary">Date / Time</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($d['events'] as $ev) : ?>
                <tr>
                  <th scope="row" class="text-secondary text-capitalize fw-normal" style="width: 220px;"><?= e(str_replace('_', ' ', (string) ($ev['eventAction'] ?? ''))) ?>:</th>
                  <td><strong><?= e((string) ($ev['eventDate'] ?? '')) ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- Nameservers -->
    <?php if (!empty($d['nameservers']) && is_array($d['nameservers'])) : ?>
      <div class="card mb-3 border-secondary">
        <div class="card-header py-2 small fw-semibold text-secondary">
          <i class="fa-solid fa-server me-1"></i>Authoritative Nameserver Delegation
        </div>
        <div class="card-body py-2 d-flex flex-wrap gap-2">
          <?php foreach ($d['nameservers'] as $ns) : ?>
            <a href="/tools/dns-lookup?domain=<?= urlencode((string) ($ns['ldhName'] ?? '')) ?>" class="badge bg-dark border border-secondary text-info text-decoration-none p-2">
              <i class="fa-solid fa-globe me-1"></i><?= e((string) ($ns['ldhName'] ?? '')) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Raw RDAP JSON Collapsible -->
    <details class="card border-secondary mt-3">
      <summary class="card-header py-2 small fw-semibold text-secondary" style="cursor: pointer;">
        <i class="fa-solid fa-code me-1"></i>View Raw RDAP JSON Response
      </summary>
      <div class="card-body p-2">
        <div class="raw-output-box"><?= e(json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></div>
      </div>
    </details>
  </div>
<?php endif; ?>

<?php if (!empty($socketResult) && ($socketResult['success'] ?? false)) : ?>
  <div class="panel">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div>
        <h3 class="h6 mb-0 text-info fw-bold">
          <i class="fa-solid fa-terminal me-2"></i>Raw WHOIS Socket Results (Port 43)
        </h3>
        <small class="text-secondary">Server: <?= e((string) ($socketResult['server'] ?? 'whois.iana.org')) ?></small>
      </div>
      <button class="btn btn-sm btn-outline-secondary btn-copy-target" data-target="whois-raw-output" type="button">
        <i class="fa-solid fa-copy me-1"></i>Copy Output
      </button>
    </div>

    <div class="raw-output-box" id="whois-raw-output"><?= e((string) ($socketResult['raw'] ?? '')) ?></div>
  </div>
<?php endif; ?>
