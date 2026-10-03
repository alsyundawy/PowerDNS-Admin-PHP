<form method="post" action="/settings" class="panel stack">
  <?= csrf_field() ?>
  <label>URL API PowerDNS<input class="form-control" name="pdns_api_url" value="<?= e($url) ?>" required></label>
  <label>Server id<input class="form-control" name="pdns_server_id" value="<?= e($server) ?>"></label>
  <label>API key baru<input class="form-control" name="pdns_api_key" placeholder="kosongkan jika tidak diganti" autocomplete="off"></label>
  <label class="check"><input type="checkbox" name="pdns_verify_tls" <?= $verify ? 'checked' : '' ?>> Verifikasi sertifikat TLS</label>
  <p class="muted">Key dienkripsi AES-256-GCM dengan app_key di config.php. Jangan commit config.php.</p>
  <button class="btn btn-primary" type="submit">Simpan</button>
</form>
