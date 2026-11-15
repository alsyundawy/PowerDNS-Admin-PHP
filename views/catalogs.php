<?php

declare(strict_types=1);

/**
 * @var array<string, mixed> $user
 * @var bool $isSupported
 * @var string $serverVersion
 * @var array<int, array<string, mixed>> $catalogZones
 * @var array<string, array<int, array<string, mixed>>> $groupedMembers
 * @var array<int, array<string, mixed>> $allZones
 */

?>
<div class="panel mb-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
    <div>
      <h2 class="h5 mb-1"><i class="fa-solid fa-book-bookmark text-info me-2"></i>PowerDNS Catalog Zones (RFC 9432)</h2>
      <p class="muted mb-0 small">
        Centralized zone fleet management and synchronization across PowerDNS Authoritative nodes using DNS catalog zone transfers.
      </p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <?php if ($isSupported) : ?>
        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
          <i class="fa-solid fa-circle-check me-1"></i>RFC 9432 Supported (PowerDNS <?= e($serverVersion) ?>)
        </span>
      <?php else : ?>
        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2">
          <i class="fa-solid fa-triangle-exclamation me-1"></i>Requires PowerDNS &gt;= 4.7 (Detected: <?= e($serverVersion ?: 'Unknown') ?>)
        </span>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Overview Stats Cards -->
<div class="stat-grid mb-4">
  <article>
    <span><i class="fa-solid fa-book-bookmark text-info me-1"></i>Catalog Zones</span>
    <strong><?= count($catalogZones) ?></strong>
  </article>
  <article>
    <span><i class="fa-solid fa-list-check text-success me-1"></i>Member Zones Assigned</span>
    <?php
    $totalMembers = 0;
foreach ($groupedMembers as $members) {
    $totalMembers += count($members);
}
?>
    <strong><?= $totalMembers ?></strong>
  </article>
  <article>
    <span><i class="fa-solid fa-server text-purple me-1"></i>Daemon Engine</span>
    <strong class="fs-6"><?= e($serverVersion ?: 'PowerDNS 4.x') ?></strong>
  </article>
  <article>
    <span><i class="fa-solid fa-shield-halved text-warning me-1"></i>Catalog Protocol</span>
    <strong class="fs-6">RFC 9432</strong>
  </article>
</div>

<!-- Action Toolbars -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h3 class="h6 mb-0">Active Catalog Zones &amp; Member Associations</h3>
  <div class="d-flex gap-2">
    <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#createCatalogCollapse">
      <i class="fa-solid fa-plus me-1"></i> New Catalog Zone
    </button>
    <button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse" data-bs-target="#assignCatalogCollapse">
      <i class="fa-solid fa-link me-1"></i> Assign Zone to Catalog
    </button>
  </div>
</div>

<!-- Collapse Form 1: Create Catalog Zone -->
<div class="collapse mb-4" id="createCatalogCollapse">
  <div class="panel border border-primary-subtle">
    <h4 class="h6 mb-3 text-primary"><i class="fa-solid fa-plus-circle me-1"></i>Create New Catalog Zone</h4>
    <form method="post" action="/catalogs/create" class="row g-3">
      <?= csrfField() ?>
      <div class="col-md-5">
        <label class="form-label small" for="cat-name">Catalog Zone Name (FQDN)</label>
        <input class="form-control font-monospace" id="cat-name" name="name" placeholder="catalog.example.com" required>
        <div class="form-text small muted">RFC 9432 compliant catalog zone root domain.</div>
      </div>
      <div class="col-md-3">
        <label class="form-label small" for="cat-kind">Role / Kind</label>
        <select class="form-select" id="cat-kind" name="kind">
          <option value="Producer" selected>Producer (Primary / Master Catalog)</option>
          <option value="Consumer">Consumer (Secondary / Follower Catalog)</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label small" for="cat-masters">Masters / Primaries (for Consumer only)</label>
        <input class="form-control font-monospace" id="cat-masters" name="masters" placeholder="192.0.2.1, 192.0.2.2">
      </div>
      <div class="col-12">
        <label class="form-label small" for="cat-ns">Initial Nameservers (Comma separated)</label>
        <input class="form-control font-monospace" id="cat-ns" name="nameservers" placeholder="ns1.example.com, ns2.example.com">
      </div>
      <div class="col-12 d-flex justify-content-end gap-2">
        <button class="btn btn-sm btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#createCatalogCollapse">Cancel</button>
        <button class="btn btn-sm btn-primary" type="submit">Create Catalog Zone</button>
      </div>
    </form>
  </div>
</div>

