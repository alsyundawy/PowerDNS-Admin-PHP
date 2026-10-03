<div class="toolbar">
  <form class="search-inline" method="get" action="/zones">
    <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Cari zona">
    <select class="form-select" name="kind">
      <option value="">Semua jenis</option>
      <?php foreach (['Native','Master','Slave','Producer','Consumer'] as $k): ?>
        <option value="<?= e($k) ?>" <?= $kind === $k ? 'selected' : '' ?>><?= e($k) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-primary" type="submit">Saring</button>
  </form>
  <div class="d-flex gap-2">
    <?php if (in_array($user['role'], ['admin','operator'], true)): ?>
      <form method="post" action="/zones/sync"><?= csrf_field() ?><button class="btn btn-outline-secondary" type="submit">Sinkron</button></form>
      <a class="btn btn-primary" href="/zones/new">Zona baru</a>
    <?php endif; ?>
  </div>
</div>
<div class="panel mt-3">
  <table class="table align-middle">
    <thead><tr><th>Zona</th><th>Jenis</th><th>Akun</th><th>Serial</th><th>DNSSEC</th></tr></thead>
    <tbody>
    <?php foreach ($zones as $z): ?>
      <tr>
        <td><a href="/zones/<?= e(rawurlencode(rtrim($z['name'], '.'))) ?>"><?= e(dns_display($z['name'])) ?></a></td>
        <td><span class="pill"><?= e($z['kind']) ?></span></td>
        <td><?= e($z['account_name'] ?: '–') ?></td>
        <td><?= e((string) ($z['serial'] ?? '')) ?></td>
        <td><?= (int) $z['dnssec'] ? 'ya' : 'tidak' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$zones): ?><tr><td colspan="5" class="muted">Belum ada zona di cache. Jalankan sinkron setelah API terhubung.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
