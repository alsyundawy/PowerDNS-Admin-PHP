<form method="post" action="/templates" class="panel stack mb-3">
  <?= csrf_field() ?>
  <label>Nama<input class="form-control" name="name" required></label>
  <label>Deskripsi<input class="form-control" name="description"></label>
  <p class="muted">Placeholder [ZONE] diganti nama zona saat diterapkan. Contoh nama: www, isi A: 192.0.2.10</p>
  <div class="row g-2">
    <div class="col-md-3"><input class="form-control" name="r_name[]" placeholder="nama"></div>
    <div class="col-md-2"><select class="form-select" name="r_type[]"><?php foreach ($types as $t): if ($t==='SOA') continue; ?><option><?= e($t) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><input class="form-control" name="r_ttl[]" value="3600"></div>
    <div class="col-md-5"><input class="form-control" name="r_content[]" placeholder="isi"></div>
  </div>
  <button class="btn btn-primary" type="submit">Simpan template</button>
</form>
<div class="panel">
  <table class="table">
    <thead><tr><th>Template</th><th>Deskripsi</th><th>Record</th></tr></thead>
    <tbody>
    <?php foreach ($templates as $t): ?>
      <tr><td><?= e($t['name']) ?></td><td><?= e($t['description']) ?></td><td><?= (int) $t['rec_count'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
