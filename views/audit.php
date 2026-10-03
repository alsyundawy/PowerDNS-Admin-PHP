<div class="panel">
  <table class="table">
    <thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Zona</th><th>Detail</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['created_at']) ?></td>
        <td><?= e($r['username']) ?></td>
        <td><?= e($r['action']) ?></td>
        <td><?= e($r['zone_name']) ?></td>
        <td><?= e((string) $r['detail']) ?></td>
        <td><?= e($r['ip']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
