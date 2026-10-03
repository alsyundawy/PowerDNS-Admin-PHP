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
<body class="auth-body">
  <div class="auth-card">
    <?= $content ?>
  </div>
</body>
</html>
