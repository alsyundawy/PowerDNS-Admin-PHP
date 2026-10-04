<?php

declare(strict_types=1);

/**
 * @var string $title
 * @var string $message
 */

?>
<div class="panel text-center py-5">
  <h1 class="h4 text-danger mb-3"><?= e($title ?? 'Kesalahan') ?></h1>
  <p class="muted mb-4"><?= e($message ?? 'Terjadi kesalahan pada permintaan Anda.') ?></p>
  <a class="btn btn-outline-primary" href="/zones">Kembali ke Daftar Zona</a>
</div>
