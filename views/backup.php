<?php

declare(strict_types=1);

/**
 * @var array<string, mixed> $user
 * @var array<string, int> $tableCounts
 * @var int $zoneCount
 * @var bool $apiOk
 */

$totalRows = array_sum($tableCounts);
?>
<div class="stat-grid mb-3">
  <article>
    <span><i class="fa-solid fa-database text-info"></i> Metadata Tables</span>
    <strong><?= count($tableCounts) ?> <small class="text-secondary fs-6">(<?= $totalRows ?> rows)</small></strong>
  </article>
  <article>
    <span><i class="fa-solid fa-sliders text-success"></i> Settings Options</span>
    <strong><?= (int) ($tableCounts['settings'] ?? 0) ?></strong>
  </article>
  <article>
    <span><i class="fa-solid fa-globe text-purple"></i> PowerDNS Zones</span>
    <strong><?= (int) $zoneCount ?></strong>
  </article>
  <article>
    <span><i class="fa-solid fa-server text-warning"></i> API Status</span>
    <strong>
      <?= $apiOk
        ? '<span class="text-success fs-6"><i class="fa-solid fa-circle-check me-1"></i>Connected</span>'
        : '<span class="text-danger fs-6"><i class="fa-solid fa-circle-xmark me-1"></i>Disconnected</span>' ?>
    </strong>
  </article>
</div>

