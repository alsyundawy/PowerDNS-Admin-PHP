<form method="post" action="/accounts" class="panel stack mb-3">
  <?= csrf_field() ?>
  <div class="row g-2">
    <div class="col-md-4"><input class="form-control" name="name" placeholder="Nama akun" required></div>
    <div class="col-md-4"><input class="form-control" name="contact" placeholder="Kontak"></div>
    <div class="col-md-3"><input class="form-control" name="notes" placeholder="Catatan"></div>
    <div class="col-md-1"><button class="btn btn-primary w-100" type="submit">Tambah</button></div>
  </div>
</form>
<div class="panel">
  <table class="table">
    <thead><tr><th>Akun</th><th>Kontak</th><th>Zona</th></tr></thead>
    <tbody>
    <?php foreach ($accounts as $a): ?>
      <tr><td><?= e($a['name']) ?></td><td><?= e($a['contact']) ?></td><td><?= (int) $a['zone_count'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