<!-- Collapse Form 2: Assign Zone to Catalog -->
<div class="collapse mb-4" id="assignCatalogCollapse">
  <div class="panel border border-info-subtle">
    <h4 class="h6 mb-3 text-info"><i class="fa-solid fa-link me-1"></i>Assign Member Zone to Catalog</h4>
    <form method="post" action="/catalogs/assign" class="row g-3">
      <?= csrfField() ?>
      <div class="col-md-6">
        <label class="form-label small" for="assign-zone">Target Member Zone</label>
        <select class="form-select font-monospace" id="assign-zone" name="zone" required>
          <option value="">-- Select Zone to Assign --</option>
          <?php foreach ($allZones as $z) : ?>
            <option value="<?= e((string) $z['name']) ?>">
              <?= e((string) $z['name']) ?> (<?= e((string) ($z['kind'] ?? 'Native')) ?>)
              <?= !empty($z['catalog']) ? ' [Assigned to: ' . e((string) $z['catalog']) . ']' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label small" for="assign-catalog">Target Catalog Zone</label>
        <select class="form-select font-monospace" id="assign-catalog" name="catalog" required>
          <option value="">-- Select Catalog Zone --</option>
          <?php foreach ($catalogZones as $cz) : ?>
            <option value="<?= e((string) $cz['name']) ?>">
              <?= e((string) $cz['name']) ?> (<?= e((string) ($cz['kind'] ?? 'Producer')) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 d-flex justify-content-end gap-2">
        <button class="btn btn-sm btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#assignCatalogCollapse">Cancel</button>
        <button class="btn btn-sm btn-info text-dark" type="submit">Assign Zone</button>
      </div>
    </form>
  </div>
</div>

<!-- Catalog Zones Display -->
<?php if (empty($catalogZones)) : ?>
  <div class="panel text-center py-5">
    <div class="mb-3 text-secondary">
      <i class="fa-solid fa-book-bookmark fa-3x opacity-50"></i>
    </div>
    <h3 class="h5 mb-2">No Catalog Zones Configured</h3>
    <p class="text-secondary small mb-3" style="max-width: 500px; margin: 0 auto;">
      Catalog zones provide automated member zone provisioning across DNS clusters using RFC 9432.
      Click "New Catalog Zone" above to define your first producer or consumer catalog zone.
    </p>
    <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#createCatalogCollapse">
      <i class="fa-solid fa-plus me-1"></i> Create First Catalog Zone
    </button>
  </div>
<?php else : ?>
  <?php foreach ($catalogZones as $cat) : ?>
    <?php
  $catName = (string) $cat['name'];
      $members = $groupedMembers[$catName] ?? ($groupedMembers[dnsCanonical($catName)] ?? []);
      $kind = strtoupper((string) ($cat['kind'] ?? 'PRODUCER'));
      ?>
    <div class="panel mb-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 pb-3 border-bottom border-secondary-subtle">
        <div class="d-flex align-items-center gap-2">
          <span class="badge <?= $kind === 'PRODUCER' ? 'bg-primary' : 'bg-info text-dark' ?> text-uppercase">
            <?= e($kind) ?>
          </span>
          <h3 class="h6 mb-0 font-monospace text-light">
            <a href="/zones/<?= e($catName) ?>" class="text-decoration-none text-light">
              <?= e($catName) ?>
            </a>
          </h3>
          <span class="badge bg-secondary-subtle text-secondary small">
            Serial: <?= e((string) ($cat['serial'] ?? '–')) ?>
          </span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-dark border border-secondary text-info">
            <?= count($members) ?> member <?= count($members) === 1 ? 'zone' : 'zones' ?>
          </span>
          <a href="/zones/<?= e($catName) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-gear me-1"></i> Zone Settings
          </a>
        </div>
      </div>

      <!-- Member Zones Table -->
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th scope="col" style="width: 40%;">Member Zone</th>
              <th scope="col" style="width: 20%;">Type</th>
              <th scope="col" style="width: 20%;">Serial</th>
              <th scope="col" style="width: 20%;" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($members as $m) : ?>
              <tr>
                <td>
                  <a href="/zones/<?= e((string) $m['name']) ?>" class="font-monospace text-decoration-none fw-semibold">
                    <?= e((string) $m['name']) ?>
                  </a>
                </td>
                <td>
                  <span class="badge bg-secondary"><?= e((string) ($m['kind'] ?? 'Native')) ?></span>
                </td>
                <td class="font-monospace small text-secondary">
                  <?= e((string) ($m['serial'] ?? '–')) ?>
                </td>
                <td class="text-end">
                  <form method="post" action="/catalogs/remove" class="d-inline" onsubmit="return confirm('Remove zone <?= e((string) $m['name']) ?> from catalog <?= e($catName) ?>?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="zone" value="<?= e((string) $m['name']) ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Unassign from catalog">
                      <i class="fa-solid fa-link-slash me-1"></i> Unassign
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($members)) : ?>
              <tr>
                <td colspan="4" class="text-center py-3 text-secondary small">
                  No member zones assigned to this catalog yet. Use "Assign Zone to Catalog" to attach member zones.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
