<div class="panel">
  <h2>Pengguna baru</h2>
  <form method="post" action="/users" class="row g-2 align-items-end">
    <?= csrf_field() ?>
    <div class="col-md-2"><input class="form-control" name="username" placeholder="username" required></div>
    <div class="col-md-2"><input class="form-control" name="display_name" placeholder="nama"></div>
    <div class="col-md-2"><input class="form-control" name="email" placeholder="email"></div>
    <div class="col-md-2"><select class="form-select" name="role"><option>user</option><option>operator</option><option>admin</option></select></div>
    <div class="col-md-2"><input class="form-control" type="password" name="password" placeholder="sandi" required></div>
    <div class="col-md-1"><input type="hidden" name="active" value="0"><label class="small"><input type="checkbox" name="active" value="1" checked> aktif</label></div>
    <div class="col-md-1"><button class="btn btn-primary" type="submit">Tambah</button></div>
  </form>
</div>
<div class="panel mt-3">
  <table class="table">
    <thead><tr><th>User</th><th>Nama</th><th>Peran</th><th>Aktif</th><th>Login terakhir</th></tr></thead>
    <tbody>
    <?php foreach ($users as $row): ?>
      <tr>
        <td><?= e($row['username']) ?></td>
        <td><?= e($row['display_name']) ?></td>
        <td><?= e($row['role']) ?></td>
        <td><?= (int) $row['active'] ? 'ya' : 'tidak' ?></td>
        <td><?= e((string) $row['last_login_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
