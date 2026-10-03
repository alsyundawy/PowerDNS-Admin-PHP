<p class="muted">Kunci privat tidak pernah ditampilkan. Daftar cryptokey API memang tidak mengirim private key. Mode CSK adalah default modern PowerDNS. Split KSK+ZSK dipilih eksplisit agar tidak mengulang isu upstream yang hanya mengirim keytype ksk lalu mendapat CSK.</p>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if (in_array($user['role'], ['admin','operator'], true)): ?>
<form method="post" action="/zones/<?= e(rawurlencode(rtrim($zone, '.'))) ?>/dnssec" class="panel stack mb-3">
  <?= csrf_field() ?>
  <label>Mode kunci
    <select class="form-select" name="mode">
      <option value="csk">CSK ECDSA P-256</option>
      <option value="split">KSK + ZSK ECDSA P-256</option>
    </select>
  </label>
  <button class="btn btn-primary" type="submit">Aktifkan / tambah kunci</button>
</form>
<?php endif; ?>
<div class="panel">
  <table class="table">
    <thead><tr><th>ID</th><th>Jenis</th><th>Algoritma</th><th>Bit</th><th>Aktif</th><th>Terbit</th><th>DS</th></tr></thead>
    <tbody>
    <?php foreach ($keys as $key): ?>
      <tr>
        <td><?= (int) ($key['id'] ?? 0) ?></td>
        <td><?= e((string) ($key['keytype'] ?? '')) ?></td>
        <td><?= e((string) ($key['algorithm'] ?? '')) ?></td>
        <td><?= (int) ($key['bits'] ?? 0) ?></td>
        <td><?= !empty($key['active']) ? 'ya' : 'tidak' ?></td>
        <td><?= !empty($key['published']) ? 'ya' : 'tidak' ?></td>
        <td><code><?= e((string) ($key['ds'][0] ?? '')) ?></code></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$keys): ?><tr><td colspan="7" class="muted">Belum ada kunci.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
