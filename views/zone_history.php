<?php

declare(strict_types=1);

/**
 * @var string $zone
 * @var array<string, mixed> $user
 * @var list<array<string, mixed>> $snapshots
 * @var array<string, mixed>|null $selectedSnapshot
 * @var array<string, mixed> $currentZone
 */

$cleanZone = rawurlencode(rtrim($zone, '.'));
?>
<div class="zone-head mb-3">
  <div>
    <p class="muted mb-1">
      <a href="/zones/<?= e($cleanZone) ?>" class="text-decoration-none">← Return to Zone Editor</a>
    </p>
    <h2 class="m-0">Version History &amp; Rollback: <code><?= e($zone) ?></code></h2>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="/zones/<?= e($cleanZone) ?>">Record Editor</a>
    <a class="btn btn-outline-primary" href="/zones/<?= e($cleanZone) ?>/dnssec">DNSSEC</a>
  </div>
</div>

<?php if ($selectedSnapshot) : ?>
  <div class="panel mb-4" style="border-left: 4px solid var(--accent, #3b82f6);">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h3 class="m-0">Inspect Revision #<?= (int) $selectedSnapshot['id'] ?></h3>
        <p class="muted small m-0">
          Saved on <?= e((string) $selectedSnapshot['created_at']) ?>
          by <strong><?= e((string) ($selectedSnapshot['username'] ?: 'System')) ?></strong>
          &bull; Serial: <?= e((string) ($selectedSnapshot['serial'] ?? '—')) ?>
          &bull; Note: <em><?= e((string) ($selectedSnapshot['comment'] ?: 'No notes')) ?></em>
        </p>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="/zones/<?= e($cleanZone) ?>/history">Close Inspection</a>
        <form
          method="post"
          action="/zones/<?= e($cleanZone) ?>/rollback/<?= (int) $selectedSnapshot['id'] ?>"
          onsubmit="return confirm(
            'WARNING: Restore all zone records to revision #<?= (int) $selectedSnapshot['id'] ?>? ' +
            'Current zone snapshot will be saved automatically before rollback.'
          )"
        >
          <?= csrfField() ?>
          <button class="btn btn-danger btn-sm" type="submit">Rollback to This Version</button>
        </form>
      </div>
    </div>

    <h4>Records in This Revision (<?= count($selectedSnapshot['rrsets'] ?? []) ?> RRsets):</h4>
    <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
      <table class="table table-sm">
        <thead>
          <tr>
            <th>Name</th>
            <th>Type</th>
            <th>TTL</th>
            <th>Content</th>
            <th>Comment</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($selectedSnapshot['rrsets'] ?? []) as $rr) : ?>
                <?php
                $rname = (string) ($rr['name'] ?? '');
              $rtype = (string) ($rr['type'] ?? '');
              $rttl = (int) ($rr['ttl'] ?? 3600);
              $rrecords = is_array($rr['records'] ?? null) ? $rr['records'] : [];
              $rcomments = is_array($rr['comments'] ?? null) ? $rr['comments'] : [];
              ?>
            <tr>
              <td><code><?= e($rname) ?></code></td>
              <td><span class="badge"><?= e($rtype) ?></span></td>
              <td><?= $rttl ?></td>
              <td>
                <?php foreach ($rrecords as $rec) : ?>
                  <div>
                    <code><?= e((string) ($rec['content'] ?? '')) ?></code>
                    <?php if (!empty($rec['disabled'])) : ?>
                      <span class="badge bg-secondary">disabled</span>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </td>
              <td class="muted small">
                <?php foreach ($rcomments as $cm) : ?>
                  <div><?= e((string) ($cm['content'] ?? '')) ?></div>
                <?php endforeach; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<div class="panel">
  <div class="panel-header mb-3">
    <h3 class="m-0">History Snapshots List (<?= count($snapshots) ?> Available)</h3>
    <p class="muted small m-0">
      Whenever zone records are added, edited, or deleted, a version snapshot is created automatically.
    </p>
  </div>

  <?php if (empty($snapshots)) : ?>
    <div class="alert alert-info">
      No history snapshots saved for this zone yet.
      A snapshot will be created automatically on your next record change.
    </div>
  <?php else : ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>#ID</th>
            <th>Created At</th>
            <th>Operator</th>
            <th>SOA Serial</th>
            <th>Note / Action</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($snapshots as $s) : ?>
            <tr class="<?= (!empty($selectedSnapshot)
              && (int) $selectedSnapshot['id'] === (int) $s['id']) ? 'table-active' : '' ?>">
              <td><strong>#<?= (int) $s['id'] ?></strong></td>
              <td><?= e((string) $s['created_at']) ?></td>
              <td>
                <span class="badge bg-light text-dark">
                  <?= e((string) ($s['username'] ?: 'System')) ?>
                </span>
              </td>
              <td><code><?= e((string) ($s['serial'] ?? '—')) ?></code></td>
              <td><?= e((string) ($s['comment'] ?: 'Zone record update')) ?></td>
              <td class="text-end">
                <a
                  class="btn btn-outline-secondary btn-sm"
                  href="/zones/<?= e($cleanZone) ?>/history?diff=<?= (int) $s['id'] ?>"
                >
                  Inspect Details
                </a>
                <form
                  method="post"
                  action="/zones/<?= e($cleanZone) ?>/rollback/<?= (int) $s['id'] ?>"
                  class="d-inline ms-1"
                  onsubmit="return confirm(
                    'WARNING: Are you sure you want to rollback zone <?= e($zone) ?> ' +
                    'to revision #<?= (int) $s['id'] ?>?'
                  )"
                >
                  <?= csrfField() ?>
                  <button class="btn btn-danger btn-sm" type="submit" title="Rollback zone to this version">
                    Rollback
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
