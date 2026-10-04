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
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="description" content="PowerDNS Authoritative Server Management Panel - Native PHP">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <meta name="theme-color" content="#101820">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title><?= e($title ?? 'PowerDNS-Admin-PHP') ?></title>
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
        onerror="this.onerror=null;this.href='/assets/vendor/bootstrap.min.css';">
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="app-body">
  <header class="mobile-nav-bar d-lg-none">
    <a class="brand-mini" href="/">
      <span class="brand-mark">PD</span>
      <span class="fw-bold">PowerDNS</span>
    </a>
    <button class="btn btn-sm btn-outline-light" id="sidebar-toggle"
            type="button" aria-label="Toggle navigasi" aria-expanded="false" aria-controls="app-sidebar">
      <span class="navbar-toggler-icon">☰</span>
    </button>
  </header>
  <aside class="sidebar" id="app-sidebar">
    <a class="brand" href="/">
      <span class="brand-mark">PD</span>
      <span>
        <strong>PowerDNS Admin</strong>
        <small>PHP native</small>
      </span>
    </a>
    <nav aria-label="Menu navigasi utama">
      <a class="<?= ($title ?? '') === 'Dasbor' ? 'active' : '' ?>" href="/">Dasbor</a>
      <a class="<?= ($title ?? '') === 'Zona' ? 'active' : '' ?>" href="/zones">Zona</a>
      <a class="<?= ($title ?? '') === 'Cari' ? 'active' : '' ?>" href="/search">Pencarian</a>
      <?php if (in_array($user['role'] ?? '', ['admin', 'operator'], true)) : ?>
        <a class="<?= str_contains($title ?? '', 'rDNS') ? 'active' : '' ?>" href="/tools/rdns">Subnet rDNS</a>
        <a class="<?= ($title ?? '') === 'Template' ? 'active' : '' ?>" href="/templates">Template</a>
        <a class="<?= ($title ?? '') === 'Akun' ? 'active' : '' ?>" href="/accounts">Akun</a>
        <a class="<?= ($title ?? '') === 'API key' ? 'active' : '' ?>" href="/apikeys">API key</a>
      <?php endif; ?>
      <?php if (($user['role'] ?? '') === 'admin') : ?>
        <a class="<?= ($title ?? '') === 'Pengguna' ? 'active' : '' ?>" href="/users">Pengguna</a>
        <a class="<?= ($title ?? '') === 'Audit' ? 'active' : '' ?>" href="/audit">Audit</a>
        <a class="<?= ($title ?? '') === 'Pengaturan' ? 'active' : '' ?>" href="/settings">Pengaturan</a>
      <?php endif; ?>
    </nav>
    <div class="sidebar-foot">
      <div class="who"><?= e($user['display_name'] ?: $user['username']) ?></div>
      <div class="role"><?= e((string) ($user['role'] ?? 'user')) ?></div>
      <form method="post" action="/logout">
        <?= csrfField() ?>
        <button class="btn btn-sm btn-outline-light w-100" type="submit">Keluar</button>
      </form>
    </div>
  </aside>
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
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
          integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
          crossorigin="anonymous"></script>
  <script>
    (typeof bootstrap !== 'undefined') ||
      document.write('<script src="/assets/vendor/bootstrap.bundle.min.js"><\/script>');
  </script>
  <script src="/assets/app.js"></script>
</body>
</html>
