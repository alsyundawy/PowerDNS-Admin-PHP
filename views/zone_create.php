<?php

declare(strict_types=1);

/**
 * @var string|null $error
 * @var list<array{id: int, name: string}> $accounts
 * @var list<array{id: int, name: string}> $templates
 */
?>
<?php if (!empty($error)) : ?>
  <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="/zones/new" enctype="multipart/form-data" class="panel stack">
  <?= csrfField() ?>
  <h2 class="h6 mb-0">Parameter Zona Baru</h2>

  <div>
    <label class="form-label" for="zone-name">Nama Zona</label>
    <input
      class="form-control"
      id="zone-name"
      name="name"
      placeholder="example.com atau 10.in-addr.arpa"
      required
      aria-describedby="zone-name-help"
    >
    <div id="zone-name-help" class="form-text">
      Masukkan domain FQDN atau zona reverse ARPA. Sistem otomatis menyesuaikan titik akhir (canonical).
    </div>
  </div>

  <div class="row g-3">
    <div class="col-md-4">
      <label class="form-label" for="zone-kind">Jenis Zona</label>
      <select class="form-select" id="zone-kind" name="kind">
        <?php foreach (['Native', 'Master', 'Slave', 'Producer', 'Consumer'] as $k) : ?>
          <option value="<?= e($k) ?>"><?= e($k) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label" for="zone-soa-edit">SOA-EDIT-API</label>
      <select class="form-select" id="zone-soa-edit" name="soa_edit_api">
        <?php foreach (['DEFAULT', 'INCREASE', 'EPOCH', 'SOA-EDIT', 'SOA-EDIT-INCREASE'] as $k) : ?>
          <option value="<?= e($k) ?>"><?= e($k) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label" for="zone-account">Akun Pemilik</label>
      <select class="form-select" id="zone-account" name="account_id">
        <option value="0">Tanpa akun</option>
        <?php foreach ($accounts as $a) : ?>
          <option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div>
    <label class="form-label" for="zone-ns">Nameserver Otoritatif (Pisahkan dengan koma)</label>
    <input class="form-control" id="zone-ns" name="nameservers" placeholder="ns1.example.com, ns2.example.com">
  </div>

  <div>
    <label class="form-label" for="zone-masters">IP Primary / Master (Wajib untuk zona Slave, pisahkan koma)</label>
    <input class="form-control" id="zone-masters" name="masters" placeholder="192.0.2.53, 198.51.100.53">
  </div>

  <div>
    <label class="form-label" for="zone-template">Terapkan Template Awal</label>
    <select class="form-select" id="zone-template" name="template_id">
      <option value="0">Tanpa template (zona kosong)</option>
      <?php foreach ($templates as $t) : ?>
        <option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="panel border p-3 bg-light">
    <h3 class="h6 mb-2">
      Impor File Zona BIND (RFC 1035 / AXFR) <span class="badge bg-secondary">Opsional</span>
    </h3>
    <p class="muted small mb-3">
      Unggah file <code>.zone</code> / <code>.txt</code> atau tempel teks zona BIND standar.
      Sistem akan otomatis mem-parsing <code>$ORIGIN</code>, <code>$TTL</code>, SOA,
      dan semua Resource Record (RR).
    </p>
    <div class="mb-3">
      <label class="form-label" for="zone-bind-file">Unggah Berkas Zona (.zone, .txt, .db)</label>
      <input
        type="file"
        class="form-control"
        id="zone-bind-file"
        name="bind_file"
        accept=".zone,.txt,.db,text/plain"
      >
    </div>
    <div>
      <label class="form-label" for="zone-bind-content">Atau Tempel Isi File Zona BIND di sini</label>
      <textarea
        class="form-control font-monospace"
        id="zone-bind-content"
        name="bind_content"
        rows="5"
        placeholder="; Tempel isi konfigurasi zona BIND di sini..."
      ></textarea>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2">
    <p class="muted small mb-0">Record akan langsung dibuat di Authoritative Server melalui HTTP API v1.</p>
    <button class="btn btn-primary" type="submit">Buat Zona</button>
  </div>
</form>
