<?php

declare(strict_types=1);

/**
 * @var string $q
 * @var array<int, array<string, mixed>> $results
 * @var string|null $error
 */

?>
<form class="search-inline mb-3" method="get" action="/search">
  <input class="form-control" name="q" value="<?= e($q) ?>"
         placeholder="Cari nama host, tipe record, zona, atau konten..." aria-label="Query pencarian">
  <button class="btn btn-primary" type="submit">Cari Data</button>
</form>

<?php if (!empty($error)) : ?>
  <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<div class="panel">
  <header>
    <h2>Hasil Pencarian Data</h2>
  </header>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th scope="col">Tipe Objek</th>
          <th scope="col">Nama</th>
          <th scope="col">Zona Otoritatif</th>
          <th scope="col">Isi Data</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($results as $r) : ?>
            <?php if (is_array($r)) : ?>
        <tr>
          <td><span class="badge bg-secondary"><?= e((string) ($r['object_type'] ?? '')) ?></span></td>
          <td><strong><?= e((string) ($r['name'] ?? '')) ?></strong></td>
          <td>
                <?php $z = (string) ($r['zone'] ?? $r['zone_id'] ?? ''); ?>
                <?php if ($z !== '') : ?>
              <a href="/zones/<?= e(rawurlencode(rtrim($z, '.'))) ?>"><?= e(dnsDisplay($z)) ?></a>
                <?php else : ?>
              –
                <?php endif; ?>
          </td>
          <td><code><?= e((string) ($r['content'] ?? '')) ?></code></td>
        </tr>
            <?php endif; ?>
      <?php endforeach; ?>
      <?php if (!$results && $q !== '') : ?>
        <tr>
          <td colspan="4" class="text-center py-4 muted">Tidak ditemukan data yang cocok dengan kueri Anda.</td>
        </tr>
      <?php elseif (!$results) : ?>
        <tr>
          <td colspan="4" class="text-center py-4 muted">
            Ketik kata kunci di atas untuk mencari lintas seluruh zona PowerDNS.
          </td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
