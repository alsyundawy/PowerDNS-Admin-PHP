<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="/zones/new" class="panel stack">
  <?= csrf_field() ?>
  <label>Nama zona<input class="form-control" name="name" placeholder="example.com atau 10.in-addr.arpa" required></label>
  <div class="row g-2">
    <div class="col-md-4">
      <label>Jenis
        <select class="form-select" name="kind" id="zone-kind">
          <?php foreach (['Native','Master','Slave','Producer','Consumer'] as $k): ?><option><?= e($k) ?></option><?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="col-md-4">
      <label>SOA-EDIT-API
        <select class="form-select" name="soa_edit_api">
          <?php foreach (['DEFAULT','INCREASE','EPOCH','SOA-EDIT','SOA-EDIT-INCREASE'] as $k): ?><option><?= e($k) ?></option><?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="col-md-4">
      <label>Akun
        <select class="form-select" name="account_id">
          <option value="0">Tanpa akun</option>
          <?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?>
        </select>
      </label>
    </div>
  </div>
  <label>Nameserver, pisahkan koma<input class="form-control" name="nameservers" placeholder="ns1.example.com, ns2.example.com"></label>
  <label>Primary Slave, pisahkan koma<input class="form-control" name="masters" placeholder="192.0.2.53"></label>
  <label>Template
    <select class="form-select" name="template_id">
      <option value="0">Tanpa template</option>
      <?php foreach ($templates as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <p class="muted">Nama dikirim canonical dengan titik akhir, sesuai API PowerDNS. Serial tidak dikirim klien.</p>
  <button class="btn btn-primary" type="submit">Buat zona</button>
</form>
