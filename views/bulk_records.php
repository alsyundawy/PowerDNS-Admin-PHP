<?php

/**
 * Cross-Zone Bulk Record Operations View.
 * Enables mass record searching, batch IP migrations, and atomic replacements across all authoritative zones.
 */

declare(strict_types=1);

/**
 * @var array<string, mixed> $user
 * @var string $query
 * @var string $typeFilter
 * @var array<int, array<string, mixed>> $results
 * @var array{zones_modified?: int, records_replaced?: int, errors?: array<int, string>}|null $replaceResult
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h2 class="h5 mb-0"><i class="fa-solid fa-list-check me-2 text-info"></i>Bulk Records Operations</h2>
    <p class="text-secondary small mb-0">Search and replace record values (IP, CNAME, TXT) simultaneously across all authoritative zones.</p>
  </div>
</div>

<?php if (!empty($replaceResult)) : ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <h6 class="alert-heading mb-1"><i class="fa-solid fa-circle-check me-1"></i> Bulk Replacement Complete</h6>
    <div>Successfully updated <strong><?= (int) ($replaceResult['records_replaced'] ?? 0) ?></strong> records across <strong><?= (int) ($replaceResult['zones_modified'] ?? 0) ?></strong> zones. Automated safety snapshots have been saved for rollback.</div>
    <?php if (!empty($replaceResult['errors'])) : ?>
      <div class="mt-2 pt-2 border-top border-success-subtle text-danger small">
        <strong>Error warnings:</strong>
        <ul class="mb-0">
          <?php foreach ($replaceResult['errors'] as $err) : ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<div class="panel mb-4">
  <form method="get" action="/bulk-records" class="row g-2 align-items-end">
    <div class="col-md-7 col-lg-8">
      <label class="form-label small" for="bulk-query">Keyword / Content Value (IP, FQDN, Text)</label>
      <input class="form-control form-control-sm" id="bulk-query" name="q" value="<?= e($query) ?>" placeholder="Example: 192.0.2.1 or old-host.example.com" required>
    </div>
    <div class="col-md-3 col-lg-2">
      <label class="form-label small" for="bulk-type">Type Filter</label>
      <select class="form-select form-select-sm" id="bulk-type" name="type">
        <option value="">All Types</option>
        <?php foreach (['A', 'AAAA', 'CNAME', 'TXT', 'MX', 'PTR', 'NS', 'ALIAS'] as $t) : ?>
          <option value="<?= $t ?>" <?= $typeFilter === $t ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 col-lg-2">
      <button class="btn btn-sm btn-primary w-100" type="submit">
        <i class="fa-solid fa-magnifying-glass me-1"></i> Search Records
      </button>
    </div>
  </form>
</div>

<?php if ($query !== '') : ?>
  <div class="panel mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="h6 mb-0">Search Results: <strong><?= count($results) ?></strong> records found</h3>
      <?php if (!empty($results) && in_array($user['role'] ?? '', ['admin', 'operator'], true)) : ?>
        <button class="btn btn-sm btn-warning" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-replace">
          <i class="fa-solid fa-pen-to-square me-1"></i> Open Bulk Replace Panel
        </button>
      <?php endif; ?>
    </div>

    <!-- Batch Replace Collapse Form -->
    <div class="collapse mb-3" id="collapse-replace">
      <div class="card card-body bg-dark-subtle border-warning-subtle">
        <h6 class="card-title text-warning mb-2"><i class="fa-solid fa-triangle-exclamation me-1"></i> Cross-Zone Bulk Replace Form</h6>
        <form method="post" action="/bulk-records/replace" onsubmit="return confirm('Are you sure you want to replace this record across all affected zones? Safety snapshots will be created automatically.');">
          <?= csrfField() ?>
          <input type="hidden" name="q" value="<?= e($query) ?>">
          <input type="hidden" name="type" value="<?= e($typeFilter) ?>">
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small" for="replace-target">Target Content to Replace</label>
              <input class="form-control form-control-sm font-monospace" id="replace-target" name="target" value="<?= e($query) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small" for="replace-new">New Replacement Content</label>
              <input class="form-control form-control-sm font-monospace" id="replace-new" name="replacement" placeholder="Example: 198.51.100.1 or new-host.example.com" required>
            </div>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <small class="text-secondary">The system will snapshot affected zone versions before applying changes.</small>
            <button class="btn btn-sm btn-danger" type="submit">
              <i class="fa-solid fa-bolt me-1"></i> Apply Bulk Replacement
            </button>
          </div>
        </form>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Authoritative Zone</th>
            <th>Record Name (FQDN)</th>
            <th>Type</th>
            <th>TTL</th>
            <th>Content / RDATA</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($results)) : ?>
            <tr>
              <td colspan="6" class="text-center text-secondary py-4">
                No records matching keyword "<strong><?= e($query) ?></strong>".
              </td>
            </tr>
          <?php else : ?>
            <?php foreach ($results as $r) : ?>
              <tr>
                <td>
                  <a href="/zones/<?= rawurlencode((string) $r['zone']) ?>" class="fw-bold text-decoration-none">
                    <?= e((string) $r['zone']) ?>
                  </a>
                </td>
                <td><code><?= e((string) $r['name']) ?></code></td>
                <td><span class="badge bg-secondary-subtle text-secondary"><?= e((string) $r['type']) ?></span></td>
                <td><small><?= (int) $r['ttl'] ?>s</small></td>
                <td><code class="text-break"><?= e((string) $r['content']) ?></code></td>
                <td class="text-end">
                  <a href="/zones/<?= rawurlencode((string) $r['zone']) ?>" class="btn btn-xs btn-outline-secondary">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Zone
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
