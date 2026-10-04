<?php

declare(strict_types=1);

/**
 * @var array<string, mixed> $user
 * @var array<int, array{id: int, name: string, kind: string}> $reverseZones
 * @var array<int, array{id: int, name: string}> $accounts
 */
?>
<div class="panel mb-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
    <div>
      <h2 class="h5 mb-1">Generator Subnet Reverse DNS &amp; PTR</h2>
      <p class="muted mb-0 small">
        Kalkulasi dan manajemen zona reverse IPv4 (/24) dan IPv6 (/64 RFC 3596 Nibble Format) dengan PowerDNS API.
      </p>
    </div>
    <span class="badge bg-primary px-3 py-2">RFC 1035 &amp; RFC 3596</span>
  </div>
</div>

<!-- Interactive Live Calculator -->
<div class="panel mb-4">
  <h3 class="h6 mb-3">Kalkulator Interaktif Subnet rDNS</h3>
  <div class="row g-3">
    <div class="col-md-3">
      <label class="form-label small" for="calc-family">Family IP</label>
      <select class="form-select" id="calc-family">
        <option value="ipv4" selected>IPv4 (/24 Subnet)</option>
        <option value="ipv6">IPv6 (/64 Prefix)</option>
      </select>
    </div>
    <div class="col-md-5">
      <label class="form-label small" for="calc-input">Alamat IP / Subnet</label>
      <input
        class="form-control font-monospace"
        id="calc-input"
        placeholder="192.0.2.0/24 atau 192.0.2.1"
        value="192.0.2.0/24"
      >
    </div>
    <div class="col-md-4">
      <label class="form-label small" for="calc-output-zone">Nama Zona Reverse (Canonical)</label>
      <div class="input-group">
        <input
          class="form-control font-monospace bg-dark text-success"
          id="calc-output-zone"
          aria-label="Nama Zona Reverse Canonical"
          readonly
        >
        <button class="btn btn-outline-secondary btn-sm" id="btn-copy-zone" type="button" title="Salin">Salin</button>
      </div>
    </div>
  </div>
  <div class="mt-3 p-3 rounded bg-dark border border-secondary small font-monospace" id="calc-telemetry">
    <div class="row">
      <div class="col-md-6">
        <span class="text-secondary">Contoh Host #1 :</span>
        <span class="text-light" id="tel-host1">192.0.2.1</span><br>
        <span class="text-secondary">Nama PTR Relatif:</span>
        <span class="text-info" id="tel-rel1">1</span>
      </div>
      <div class="col-md-6">
        <span class="text-secondary">Full PTR FQDN :</span>
        <span class="text-warning" id="tel-fqdn1">1.2.0.192.in-addr.arpa.</span>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Card 1: Buat Zona Reverse Baru -->
  <div class="col-lg-6">
    <div class="panel h-100">
      <h3 class="h6 mb-3">1. Buat Zona Reverse Baru di PowerDNS</h3>
      <form method="post" action="/tools/rdns/create-zone" class="stack">
        <?= csrfField() ?>
        <div>
          <label class="form-label small" for="create-family">Family IP</label>
          <select class="form-select" id="create-family" name="family">
            <option value="ipv4" selected>IPv4 (/24 Subnet)</option>
            <option value="ipv6">IPv6 (/64 Prefix)</option>
          </select>
        </div>
        <div>
          <label class="form-label small" for="create-subnet">Subnet / Prefix</label>
          <input
            class="form-control font-monospace"
            id="create-subnet"
            name="subnet"
            placeholder="192.0.2.0/24 atau 2001:db8:1234:5678::/64"
            required
          >
          <div class="form-text small muted">
            Zona otomatis dikonversi (misal: <code>2.0.192.in-addr.arpa.</code>).
          </div>
        </div>
        <div class="row g-2">
          <div class="col-sm-6">
            <label class="form-label small" for="create-kind">Jenis Zona</label>
            <select class="form-select" id="create-kind" name="kind">
              <option value="Native" selected>Native</option>
              <option value="Master">Master / Primary</option>
            </select>
          </div>
          <div class="col-sm-6">
            <label class="form-label small" for="create-account">Akun Tenant</label>
            <select class="form-select" id="create-account" name="account_id">
              <option value="0">Tanpa akun</option>
              <?php foreach ($accounts as $a) : ?>
                <option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="form-label small" for="create-ns">Nameservers Awal (Pisahkan Koma)</label>
          <input
            class="form-control"
            id="create-ns"
            name="nameservers"
            placeholder="ns1.example.com, ns2.example.com"
          >
        </div>
        <div class="pt-2">
          <button class="btn btn-primary w-100" type="submit">Buat Zona Reverse</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Card 2: Batch Generator Record PTR -->
  <div class="col-lg-6">
    <div class="panel h-100">
      <h3 class="h6 mb-3">2. Generator Record PTR Massal (Batch Template)</h3>
      <form method="post" action="/tools/rdns/generate-ptr" class="stack">
        <?= csrfField() ?>
        <div>
          <label class="form-label small" for="gen-zone">Pilih Zona Reverse Tujuan</label>
          <select class="form-select font-monospace" id="gen-zone" name="zone" required>
            <option value="">-- Pilih Zona Reverse Terdaftar --</option>
            <?php foreach ($reverseZones as $rz) : ?>
              <option value="<?= e($rz['name']) ?>"><?= e($rz['name']) ?> (<?= e($rz['kind']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row g-2">
          <div class="col-sm-6">
            <label class="form-label small" for="gen-family">Family IP</label>
            <select class="form-select" id="gen-family" name="family">
              <option value="ipv4" selected>IPv4 (/24)</option>
              <option value="ipv6">IPv6 (/64)</option>
            </select>
          </div>
          <div class="col-sm-6">
            <label class="form-label small" for="gen-subnet">Subnet Acuan</label>
            <input
              class="form-control font-monospace"
              id="gen-subnet"
              name="subnet"
              placeholder="192.0.2.0/24"
              required
            >
          </div>
        </div>
        <div class="row g-2">
          <div class="col-sm-6">
            <label class="form-label small" for="gen-domain">Domain Tujuan</label>
            <input
              class="form-control"
              id="gen-domain"
              name="domain"
              placeholder="example.com"
              required
            >
          </div>
          <div class="col-sm-6">
            <label class="form-label small" for="gen-ttl">TTL (Detik)</label>
            <input
              class="form-control"
              type="number"
              id="gen-ttl"
              name="ttl"
              value="3600"
              min="30"
            >
          </div>
        </div>
        <div>
          <label class="form-label small" for="gen-pattern">Pola Naming Template</label>
          <input
            class="form-control font-monospace"
            id="gen-pattern"
            name="pattern"
            value="host-[ID].[DOMAIN]"
            required
          >
          <div class="form-text small muted">
            Gunakan makro: <code>[ID]</code> (nomor urut), <code>[HEX]</code> (heksadesimal),
            <code>[IP_DASH]</code>, <code>[DOMAIN]</code>.
          </div>
        </div>
        <div class="row g-2">
          <div class="col-sm-6">
            <label class="form-label small" for="gen-start">Host Mulai</label>
            <input class="form-control" type="number" id="gen-start" name="start" value="1" min="1">
          </div>
          <div class="col-sm-6">
            <label class="form-label small" for="gen-end">Host Akhir</label>
            <input class="form-control" type="number" id="gen-end" name="end" value="254" min="1">
          </div>
        </div>
        <div class="pt-2">
          <button class="btn btn-outline-primary w-100" type="submit">Bangkis &amp; Terapkan PTR Massal</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Card 3: Auto-Populate dari Zona Forward -->
  <div class="col-12">
    <div class="panel">
      <h3 class="h6 mb-2">3. Auto-Populate PTR dari Record Forward (A / AAAA)</h3>
      <p class="muted small mb-3">
        Memindai seluruh zona forward lokal di PowerDNS untuk mendeteksi record <code>A</code> atau <code>AAAA</code>
        yang berada di dalam subnet target, lalu secara otomatis menyusun dan menyuntikkan record PTR ke zona reverse.
      </p>
      <form method="post" action="/tools/rdns/scan-forward" class="row g-3 align-items-end">
        <?= csrfField() ?>
        <div class="col-md-4">
          <label class="form-label small" for="scan-zone">Zona Reverse Tujuan</label>
          <select class="form-select font-monospace" id="scan-zone" name="zone" required>
            <option value="">-- Pilih Zona Reverse --</option>
            <?php foreach ($reverseZones as $rz) : ?>
              <option value="<?= e($rz['name']) ?>"><?= e($rz['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-5">
          <label class="form-label small" for="scan-subnet">Subnet Yang Dipindai</label>
          <input
            class="form-control font-monospace"
            id="scan-subnet"
            name="subnet"
            placeholder="192.0.2.0/24 atau 2001:db8:1234:5678::/64"
            required
          >
        </div>
        <div class="col-md-3">
          <button class="btn btn-primary w-100" type="submit">Pindai &amp; Sinkronkan PTR</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var familySelect = document.getElementById('calc-family');
  var inputEl = document.getElementById('calc-input');
  var outputZone = document.getElementById('calc-output-zone');
  var telHost1 = document.getElementById('tel-host1');
  var telRel1 = document.getElementById('tel-rel1');
  var telFqdn1 = document.getElementById('tel-fqdn1');
  var btnCopy = document.getElementById('btn-copy-zone');

  function calculateRdns() {
    var family = familySelect.value;
    var raw = inputEl.value.trim();
    if (!raw) return;

    if (family === 'ipv4') {
      var ip = raw.split('/')[0].trim();
      var parts = ip.split('.');
      if (parts.length === 4) {
        var zone = parts[2] + '.' + parts[1] + '.' + parts[0] + '.in-addr.arpa.';
        outputZone.value = zone;
        telHost1.textContent = parts[0] + '.' + parts[1] + '.' + parts[2] + '.1';
        telRel1.textContent = '1';
        telFqdn1.textContent = '1.' + zone;
      }
    } else {
      // IPv6 calculation approximation for preview
      var ip6 = raw.split('/')[0].trim();
      outputZone.value = 'Kalkulasi Nibble RFC 3596 Aktif (.ip6.arpa.)';
      telHost1.textContent = ip6 ? (ip6 + '1') : '::1';
      telRel1.textContent = '1.0.0.0...';
      telFqdn1.textContent = '1.0.0.0...ip6.arpa.';
    }
  }

  familySelect.addEventListener('change', function () {
    if (familySelect.value === 'ipv4') {
      inputEl.value = '192.0.2.0/24';
    } else {
      inputEl.value = '2001:db8:1234:5678::/64';
    }
    calculateRdns();
  });

  inputEl.addEventListener('input', calculateRdns);
  btnCopy.addEventListener('click', function () {
    if (outputZone.value) {
      navigator.clipboard.writeText(outputZone.value);
      btnCopy.textContent = 'Tersalin!';
      setTimeout(function () { btnCopy.textContent = 'Salin'; }, 2000);
    }
  });

  calculateRdns();
});
</script>
