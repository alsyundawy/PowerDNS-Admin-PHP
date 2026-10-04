<?php

declare(strict_types=1);

/**
 * @var string $title
 * @var string $content
 */

?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="description" content="PowerDNS Authoritative Server Management Panel - Masuk / Autentikasi">
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
<body class="auth-body">
  <main class="auth-card" role="main">
    <?= $content ?>
  </main>
  <script src="/assets/app.js"></script>
</body>
</html>
