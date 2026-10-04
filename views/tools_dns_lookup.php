<?php

declare(strict_types=1);

/**
 * @var string $title
 * @var array<string, mixed> $user
 * @var string $domain
 * @var string $type
 * @var array<int, array<string, mixed>> $records
 * @var string|null $error
 */

$types = ['ANY', 'A', 'AAAA', 'NS', 'MX', 'TXT', 'SOA', 'CNAME', 'PTR', 'SRV', 'CAA'];
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h2 class="h4 mb-1 text-light">
      <i class="fa-solid fa-satellite-dish text-info me-2"></i>DNS Record Lookup
    </h2>
    <p class="text-secondary small mb-0">Inspeksi record DNS publik otoritatif menggunakan engine resolver native PHP dengan resolusi otomatis glue IP.</p>
  </div>
</div>

<?php if (!empty($error)) : ?>
  <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
    <i class="fa-solid fa-circle-exclamation flex-shrink-0"></i>
    <div><?= e($error) ?></div>
  </div>
<?php endif; ?>

<div class="panel">
  <form method="get" action="/tools/dns-lookup" class="row g-2 align-items-end mb-2">
    <div class="col-md-7 col-12">
      <label for="dns-domain-input" class="form-label small fw-semibold">Nama Domain / Host FQDN</label>
      <div class="input-group">
        <span class="input-group-text bg-dark border-secondary text-secondary">
          <i class="fa-solid fa-globe"></i>
        </span>
        <input type="text" class="form-control" id="dns-domain-input" name="domain"
               value="<?= e($domain ?? '') ?>"
               placeholder="Contoh: powerdns.com atau cloudflare.com" required autofocus>
      </div>
    </div>
    <div class="col-md-2 col-6">
      <label for="dns-type-select" class="form-label small fw-semibold">Tipe Record</label>
      <select class="form-select" id="dns-type-select" name="type">
        <?php foreach ($types as $t) : ?>
          <option value="<?= $t ?>" <?= ($type ?? 'ANY') === $t ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3 col-6">
      <button type="submit" class="btn btn-primary w-100">
        <i class="fa-solid fa-magnifying-glass me-1"></i>Lookup DNS
      </button>
    </div>
  </form>

  <?php if (!empty($domain)) : ?>
    <div class="d-flex flex-wrap gap-1 pt-2 align-items-center">
      <span class="text-secondary small me-1">Filter Tipe:</span>
      <?php foreach ($types as $t) : ?>
        <a href="/tools/dns-lookup?domain=<?= urlencode($domain) ?>&type=<?= $t ?>"
           class="badge <?= ($type ?? 'ANY') === $t ? 'bg-primary' : 'bg-secondary-subtle text-light' ?> text-decoration-none">
          <?= $t ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else : ?>
    <div class="d-flex flex-wrap gap-2 pt-1">
      <span class="text-secondary small align-self-center">Contoh cepat:</span>
      <a href="/tools/dns-lookup?domain=powerdns.com&type=ANY" class="badge bg-secondary-subtle text-light text-decoration-none">powerdns.com</a>
      <a href="/tools/dns-lookup?domain=google.com&type=ANY" class="badge bg-secondary-subtle text-light text-decoration-none">google.com</a>
      <a href="/tools/dns-lookup?domain=cloudflare.com&type=NS" class="badge bg-secondary-subtle text-light text-decoration-none">cloudflare.com (NS)</a>
    </div>
  <?php endif; ?>
</div>

<?php if (!empty($domain)) : ?>
  <div class="panel">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div>
        <h3 class="h6 mb-0 text-info fw-bold">
          <i class="fa-solid fa-list-check me-2"></i>Hasil DNS untuk <?= e($domain) ?>
          <span class="badge bg-secondary-subtle text-secondary ms-1"><?= count($records) ?> record</span>
        </h3>
        <small class="text-secondary">Tipe: <?= e($type) ?></small>
      </div>
      <div class="d-flex gap-2">
        <a href="/tools/whois?query=<?= urlencode($domain) ?>" class="btn btn-sm btn-outline-info">
          <i class="fa-solid fa-id-card me-1"></i>WHOIS Domain &raquo;
        </a>
      </div>
    </div>

    <?php if (empty($records)) : ?>
      <div class="alert alert-warning mb-0" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-1"></i>Tidak ditemukan record DNS untuk domain dan tipe tersebut.
      </div>
    <?php else : ?>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Host (FQDN)</th>
              <th style="width: 100px;">Tipe</th>
              <th style="width: 90px;">TTL</th>
              <th>Data / Target / Value</th>
              <th style="width: 80px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($records as $idx => $r) : ?>
              <?php
                $rType = (string) ($r['type'] ?? 'A');
                $typeBadgeClass = match ($rType) {
                    'A' => 'bg-primary',
                    'AAAA' => 'bg-info text-dark',
                    'NS' => 'bg-purple',
                    'MX' => 'bg-warning text-dark',
                    'TXT' => 'bg-success',
                    'SOA' => 'bg-danger',
                    'CNAME' => 'bg-secondary',
                    default => 'bg-dark border border-secondary',
                };
                ?>
              <tr>
                <td><strong><?= e((string) ($r['host'] ?? '')) ?></strong></td>
                <td><span class="badge <?= $typeBadgeClass ?>"><?= e($rType) ?></span></td>
                <td><span class="text-secondary"><?= e((string) ($r['ttl'] ?? 300)) ?>s</span></td>
                <td>
                  <code id="rec-val-<?= $idx ?>"><?= e((string) ($r['value'] ?? '')) ?></code>
                  <?php if (!empty($r['glue_ipv4']) || !empty($r['glue_ipv6'])) : ?>
                    <div class="mt-1 d-flex flex-wrap gap-1">
                      <?php foreach ((array) ($r['glue_ipv4'] ?? []) as $g4) : ?>
                        <a href="/tools/ipcalc?cidr=<?= urlencode((string) $g4) ?>%2F32"
                           class="badge bg-dark border border-secondary text-info text-decoration-none">
                          <i class="fa-solid fa-link me-1"></i>IPv4: <?= e((string) $g4) ?>
                        </a>
                      <?php endforeach; ?>
                      <?php foreach ((array) ($r['glue_ipv6'] ?? []) as $g6) : ?>
                        <a href="/tools/ipcalc?cidr=<?= urlencode((string) $g6) ?>%2F128"
                           class="badge bg-dark border border-secondary text-light text-decoration-none">
                          <i class="fa-solid fa-link me-1"></i>IPv6: <?= e((string) $g6) ?>
                        </a>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <button class="btn btn-sm btn-outline-secondary btn-copy-target"
                          data-target="rec-val-<?= $idx ?>" title="Salin Record">
                    <i class="fa-solid fa-copy"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
