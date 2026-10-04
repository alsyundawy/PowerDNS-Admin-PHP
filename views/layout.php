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
<html lang="id" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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
  <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous"
        onerror="this.onerror=null;this.href='/assets/vendor/bootstrap.min.css';">
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="app-body">
  <header class="mobile-nav-bar d-lg-none">
    <a class="brand-mini" href="/">
      <span class="brand-mark"><i class="fa-solid fa-bolt"></i></span>
      <span class="fw-bold">PowerDNS</span>
    </a>
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-sm btn-outline-light theme-toggle-btn"
              type="button" aria-label="Ganti mode tema">
        <i class="fa-solid fa-moon text-warning"></i>
      </button>
      <button class="btn btn-sm btn-outline-light" id="sidebar-toggle"
              type="button" aria-label="Toggle navigasi" aria-expanded="false" aria-controls="app-sidebar">
        <i class="fa-solid fa-bars"></i>
      </button>
    </div>
  </header>
  <aside class="sidebar" id="app-sidebar">
    <a class="brand" href="/">
      <span class="brand-mark"><i class="fa-solid fa-shield-halved"></i></span>
      <span>
        <strong>PowerDNS Admin</strong>
        <small>PHP native &bull; v0.2.1</small>
      </span>
    </a>
    <nav aria-label="Menu navigasi utama">
      <a class="<?= ($title ?? '') === 'Dasbor' ? 'active' : '' ?>" href="/">
        <i class="fa-solid fa-gauge fa-fw"></i> Dasbor
      </a>
      <a class="<?= ($title ?? '') === 'Zona' ? 'active' : '' ?>" href="/zones">
        <i class="fa-solid fa-globe fa-fw"></i> Zona
      </a>
      <a class="<?= ($title ?? '') === 'Pencarian' ? 'active' : '' ?>" href="/search">
        <i class="fa-solid fa-magnifying-glass fa-fw"></i> Pencarian
      </a>

      <div class="nav-section-title">Alat Jaringan</div>
      <?php if (in_array($user['role'] ?? '', ['admin', 'operator'], true)) : ?>
        <a class="<?= str_contains($title ?? '', 'rDNS') ? 'active' : '' ?>" href="/tools/rdns">
          <i class="fa-solid fa-network-wired fa-fw"></i> Subnet rDNS
        </a>
      <?php endif; ?>
      <a class="<?= str_contains($title ?? '', 'IPCalc') ? 'active' : '' ?>" href="/tools/ipcalc">
        <i class="fa-solid fa-calculator fa-fw"></i> IPCalc & IPv6
      </a>
      <a class="<?= str_contains($title ?? '', 'WHOIS') ? 'active' : '' ?>" href="/tools/whois">
        <i class="fa-solid fa-id-card fa-fw"></i> WHOIS & RDAP
      </a>
      <a class="<?= str_contains($title ?? '', 'DNS Lookup') ? 'active' : '' ?>" href="/tools/dns-lookup">
        <i class="fa-solid fa-satellite-dish fa-fw"></i> DNS Lookup
      </a>

      <?php if (in_array($user['role'] ?? '', ['admin', 'operator'], true)) : ?>
        <div class="nav-section-title">Manajemen</div>
        <a class="<?= ($title ?? '') === 'Template' ? 'active' : '' ?>" href="/templates">
          <i class="fa-solid fa-layer-group fa-fw"></i> Template
        </a>
        <a class="<?= ($title ?? '') === 'Akun' ? 'active' : '' ?>" href="/accounts">
          <i class="fa-solid fa-users fa-fw"></i> Akun
        </a>
        <a class="<?= ($title ?? '') === 'API key' ? 'active' : '' ?>" href="/apikeys">
          <i class="fa-solid fa-key fa-fw"></i> API key
        </a>
      <?php endif; ?>

      <?php if (($user['role'] ?? '') === 'admin') : ?>
        <div class="nav-section-title">Sistem</div>
        <a class="<?= ($title ?? '') === 'Pengguna' ? 'active' : '' ?>" href="/users">
          <i class="fa-solid fa-user-shield fa-fw"></i> Pengguna
        </a>
        <a class="<?= ($title ?? '') === 'Audit' ? 'active' : '' ?>" href="/audit">
          <i class="fa-solid fa-clipboard-list fa-fw"></i> Audit
        </a>
        <a class="<?= ($title ?? '') === 'Pengaturan' ? 'active' : '' ?>" href="/settings">
          <i class="fa-solid fa-sliders fa-fw"></i> Pengaturan
        </a>
      <?php endif; ?>
    </nav>

    <div class="sidebar-foot">
      <button class="theme-toggle-btn" type="button" aria-label="Ganti tema">
        <span class="d-flex align-items-center gap-2">
          <i class="fa-solid fa-sun text-warning"></i>
          <span class="theme-label">Mode Gelap</span>
        </span>
        <span class="badge bg-secondary-subtle text-secondary small">2026</span>
      </button>

      <div class="who"><?= e((string) (!empty($user['display_name']) ? $user['display_name'] : ($user['username'] ?? 'Pengguna'))) ?></div>
      <div class="role"><?= e((string) ($user['role'] ?? 'user')) ?></div>
      <form method="post" action="/logout">
        <?= csrfField() ?>
        <button class="btn btn-sm btn-outline-light w-100" type="submit">
          <i class="fa-solid fa-right-from-bracket me-1"></i> Keluar
        </button>
      </form>
    </div>
  </aside>
  <div class="sidebar-backdrop" id="sidebar-backdrop" aria-hidden="true"></div>
  <main class="main">
    <header class="topbar">
      <div>
        <h1><?= e($title ?? '') ?></h1>
        <p>Panel otoritatif. Record hidup di PowerDNS, bukan di database ini.</p>
      </div>
    </header>
    <?php if (!empty($flash)) : ?>
      <div class="alert alert-<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <?= $content ?>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"
          integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs"
          crossorigin="anonymous"></script>
  <script>
    window.jQuery || document.write('<script src="/assets/vendor/jquery.min.js"><\/script>');
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
          integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
          crossorigin="anonymous"></script>
  <script>
    (typeof bootstrap !== 'undefined') ||
      document.write('<script src="/assets/vendor/bootstrap.bundle.min.js"><\/script>');
  </script>
  <script src="/assets/app.js"></script>
</body>
</html>
