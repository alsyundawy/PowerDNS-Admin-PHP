<div class="stat-grid">
  <article><span>Zona</span><strong><?= (int) $zones ?></strong></article>
  <article><span>DNSSEC</span><strong><?= (int) $dnssec ?></strong></article>
  <article><span>Pengguna</span><strong><?= (int) $users ?></strong></article>
  <article><span>Akun</span><strong><?= (int) $accounts ?></strong></article>
</div>
<div class="row g-3 mt-1">
  <div class="col-lg-7">
    <section class="panel">
      <header><h2>PowerDNS</h2><?php if ($apiOk): ?><span class="pill ok">API hidup</span><?php else: ?><span class="pill bad">API gagal</span><?php endif; ?></header>
      <?php if ($apiError): ?><p class="muted"><?= e($apiError) ?></p><?php endif; ?>
      <div class="stat-grid compact">
        <?php foreach (['udp-queries'=>'UDP query','udp-answers'=>'UDP jawab','tcp-queries'=>'TCP query','packetcache-hit'=>'Cache hit','servfail-answers'=>'SERVFAIL'] as $k => $label): ?>
          <article><span><?= e($label) ?></span><strong><?= e($stats[$k] ?? '–') ?></strong></article>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
  <div class="col-lg-5">
    <section class="panel">
      <header><h2>Audit terbaru</h2></header>
      <ul class="feed">
        <?php foreach ($recent as $row): ?>
          <li><b><?= e($row['action']) ?></b> <span><?= e($row['zone_name']) ?></span><small><?= e($row['username']) ?> · <?= e($row['created_at']) ?></small></li>
        <?php endforeach; ?>
        <?php if (!$recent): ?><li class="muted">Belum ada jejak.</li><?php endif; ?>
      </ul>
    </section>
  </div>
</div>
