<?php

declare(strict_types=1);

/**
 * @var string $title
 * @var string $content
 */

?>
<!doctype html>
<html lang="id" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
  <meta name="description" content="PowerDNS Authoritative Server Management Panel - Masuk / Autentikasi">
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
<body class="auth-body">
  <main class="auth-card" role="main">
    <?= $content ?>
  </main>
  <script src="/assets/vendor/bootstrap.bundle.min.js"></script>
  <script src="/assets/app.js"></script>
</body>
</html>
