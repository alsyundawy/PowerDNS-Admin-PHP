<div class="zone-head">
  <div>
    <p class="muted mb-1"><?= e($data['kind'] ?? '') ?> · serial <?= e((string) ($data['serial'] ?? '')) ?> · <?= e($local['account_name'] ?: 'tanpa akun') ?></p>
    <code><?= e($soa) ?></code>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-primary" href="/zones/<?= e(rawurlencode(rtrim($zone, '.'))) ?>/dnssec">DNSSEC</a>
    <?php if ($canEdit): ?>
      <form method="post" action="/zones/<?= e(rawurlencode(rtrim($zone, '.'))) ?>/notify"><?= csrf_field() ?><button class="btn btn-outline-secondary" type="submit">NOTIFY</button></form>
      <form method="post" action="/zones/<?= e(rawurlencode(rtrim($zone, '.'))) ?>/axfr"><?= csrf_field() ?><button class="btn btn-outline-secondary" type="submit">AXFR</button></form>
    <?php endif; ?>
    <?php if ($user['role'] === 'admin'): ?>
      <form method="post" action="/zones/<?= e(rawurlencode(rtrim($zone, '.'))) ?>/delete" onsubmit="return confirm('Hapus zona ini dari PowerDNS?')"><?= csrf_field() ?><button class="btn btn-outline-danger" type="submit">Hapus</button></form>
    <?php endif; ?>
  </div>
</div>
<?php if ($canEdit): ?>
<form method="post" action="/zones/<?= e(rawurlencode(rtrim($zone, '.'))) ?>/save" id="record-form">
  <?= csrf_field() ?>
  <div class="panel mt-3">
    <div class="toolbar">
      <input class="form-control" id="record-filter" placeholder="Saring nama atau isi">
      <button class="btn btn-outline-primary" type="button" id="add-row">Tambah baris</button>
      <button class="btn btn-primary" type="submit">Terapkan ke PowerDNS</button>
    </div>
    <table class="table" id="record-table">
      <thead><tr><th>Nama</th><th>Tipe</th><th>TTL</th><th>Isi</th><th>Off</th><th>Catatan</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $i => $row): ?>
        <tr>
          <td><input class="form-control" name="r_name[]" value="<?= e($row['name']) ?>"></td>
          <td><select class="form-select" name="r_type[]"><?php foreach ($types as $t): ?><option <?= $row['type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></td>
          <td><input class="form-control" name="r_ttl[]" value="<?= (int) $row['ttl'] ?>"></td>
          <td><input class="form-control" name="r_content[]" value="<?= e($row['content']) ?>"></td>
          <td><input type="hidden" name="r_disabled[<?= (int) $i ?>]" value="0"><input type="checkbox" name="r_disabled[<?= (int) $i ?>]" value="1" <?= $row['disabled'] ? 'checked' : '' ?>></td>
          <td><input class="form-control" name="r_comment[]" value="<?= e($row['comment']) ?>"></td>
          <td><button class="btn btn-sm btn-outline-danger rm-row" type="button">×</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</form>
<template id="row-template">
  <tr>
    <td><input class="form-control" name="r_name[]" placeholder="www atau @"></td>
    <td><select class="form-select" name="r_type[]"><?php foreach ($types as $t): if ($t === 'SOA') continue; ?><option><?= e($t) ?></option><?php endforeach; ?></select></td>
    <td><input class="form-control" name="r_ttl[]" value="3600"></td>
    <td><input class="form-control" name="r_content[]"></td>
    <td><input type="checkbox" class="dis-check"></td>
    <td><input class="form-control" name="r_comment[]"></td>
    <td><button class="btn btn-sm btn-outline-danger rm-row" type="button">×</button></td>
  </tr>
</template>
<?php else: ?>
<div class="panel mt-3">
  <table class="table">
    <thead><tr><th>Nama</th><th>Tipe</th><th>TTL</th><th>Isi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
      <tr><td><?= e($row['name']) ?></td><td><?= e($row['type']) ?></td><td><?= (int) $row['ttl'] ?></td><td><?= e($row['content']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php if (($user['role'] ?? '') === 'admin'): ?>
<form method="post" action="/zones/<?= e(rawurlencode(rtrim($zone, '.'))) ?>/grant" class="panel stack mt-3">
  <?= csrf_field() ?>
  <strong>Akses zona</strong>
  <div class="row g-2">
    <div class="col-md-5"><input class="form-control" name="username" placeholder="username panel" required></div>
    <div class="col-md-3"><label class="small"><input type="checkbox" name="can_edit" checked> boleh sunting</label></div>
    <div class="col-md-2"><button class="btn btn-outline-primary" type="submit">Berikan</button></div>
  </div>
</form>
<?php endif; ?>
