<?php

declare(strict_types=1);

/**
 * @var string $url
 * @var string $server
 * @var bool $verify
 */
?>
<form method="post" action="/settings" class="panel stack">
  <?= csrfField() ?>
  <h2 class="h6 mb-2">Konfigurasi Koneksi PowerDNS Authoritative API</h2>

  <div>
    <label class="form-label" for="setting-url">URL API PowerDNS</label>
    <input
      class="form-control"
      id="setting-url"
      name="pdns_api_url"
      value="<?= e($url) ?>"
      placeholder="http://127.0.0.1:8081"
      required
    >
    <div class="form-text">
      Endpoint webserver PowerDNS yang dikonfigurasi di <code>pdns.conf</code> (webserver=yes).
    </div>
  </div>

  <div>
    <label class="form-label" for="setting-server">Server ID</label>
    <input
      class="form-control"
      id="setting-server"
      name="pdns_server_id"
      value="<?= e($server) ?>"
      placeholder="localhost"
    >
    <div class="form-text">
      Default server id PowerDNS Authoritative adalah <code>localhost</code>.
    </div>
  </div>

  <div>
    <label class="form-label" for="setting-key">API Key Baru</label>
    <input
      class="form-control"
      type="password"
      id="setting-key"
      name="pdns_api_key"
      placeholder="Kosongkan jika tidak ingin mengubah kunci saat ini"
      autocomplete="off"
    >
    <div class="form-text">
      Kunci rahasia dienkripsi secara simetris dengan AES-256-GCM menggunakan appKey internal.
    </div>
  </div>

  <div class="form-check">
    <input
      class="form-check-input"
      type="checkbox"
      id="setting-tls"
      name="pdns_verify_tls"
      value="1"
      <?= $verify ? 'checked' : '' ?>
    >
    <label class="form-check-label" for="setting-tls">
      Verifikasi sertifikat TLS/SSL (Wajib aktif untuk endpoint HTTPS produksi)
    </label>
  </div>

  <div class="pt-2">
    <button class="btn btn-primary" type="submit">Simpan Pengaturan</button>
  </div>
</form>
