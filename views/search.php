<form class="search-inline mb-3" method="get" action="/search">
  <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="nama, record, atau catatan">
  <button class="btn btn-primary" type="submit">Cari</button>
</form>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="panel">
  <table class="table">
    <thead><tr><th>Jenis</th><th>Nama</th><th>Zona</th><th>Isi</th></tr></thead>
    <tbody>
    <?php foreach ($results as $r): if (!is_array($r)) continue; ?>
      <tr>
        <td><?= e((string) ($r['object_type'] ?? '')) ?></td>
        <td><?= e((string) ($r['name'] ?? '')) ?></td>
        <td><?= e((string) ($r['zone'] ?? $r['zone_id'] ?? '')) ?></td>
        <td><?= e((string) ($r['content'] ?? '')) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
