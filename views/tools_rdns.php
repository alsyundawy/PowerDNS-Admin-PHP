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
      <h2 class="h5 mb-1">Reverse DNS Subnet &amp; PTR Generator</h2>
      <p class="muted mb-0 small">
        IPv4 (/24) and IPv6 (/64 RFC 3596 Nibble Format) reverse zone calculation and management with PowerDNS API.
      </p>
    </div>
    <span class="badge bg-primary px-3 py-2">RFC 1035 &amp; RFC 3596</span>
  </div>
</div>

<!-- Interactive Live Calculator -->
<div class="panel mb-4">
  <h3 class="h6 mb-3">Interactive rDNS Subnet Calculator</h3>
  <div class="row g-3">
    <div class="col-md-3">
      <label class="form-label small" for="calc-family">IP Family</label>
      <select class="form-select" id="calc-family">
        <option value="ipv4" selected>IPv4 (/24 Subnet)</option>
        <option value="ipv6">IPv6 (/64 Prefix)</option>
      </select>
    </div>
    <div class="col-md-5">
      <label class="form-label small" for="calc-input">IP Address / Subnet</label>
      <input
        class="form-control font-monospace"
        id="calc-input"
        placeholder="192.0.2.0/24 or 192.0.2.1"
        value="192.0.2.0/24"
      >
    </div>
    <div class="col-md-4">
      <label class="form-label small" for="calc-output-zone">Reverse Zone Name (Canonical)</label>
      <div class="input-group">
        <input
          class="form-control font-monospace bg-dark text-success"
          id="calc-output-zone"
          aria-label="Canonical Reverse Zone Name"
          readonly
        >
        <button class="btn btn-outline-secondary btn-sm" id="btn-copy-zone" type="button" title="Copy">Copy</button>
      </div>
    </div>
  </div>
  <div class="mt-3 p-3 rounded bg-dark border border-secondary small font-monospace" id="calc-telemetry">
    <div class="row">
      <div class="col-md-6">
        <span class="text-secondary">Example Host #1:</span>
        <span class="text-light" id="tel-host1">192.0.2.1</span><br>
        <span class="text-secondary">Relative PTR Name:</span>
        <span class="text-info" id="tel-rel1">1</span>
      </div>
      <div class="col-md-6">
        <span class="text-secondary">Full PTR FQDN:</span>
        <span class="text-warning" id="tel-fqdn1">1.2.0.192.in-addr.arpa.</span>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Card 1: Create New Reverse Zone -->
  <div class="col-lg-6">
    <div class="panel h-100">
      <h3 class="h6 mb-3">1. Create New Reverse Zone in PowerDNS</h3>
      <form method="post" action="/tools/rdns/create-zone" class="stack">
        <?= csrfField() ?>
        <div>
          <label class="form-label small" for="create-family">IP Family</label>
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
            placeholder="192.0.2.0/24 or 2001:db8:1234:5678::/64"
            required
          >
          <div class="form-text small muted">
            Zone is automatically converted (e.g. <code>2.0.192.in-addr.arpa.</code>).
          </div>
        </div>
        <div class="row g-2">
          <div class="col-sm-6">
            <label class="form-label small" for="create-kind">Zone Type</label>
            <select class="form-select" id="create-kind" name="kind">
              <option value="Native" selected>Native</option>
              <option value="Master">Master / Primary</option>
            </select>
          </div>
          <div class="col-sm-6">
            <label class="form-label small" for="create-account">Tenant Account</label>
            <select class="form-select" id="create-account" name="account_id">
              <option value="0">No account</option>
              <?php foreach ($accounts as $a) : ?>
                <option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="form-label small" for="create-ns">Initial Nameservers (Comma separated)</label>
          <input
            class="form-control"
            id="create-ns"
            name="nameservers"
            placeholder="ns1.example.com, ns2.example.com"
          >
        </div>
        <div class="pt-2">
          <button class="btn btn-primary w-100" type="submit">Create Reverse Zone</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Card 2: Batch PTR Record Generator -->
  <div class="col-lg-6">
    <div class="panel h-100">
      <h3 class="h6 mb-3">2. Bulk PTR Record Generator (Batch Template)</h3>
      <form method="post" action="/tools/rdns/generate-ptr" class="stack">
        <?= csrfField() ?>
        <div>
          <label class="form-label small" for="gen-zone">Select Target Reverse Zone</label>
          <select class="form-select font-monospace" id="gen-zone" name="zone" required>
            <option value="">-- Select Registered Reverse Zone --</option>
            <?php foreach ($reverseZones as $rz) : ?>
              <option value="<?= e($rz['name']) ?>"><?= e($rz['name']) ?> (<?= e($rz['kind']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row g-2">
          <div class="col-sm-6">
            <label class="form-label small" for="gen-family">IP Family</label>
            <select class="form-select" id="gen-family" name="family">
              <option value="ipv4" selected>IPv4 (/24)</option>
              <option value="ipv6">IPv6 (/64)</option>
            </select>
          </div>
          <div class="col-sm-6">
            <label class="form-label small" for="gen-subnet">Reference Subnet</label>
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
            <label class="form-label small" for="gen-domain">Target Domain</label>
            <input
              class="form-control"
              id="gen-domain"
              name="domain"
              placeholder="example.com"
              required
            >
          </div>
          <div class="col-sm-6">
            <label class="form-label small" for="gen-ttl">TTL (Seconds)</label>
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
          <label class="form-label small" for="gen-pattern">Naming Pattern Template</label>
          <input
            class="form-control font-monospace"
            id="gen-pattern"
            name="pattern"
            value="host-[ID].[DOMAIN]"
            required
          >
          <div class="form-text small muted">
            Use macros: <code>[ID]</code> (sequential number), <code>[HEX]</code>, <code>[HEX16]</code>,
            <code>[IP]</code>, <code>[IP_DASH]</code>, <code>[OCTET4]</code>, <code>[DOMAIN]</code>.
          </div>
        </div>
        <div class="row g-2">
          <div class="col-sm-6">
            <label class="form-label small" for="gen-start">Start Host</label>
            <input class="form-control" type="number" id="gen-start" name="start" value="1" min="1">
          </div>
          <div class="col-sm-6">
            <label class="form-label small" for="gen-end">End Host</label>
            <input class="form-control" type="number" id="gen-end" name="end" value="254" min="1">
          </div>
        </div>
        <div class="pt-2">
          <button class="btn btn-outline-primary w-100" type="submit">Generate &amp; Apply Bulk PTR</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Card 3: Auto-Populate from Forward Zones -->
  <div class="col-12">
    <div class="panel">
      <h3 class="h6 mb-2">3. Auto-Populate PTR from Forward Records (A / AAAA)</h3>
      <p class="muted small mb-3">
        Scans all local forward zones in PowerDNS to detect <code>A</code> or <code>AAAA</code> records
        matching the target subnet, then automatically composes and injects PTR records into the reverse zone.
      </p>
      <form method="post" action="/tools/rdns/scan-forward" class="row g-3 align-items-end">
        <?= csrfField() ?>
        <div class="col-md-4">
          <label class="form-label small" for="scan-zone">Target Reverse Zone</label>
          <select class="form-select font-monospace" id="scan-zone" name="zone" required>
            <option value="">-- Select Reverse Zone --</option>
            <?php foreach ($reverseZones as $rz) : ?>
              <option value="<?= e($rz['name']) ?>"><?= e($rz['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-5">
          <label class="form-label small" for="scan-subnet">Scanned Subnet</label>
          <input
            class="form-control font-monospace"
            id="scan-subnet"
            name="subnet"
            placeholder="192.0.2.0/24 or 2001:db8:1234:5678::/64"
            required
          >
        </div>
        <div class="col-md-3">
          <button class="btn btn-primary w-100" type="submit">Scan &amp; Synchronize PTR</button>
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
      outputZone.value = 'RFC 3596 Nibble Calculation Active (.ip6.arpa.)';
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
      btnCopy.textContent = 'Copied!';
      setTimeout(function () { btnCopy.textContent = 'Copy'; }, 2000);
    }
  });

  calculateRdns();
});
</script>
