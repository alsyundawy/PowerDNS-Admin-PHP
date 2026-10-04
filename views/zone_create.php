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
  <h2 class="h6 mb-0">New Zone Parameters</h2>

  <div>
    <label class="form-label" for="zone-name">Zone Name</label>
    <input
      class="form-control"
      id="zone-name"
      name="name"
      placeholder="example.com or 10.in-addr.arpa"
      required
      aria-describedby="zone-name-help"
    >
    <div id="zone-name-help" class="form-text">
      Enter an FQDN domain or ARPA reverse zone. Canonical trailing dots are handled automatically.
    </div>
  </div>

  <div class="row g-3">
    <div class="col-md-4">
      <label class="form-label" for="zone-kind">Zone Type</label>
      <select class="form-select" id="zone-kind" name="kind">
        <?php foreach (['Native', 'Master', 'Slave', 'Primary', 'Secondary', 'Producer', 'Consumer'] as $k) : ?>
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
      <label class="form-label" for="zone-account">Account Owner</label>
      <select class="form-select" id="zone-account" name="account_id">
        <option value="0">No account</option>
        <?php foreach ($accounts as $a) : ?>
          <option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div>
    <label class="form-label" for="zone-ns">Authoritative Nameservers (Comma separated)</label>
    <input
      class="form-control"
      id="zone-ns"
      name="nameservers"
      value="<?= e((string) setting('dns_default_ns', '')) ?>"
      placeholder="ns1.example.com, ns2.example.com"
    >
  </div>

  <div>
    <label class="form-label" for="zone-masters">Primary / Master IPs (Required for Slave zones, comma separated)</label>
    <input class="form-control" id="zone-masters" name="masters" placeholder="192.0.2.53, 198.51.100.53">
  </div>

  <div>
    <label class="form-label" for="zone-template">Apply Initial Template</label>
    <select class="form-select" id="zone-template" name="template_id">
      <option value="0">No template (empty zone)</option>
      <?php foreach ($templates as $t) : ?>
        <option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="panel border p-3 bg-light">
    <h3 class="h6 mb-2">
      Import BIND Zone File (RFC 1035 / AXFR) <span class="badge bg-secondary">Optional</span>
    </h3>
    <p class="muted small mb-3">
      Upload a <code>.zone</code> / <code>.txt</code> file or paste standard BIND zone text.
      The system automatically parses <code>$ORIGIN</code>, <code>$TTL</code>, SOA,
      and all Resource Records (RRs).
    </p>
    <div class="mb-3">
      <label class="form-label" for="zone-bind-file">Upload Zone File (.zone, .txt, .db)</label>
      <input
        type="file"
        class="form-control"
        id="zone-bind-file"
        name="bind_file"
        accept=".zone,.txt,.db,text/plain"
      >
    </div>
    <div>
      <label class="form-label" for="zone-bind-content">Or Paste BIND Zone File Content Here</label>
      <textarea
        class="form-control font-monospace"
        id="zone-bind-content"
        name="bind_content"
        rows="5"
        placeholder="; Paste BIND zone configuration here..."
      ></textarea>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2">
    <p class="muted small mb-0">Records will be created immediately on the Authoritative Server via HTTP API v1.</p>
    <button class="btn btn-primary" type="submit">Create Zone</button>
  </div>
</form>
