<?php

declare(strict_types=1);

/**
 * @var string $title
 * @var array<string, mixed> $user
 * @var string $content
 * @var array{type: string, message: string}|null $flash
 */

?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
  <meta name="description" content="PowerDNS Authoritative Server Management Panel - Native PHP">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <meta name="theme-color" content="#0b0f19">
  <meta name="color-scheme" content="dark light">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title><?= e($title ?? 'PowerDNS-Admin-PHP') ?></title>
  <script>
    (function() {
      const savedTheme = localStorage.getItem('pdns_theme') || 'dark';
      document.documentElement.setAttribute('data-theme', savedTheme);
    })();
  </script>
  <link rel="stylesheet" href="/assets/vendor/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="app-body">
  <header class="mobile-nav-bar d-lg-none">
    <a class="brand-mini" href="/">
      <?php if (appLogoUrl()) : ?>
        <img src="<?= e((string) appLogoUrl()) ?>" alt="Logo" class="brand-logo-mini">
      <?php else : ?>
        <span class="brand-mark"><i class="fa-solid fa-bolt"></i></span>
      <?php endif; ?>
      <span class="fw-bold"><?= e(appName()) ?></span>
    </a>
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-sm btn-outline-light theme-toggle-btn"
              type="button" aria-label="Toggle theme mode">
        <i class="fa-solid fa-moon text-warning"></i>
      </button>
      <button class="btn btn-sm btn-outline-light" id="sidebar-toggle"
              type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="app-sidebar">
        <i class="fa-solid fa-bars"></i>
      </button>
    </div>
  </header>
  <aside class="sidebar" id="app-sidebar">
    <a class="brand" href="/">
      <?php if (appLogoUrl()) : ?>
        <img src="<?= e((string) appLogoUrl()) ?>" alt="Logo" class="brand-logo-img">
      <?php else : ?>
        <span class="brand-mark"><i class="fa-solid fa-shield-halved"></i></span>
      <?php endif; ?>
      <span>
        <strong><?= e(appName()) ?></strong>
        <small>PHP native &bull; v0.3.0</small>
      </span>
    </a>
    <nav aria-label="Main navigation menu">
      <a class="<?= ($title ?? '') === 'Dashboard' ? 'active' : '' ?>" href="/">
        <i class="fa-solid fa-gauge fa-fw"></i> Dashboard
      </a>
      <a class="<?= ($title ?? '') === 'Zones' ? 'active' : '' ?>" href="/zones">
        <i class="fa-solid fa-globe fa-fw"></i> Zones
      </a>
      <a class="<?= ($title ?? '') === 'Search' ? 'active' : '' ?>" href="/search">
        <i class="fa-solid fa-magnifying-glass fa-fw"></i> Search
      </a>
      <a class="<?= ($title ?? '') === 'User Profile' ? 'active' : '' ?>" href="/profile">
        <i class="fa-solid fa-user fa-fw"></i> My Profile
      </a>

      <div class="nav-section-title">Network Tools</div>
      <?php if (in_array($user['role'] ?? '', ['admin', 'operator'], true)) : ?>
        <a class="<?= str_contains($title ?? '', 'rDNS') ? 'active' : '' ?>" href="/tools/rdns">
          <i class="fa-solid fa-network-wired fa-fw"></i> Subnet rDNS
        </a>
      <?php endif; ?>
      <a class="<?= str_contains($title ?? '', 'IPCalc') ? 'active' : '' ?>" href="/tools/ipcalc">
        <i class="fa-solid fa-calculator fa-fw"></i> IPCalc &amp; IPv6
      </a>
      <a class="<?= str_contains($title ?? '', 'WHOIS') ? 'active' : '' ?>" href="/tools/whois">
        <i class="fa-solid fa-id-card fa-fw"></i> WHOIS &amp; RDAP
      </a>
      <a class="<?= str_contains($title ?? '', 'DNS Lookup') ? 'active' : '' ?>" href="/tools/dns-lookup">
        <i class="fa-solid fa-satellite-dish fa-fw"></i> DNS Lookup
      </a>

      <?php if (in_array($user['role'] ?? '', ['admin', 'operator'], true)) : ?>
        <div class="nav-section-title">Management</div>
        <a class="<?= ($title ?? '') === 'Templates' ? 'active' : '' ?>" href="/templates">
          <i class="fa-solid fa-layer-group fa-fw"></i> Templates
        </a>
        <a class="<?= ($title ?? '') === 'Accounts' ? 'active' : '' ?>" href="/accounts">
          <i class="fa-solid fa-users fa-fw"></i> Accounts
        </a>
        <a class="<?= ($title ?? '') === 'API Keys' ? 'active' : '' ?>" href="/apikeys">
          <i class="fa-solid fa-key fa-fw"></i> API Keys
        </a>
        <a class="<?= str_contains($title ?? '', 'Bulk Records') ? 'active' : '' ?>" href="/bulk-records">
          <i class="fa-solid fa-list-check fa-fw"></i> Bulk Records
        </a>
      <?php endif; ?>

      <?php if (($user['role'] ?? '') === 'admin') : ?>
        <div class="nav-section-title">System</div>
        <a class="<?= str_contains($title ?? '', 'Cluster') || str_contains($title ?? '', 'Node') ? 'active' : '' ?>" href="/servers">
          <i class="fa-solid fa-server fa-fw"></i> Server Nodes
        </a>
        <a class="<?= str_contains($title ?? '', 'Webhooks') ? 'active' : '' ?>" href="/webhooks">
          <i class="fa-solid fa-bolt fa-fw"></i> Webhooks
        </a>
        <a class="<?= str_contains($title ?? '', 'Analytics') ? 'active' : '' ?>" href="/analytics">
          <i class="fa-solid fa-chart-pie fa-fw"></i> DNS Analytics
        </a>
        <a class="<?= ($title ?? '') === 'Users' ? 'active' : '' ?>" href="/users">
          <i class="fa-solid fa-user-shield fa-fw"></i> Users
        </a>
        <a class="<?= ($title ?? '') === 'Backup & Restore' ? 'active' : '' ?>" href="/backup">
          <i class="fa-solid fa-database fa-fw"></i> Backup &amp; Restore
        </a>
        <a class="<?= ($title ?? '') === 'Audit Log' ? 'active' : '' ?>" href="/audit">
          <i class="fa-solid fa-clipboard-list fa-fw"></i> Audit
        </a>
        <a class="<?= ($title ?? '') === 'Settings' ? 'active' : '' ?>" href="/settings">
          <i class="fa-solid fa-sliders fa-fw"></i> Settings
        </a>
      <?php endif; ?>
    </nav>

    <div class="sidebar-foot">
      <button class="theme-toggle-btn" type="button" aria-label="Toggle theme">
        <span class="d-flex align-items-center gap-2">
          <i class="fa-solid fa-sun text-warning"></i>
          <span class="theme-label">Dark Mode</span>
        </span>
        <span class="badge bg-secondary-subtle text-secondary small">2026</span>
      </button>

      <a href="/profile" class="sidebar-user-card text-decoration-none">
        <div class="sidebar-avatar-wrap">
          <?php if (userAvatar($user) !== '') : ?>
            <img src="<?= e(userAvatar($user)) ?>" alt="Avatar" class="sidebar-avatar-img">
          <?php else : ?>
            <div class="sidebar-avatar-initials">
              <?= e(strtoupper(substr((string) ($user['username'] ?? 'U'), 0, 2))) ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="sidebar-user-info">
          <div class="who"><?= e((string) (!empty($user['display_name']) ? $user['display_name'] : ($user['username'] ?? 'User'))) ?></div>
          <div class="role"><?= e((string) ($user['role'] ?? 'user')) ?></div>
        </div>
      </a>

      <form method="post" action="/logout">
        <?= csrfField() ?>
        <button class="btn btn-sm btn-outline-light w-100" type="submit">
          <i class="fa-solid fa-right-from-bracket me-1"></i> Sign Out
        </button>
      </form>
    </div>
  </aside>
  <div class="sidebar-backdrop" id="sidebar-backdrop" aria-hidden="true"></div>
  <main class="main">
    <header class="topbar d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h1 class="mb-0 fs-5"><?= e($title ?? '') ?></h1>
        <p class="mb-0 small text-secondary">Authoritative panel. Records live in PowerDNS, not in this database.</p>
      </div>
      <?php if (!empty($user) && in_array($user['role'] ?? '', ['admin', 'operator'], true)) : ?>
        <?php
          $clusterServers = class_exists('PdnsCluster') ? PdnsCluster::listServers() : [];
          $activeServer = class_exists('PdnsCluster') ? PdnsCluster::getActiveServer() : null;
          ?>
        <?php if (!empty($clusterServers)) : ?>
          <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="badge bg-success rounded-pill" style="width: 8px; height: 8px; padding: 0;"></span>
              <i class="fa-solid fa-server small"></i>
              <span>Node: <strong><?= e((string) ($activeServer['name'] ?? 'Default')) ?></strong></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
              <li><h6 class="dropdown-header">Select PowerDNS Node</h6></li>
              <?php foreach ($clusterServers as $srv) : ?>
                <li>
                  <a class="dropdown-item d-flex justify-content-between align-items-center <?= ((int) ($activeServer['id'] ?? 0) === (int) $srv['id']) ? 'active' : '' ?>" href="/servers/switch?id=<?= (int) $srv['id'] ?>">
                    <span><?= e((string) $srv['name']) ?></span>
                    <?php if (isset($srv['latency_ms'])) : ?>
                      <span class="badge bg-secondary-subtle text-secondary small ms-2"><?= (int) $srv['latency_ms'] ?>ms</span>
                    <?php endif; ?>
                  </a>
                </li>
              <?php endforeach; ?>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item small text-primary" href="/servers"><i class="fa-solid fa-gear me-1"></i> Manage Cluster Nodes</a></li>
            </ul>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </header>
    <?php if (!empty($flash)) : ?>
      <div class="alert alert-<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <?= $content ?>

    <footer class="app-footer text-secondary small py-3 mt-4 border-top border-secondary-subtle">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <?= e(appFooterText()) ?>
        </div>
        <div class="d-flex gap-3">
          <span>v0.3.0</span>
          <?php if (($user['role'] ?? '') === 'admin') : ?>
            <a href="/backup" class="text-secondary text-decoration-none">Backup</a>
            <a href="/settings" class="text-secondary text-decoration-none">Settings</a>
          <?php endif; ?>
        </div>
      </div>
    </footer>
  </main>
  <script src="/assets/vendor/jquery.min.js"></script>
  <script src="/assets/vendor/bootstrap.bundle.min.js"></script>
  <script src="/assets/app.js"></script>
</body>
</html>