<div class="row g-3">
  <!-- Database Metadata Backup & Restore -->
  <div class="col-lg-4">
    <div class="panel h-100 d-flex flex-column justify-content-between">
      <div>
        <div class="d-flex align-items-center gap-2 mb-2">
          <i class="fa-solid fa-database text-info fs-5"></i>
          <h2 class="h6 mb-0">Database Metadata (SQL)</h2>
        </div>
        <p class="text-secondary small mb-3">
          Backs up 13 internal panel tables (users, accounts, audit logs, snapshots, and templates)
          in a safely encapsulated SQL transaction file format.
        </p>

        <div class="mb-3">
          <a class="btn btn-outline-info w-100 py-2" href="/backup/download/db">
            <i class="fa-solid fa-download me-1"></i> Download SQL Backup
          </a>
        </div>

        <hr class="border-secondary-subtle my-3">

        <h3 class="small fw-bold text-uppercase text-secondary mb-2">Restore Database</h3>
        <form method="post" action="/backup/restore/db" enctype="multipart/form-data" class="stack"
              onsubmit="return confirm('WARNING: Restoring the database will overwrite current application metadata tables. Proceed?');">
          <?= csrfField() ?>
          <div>
            <label class="form-label small" for="db-file">Select Backup File (.sql)</label>
            <input class="form-control form-control-sm" type="file" id="db-file" name="db_file" accept=".sql" required>
          </div>
          <button class="btn btn-sm btn-danger w-100" type="submit">
            <i class="fa-solid fa-rotate-left me-1"></i> Restore SQL Metadata
          </button>
        </form>
      </div>
      <div class="mt-3 pt-2 border-top border-secondary-subtle">
        <small class="text-secondary d-block">
          <i class="fa-solid fa-shield-halved me-1"></i> Automatically validated against dangerous injected commands.
        </small>
      </div>
    </div>
  </div>

  <!-- Settings & Config Backup & Restore -->
  <div class="col-lg-4">
    <div class="panel h-100 d-flex flex-column justify-content-between">
      <div>
        <div class="d-flex align-items-center gap-2 mb-2">
          <i class="fa-solid fa-sliders text-success fs-5"></i>
          <h2 class="h6 mb-0">Settings &amp; Configuration (JSON)</h2>
        </div>
        <p class="text-secondary small mb-3">
          Backs up complete panel configuration, branding preferences, PowerDNS endpoints, and footer
          in a portable standard JSON format.
        </p>

        <div class="mb-3">
          <a class="btn btn-outline-success w-100 py-2" href="/backup/download/config">
            <i class="fa-solid fa-download me-1"></i> Download JSON Config
          </a>
        </div>

        <hr class="border-secondary-subtle my-3">

        <h3 class="small fw-bold text-uppercase text-secondary mb-2">Restore Settings</h3>
        <form method="post" action="/backup/restore/config" enctype="multipart/form-data" class="stack"
              onsubmit="return confirm('Update panel settings with this JSON configuration file?');">
          <?= csrfField() ?>
          <div>
            <label class="form-label small" for="config-file">Select Configuration File (.json)</label>
            <input
              class="form-control form-control-sm"
              type="file"
              id="config-file"
              name="config_file"
              accept=".json"
              required
            >
          </div>
          <button class="btn btn-sm btn-success w-100" type="submit">
            <i class="fa-solid fa-rotate-left me-1"></i> Restore JSON Settings
          </button>
        </form>
      </div>
      <div class="mt-3 pt-2 border-top border-secondary-subtle">
        <small class="text-secondary d-block">
          <i class="fa-solid fa-circle-info me-1"></i> API secret keys remain securely protected.
        </small>
      </div>
    </div>
  </div>

  <!-- PowerDNS Zones Snapshot Backup & Restore -->
  <div class="col-lg-4">
    <div class="panel h-100 d-flex flex-column justify-content-between">
      <div>
        <div class="d-flex align-items-center gap-2 mb-2">
          <i class="fa-solid fa-globe text-purple fs-5"></i>
          <h2 class="h6 mb-0">PowerDNS Zones (Snapshot JSON)</h2>
        </div>
        <p class="text-secondary small mb-3">
          Exports all authoritative zones along with all RRsets (A, AAAA, MX, TXT, SOA, NS, etc.)
          and BIND format directly via PowerDNS API v1.
        </p>

        <div class="mb-3">
          <a class="btn btn-outline-primary w-100 py-2 <?= !$apiOk ? 'disabled' : '' ?>" href="/backup/download/zones">
            <i class="fa-solid fa-download me-1"></i> Download All Zones Snapshot
          </a>
        </div>

        <hr class="border-secondary-subtle my-3">

        <h3 class="small fw-bold text-uppercase text-secondary mb-2">Restore / Import Zones</h3>
        <form method="post" action="/backup/restore/zones" enctype="multipart/form-data" class="stack"
              onsubmit="return confirm('Import zones collection to PowerDNS Authoritative Server? Existing records will be synchronized.');">
          <?= csrfField() ?>
          <div>
            <label class="form-label small" for="zones-file">Select Zones File (.json)</label>
            <input
              class="form-control form-control-sm"
              type="file"
              id="zones-file"
              name="zones_file"
              accept=".json"
              required
              <?= !$apiOk ? 'disabled' : '' ?>
            >
          </div>
          <button class="btn btn-sm btn-primary w-100" type="submit" <?= !$apiOk ? 'disabled' : '' ?>>
            <i class="fa-solid fa-cloud-arrow-up me-1"></i> Import &amp; Sync Zones
          </button>
        </form>
      </div>
      <div class="mt-3 pt-2 border-top border-secondary-subtle">
        <small class="text-secondary d-block">
          <i class="fa-solid fa-bolt me-1"></i> Requires active connection to PowerDNS Authoritative API.
        </small>
      </div>
    </div>
  </div>
</div>

<div class="panel mt-3">
  <h3 class="h6 mb-2"><i class="fa-solid fa-terminal me-2 text-info"></i>Daily Backup Automation (Linux Cron)</h3>
  <p class="text-secondary small mb-2">
    You can automate regular MySQL database and metadata backups using Linux cron jobs:
  </p>
  <pre class="p-3 rounded bg-dark border border-secondary text-light small mb-0 font-monospace"
  ><code># Daily database backup every day at 02:00
0 2 * * * mysqldump -u pdns -p'secret' pdns_admin | gzip &gt; /var/backups/pdns_admin_$(date +\%Y\%m\%d).sql.gz</code></pre>
</div>
