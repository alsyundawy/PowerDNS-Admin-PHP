<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'PowerDNS-Admin-PHP') ?></title>
  <link rel="stylesheet" href="/assets/vendor/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="app-body">
  <aside class="sidebar">
    <a class="brand" href="/">
      <span class="brand-mark">PD</span>
      <span>
        <strong>PowerDNS Admin</strong>
        <small>PHP native</small>
      </span>
    </a>
    <nav>
      <a class="<?= ($title ?? '') === 'Dasbor' ? 'active' : '' ?>" href="/">Dasbor</a>
      <a href="/zones">Zona</a>
      <a href="/search">Pencarian</a>
      <?php if (in_array($user['role'] ?? '', ['admin','operator'], true)): ?>
        <a href="/templates">Template</a>
        <a href="/accounts">Akun</a>
        <a href="/apikeys">API key</a>
      <?php endif; ?>
      <?php if (($user['role'] ?? '') === 'admin'): ?>
        <a href="/users">Pengguna</a>
        <a href="/audit">Audit</a>
        <a href="/settings">Pengaturan</a>
      <?php endif; ?>
    </nav>
    <div class="sidebar-foot">
      <div class="who"><?= e($user['display_name'] ?: $user['username']) ?></div>
      <div class="role"><?= e($user['role']) ?></div>
      <form method="post" action="/logout"><?= csrf_field() ?><button class="btn btn-sm btn-outline-light w-100" type="submit">Keluar</button></form>
    </div>
  </aside>
  <main class="main">
    <header class="topbar">
      <div>
        <h1><?= e($title ?? '') ?></h1>
        <p>Panel otoritatif. Record hidup di PowerDNS, bukan di database ini.</p>
      </div>
    </header>
    <?php if (!empty($flash)): ?>
      <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <?= $content ?>
  </main>
  <script src="/assets/vendor/jquery.min.js"></script>
  <script src="/assets/vendor/bootstrap.bundle.min.js"></script>
  <script src="/assets/app.js"></script>
</body>
</html>
