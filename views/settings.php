<?php

declare(strict_types=1);

/**
 * @var string $url
 * @var string $server
 * @var bool $verify
 * @var string $appName
 * @var string|null $appLogoUrl
 * @var string $appFooterText
 * @var string $defaultTheme
 * @var int $defaultTtl
 * @var string $defaultNs
 * @var string $defaultSoaEmail
 * @var int $defaultSoaRefresh
 * @var int $defaultSoaRetry
 * @var int $defaultSoaExpire
 * @var int $defaultSoaMinimum
 * @var bool $autoPtrDefault
 * @var int $sessionLifetime
 * @var int $maxLoginAttempts
 * @var int $lockoutSeconds
 * @var bool $forceHsts
 * @var int $maxSnapshots
 * @var int $auditRetentionDays
 * @var string $rdnsPattern
 * @var string $publicResolvers
 */
?>
<form method="post" action="/settings" enctype="multipart/form-data" class="stack">
  <?= csrfField() ?>

  <!-- 1. PowerDNS Authoritative API Connection -->
  <div class="panel stack">
    <h2 class="h6 mb-2"><i class="fa-solid fa-server me-2 text-info"></i>1. PowerDNS Authoritative API Connection</h2>

    <div>
      <label class="form-label" for="setting-url">PowerDNS API URL</label>
      <input
        class="form-control"
        id="setting-url"
        name="pdns_api_url"
        value="<?= e($url) ?>"
        placeholder="http://127.0.0.1:8081"
        required
      >
      <div class="form-text">
        PowerDNS webserver endpoint configured in <code>pdns.conf</code> (webserver=yes).
      </div>
    </div>

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label" for="setting-server">Server ID</label>
        <input
          class="form-control"
          id="setting-server"
          name="pdns_server_id"
          value="<?= e($server) ?>"
          placeholder="localhost"
        >
        <div class="form-text">
          Default server ID for PowerDNS Authoritative is <code>localhost</code>.
        </div>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="setting-key">New API Key</label>
        <input
          class="form-control"
          type="password"
          id="setting-key"
          name="pdns_api_key"
          placeholder="Leave empty to keep current key"
          autocomplete="off"
        >
        <div class="form-text">
          Symmetrically encrypted with AES-256-GCM using panel internal appKey.
        </div>
      </div>
    </div>

    <div class="form-check mt-2">
      <input
        class="form-check-input"
        type="checkbox"
        id="setting-tls"
        name="pdns_verify_tls"
        value="1"
        <?= $verify ? 'checked' : '' ?>
      >
      <label class="form-check-label" for="setting-tls">
        Verify TLS/SSL certificates (Mandatory for production HTTPS endpoints)
      </label>
    </div>
  </div>

  <!-- 2. DNS Defaults & Policies -->
  <div class="panel stack">
    <h2 class="h6 mb-2"><i class="fa-solid fa-network-wired me-2 text-primary"></i>2. DNS Default Policies &amp; Parameters</h2>

    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label" for="setting-default-ttl">Default TTL (Seconds)</label>
        <input
          class="form-control"
          type="number"
          min="30"
          max="604800"
          id="setting-default-ttl"
          name="dns_default_ttl"
          value="<?= (int) $defaultTtl ?>"
          required
        >
        <div class="form-text">Fallback TTL for new records or imported zones without explicit TTL (standard: 3600).</div>
      </div>
      <div class="col-md-8">
        <label class="form-label" for="setting-default-ns">Default Authoritative Nameservers</label>
        <input
          class="form-control"
          id="setting-default-ns"
          name="dns_default_ns"
          value="<?= e($defaultNs) ?>"
          placeholder="ns1.example.com, ns2.example.com"
        >
        <div class="form-text">Pre-filled automatic NS list for new zone creation (comma separated).</div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label" for="setting-soa-email">Default SOA Hostmaster / RNAME Email</label>
        <input
          class="form-control"
          id="setting-soa-email"
          name="dns_default_soa_email"
          value="<?= e($defaultSoaEmail) ?>"
          placeholder="hostmaster.example.com"
        >
        <div class="form-text">Zone administrator email in dot-delimited format (e.g. <code>admin.example.com</code>).</div>
      </div>
      <div class="col-md-6">
        <div class="form-check pt-4">
          <input
            class="form-check-input"
            type="checkbox"
            id="setting-auto-ptr"
            name="dns_auto_ptr_default"
            value="1"
            <?= $autoPtrDefault ? 'checked' : '' ?>
          >
          <label class="form-check-label" for="setting-auto-ptr">
            Check <strong>Auto-PTR Sync</strong> by default when opening forward zones
          </label>
        </div>
      </div>
    </div>

    <div class="p-3 rounded bg-dark border border-secondary-subtle">
      <span class="d-block small text-light fw-bold mb-2">SOA Cycle Time Parameters (RFC 1035 Standards):</span>
      <div class="row g-2">
        <div class="col-sm-3">
          <label class="form-label small text-secondary" for="soa-refresh">Refresh (seconds)</label>
          <input class="form-control form-control-sm" type="number" id="soa-refresh" name="dns_default_soa_refresh" value="<?= (int) $defaultSoaRefresh ?>">
        </div>
        <div class="col-sm-3">
          <label class="form-label small text-secondary" for="soa-retry">Retry (seconds)</label>
          <input class="form-control form-control-sm" type="number" id="soa-retry" name="dns_default_soa_retry" value="<?= (int) $defaultSoaRetry ?>">
        </div>
        <div class="col-sm-3">
          <label class="form-label small text-secondary" for="soa-expire">Expire (seconds)</label>
          <input class="form-control form-control-sm" type="number" id="soa-expire" name="dns_default_soa_expire" value="<?= (int) $defaultSoaExpire ?>">
        </div>
        <div class="col-sm-3">
          <label class="form-label small text-secondary" for="soa-minimum">Negative TTL (seconds)</label>
          <input class="form-control form-control-sm" type="number" id="soa-minimum" name="dns_default_soa_minimum" value="<?= (int) $defaultSoaMinimum ?>">
        </div>
      </div>
    </div>
  </div>

  <!-- 3. Branding and Appearance Customization -->
  <div class="panel stack">
    <h2 class="h6 mb-2"><i class="fa-solid fa-palette me-2 text-warning"></i>3. Panel Identity &amp; Branding</h2>

    <div class="row g-3">
      <div class="col-md-8">
        <label class="form-label" for="setting-app-name">Application Name</label>
        <input
          class="form-control"
          id="setting-app-name"
          name="app_name"
          value="<?= e($appName) ?>"
          placeholder="PowerDNS Admin"
          required
        >
        <div class="form-text">
          Panel name displayed on navigation bar, sidebar header, and browser tab title.
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="setting-theme">Default Interface Theme</label>
        <select class="form-select" id="setting-theme" name="app_default_theme">
          <option value="dark" <?= $defaultTheme === 'dark' ? 'selected' : '' ?>>OLED Dark (Visual Subnet Calc)</option>
          <option value="light" <?= $defaultTheme === 'light' ? 'selected' : '' ?>>Daylight Light (WCAG AAA)</option>
        </select>
        <div class="form-text">Initial theme for guests and new user sessions.</div>
      </div>
    </div>

    <div>
      <label class="form-label" for="setting-logo-file">Custom Application Logo</label>
      <?php if (!empty($appLogoUrl)) : ?>
        <div class="d-flex align-items-center gap-3 p-3 mb-2 rounded bg-dark border border-secondary-subtle">
          <div class="logo-preview-box">
            <img src="<?= e($appLogoUrl) ?>" alt="Application Logo" class="custom-logo-preview">
          </div>
          <div>
            <span class="d-block small text-light fw-bold">Active Custom Logo</span>
            <span class="small text-secondary font-monospace"><?= e($appLogoUrl) ?></span>
            <div class="form-check mt-1">
              <input class="form-check-input" type="checkbox" id="remove-logo" name="remove_logo" value="1">
              <label class="form-check-label small text-danger" for="remove-logo">
                Delete custom logo &amp; restore default shield icon
              </label>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="row g-2">
        <div class="col-md-6">
          <label class="form-label small text-secondary" for="setting-logo-file">Upload Logo File (PNG, SVG, WEBP)</label>
          <input
            class="form-control form-control-sm"
            type="file"
            id="setting-logo-file"
            name="app_logo_file"
            accept="image/png,image/jpeg,image/webp,image/svg+xml"
          >
          <div class="form-text small">Maximum 2 MB. Square or proportional horizontal aspect ratio recommended.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label small text-secondary" for="setting-logo-url">Or Enter External Logo URL</label>
          <input
            class="form-control form-control-sm"
            id="setting-logo-url"
            name="app_logo_url"
            value="<?= e($appLogoUrl ?? '') ?>"
            placeholder="https://example.com/logo.svg"
          >
          <div class="form-text small">Supports HTTPS protocols and relative paths.</div>
        </div>
      </div>
    </div>

    <div>
      <label class="form-label" for="setting-footer">Custom Footer Text</label>
      <input
        class="form-control"
        id="setting-footer"
        name="app_footer_text"
        value="<?= e($appFooterText) ?>"
        placeholder="PowerDNS-Admin-PHP &bull; Native High-Performance DNS Panel"
      >
      <div class="form-text">
        Informational or copyright notice displayed at the bottom of the dashboard and sign-in page.
      </div>
    </div>
  </div>

  <!-- 4. Security, Session & Rate Limiting Policies -->
  <div class="panel stack">
    <h2 class="h6 mb-2"><i class="fa-solid fa-shield-halved me-2 text-danger"></i>4. Security, Session &amp; Login Policies</h2>

    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label" for="setting-session">Session Expiration Timeout (Minutes)</label>
        <input
          class="form-control"
          type="number"
          min="5"
          max="10080"
          id="setting-session"
          name="session_lifetime_minutes"
          value="<?= (int) $sessionLifetime ?>"
          required
        >
        <div class="form-text">Idle timeout before user is automatically logged out (default: 120 minutes).</div>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="setting-max-attempts">Failed Login Attempt Limit</label>
        <input
          class="form-control"
          type="number"
          min="1"
          max="50"
          id="setting-max-attempts"
          name="login_max_attempts"
          value="<?= (int) $maxLoginAttempts ?>"
          required
        >
        <div class="form-text">Maximum consecutive incorrect password attempts before lockout (default: 5).</div>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="setting-lockout">Lockout Duration (Seconds)</label>
        <input
          class="form-control"
          type="number"
          min="30"
          max="86400"
          id="setting-lockout"
          name="login_lockout_seconds"
          value="<?= (int) $lockoutSeconds ?>"
          required
        >
        <div class="form-text">Brute-force lockout penalty duration (default: 900 seconds / 15 minutes).</div>
      </div>
    </div>

    <div class="form-check mt-2">
      <input
        class="form-check-input"
        type="checkbox"
        id="setting-hsts"
        name="security_force_hsts"
        value="1"
        <?= $forceHsts ? 'checked' : '' ?>
      >
      <label class="form-check-label" for="setting-hsts">
        Send <code>Strict-Transport-Security (HSTS)</code> header on every HTTPS connection (max-age=31536000)
      </label>
    </div>
  </div>

  <!-- 5. History Retention & Snapshots -->
  <div class="panel stack">
    <h2 class="h6 mb-2"><i class="fa-solid fa-clock-rotate-left me-2 text-success"></i>5. Zone History &amp; Audit Log Retention</h2>

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label" for="setting-max-snapshots">Maximum Snapshots Limit Per Zone</label>
        <input
          class="form-control"
          type="number"
          min="1"
          max="500"
          id="setting-max-snapshots"
          name="history_max_snapshots"
          value="<?= (int) $maxSnapshots ?>"
          required
        >
        <div class="form-text">Number of automatic recovery (rollback) points preserved for each DNS zone.</div>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="setting-audit-retention">Audit Trail Log Retention Period (Days)</label>
        <input
          class="form-control"
          type="number"
          min="1"
          max="3650"
          id="setting-audit-retention"
          name="audit_retention_days"
          value="<?= (int) $auditRetentionDays ?>"
          required
        >
        <div class="form-text">User activity history and API changes retained in the audit log table.</div>
      </div>
    </div>
  </div>

  <!-- 6. Diagnostic Tools & rDNS Settings -->
  <div class="panel stack">
    <h2 class="h6 mb-2"><i class="fa-solid fa-screwdriver-wrench me-2 text-info"></i>6. Network Tools &amp; rDNS Settings</h2>

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label" for="setting-rdns-pattern">Batch PTR Generator Default Naming Pattern</label>
        <input
          class="form-control font-monospace"
          id="setting-rdns-pattern"
          name="rdns_default_naming_pattern"
          value="<?= e($rdnsPattern) ?>"
          required
        >
        <div class="form-text">
          Available macros: <code>[ID]</code>, <code>[HEX]</code>, <code>[HEX16]</code>, <code>[IP]</code>, <code>[IP_DASH]</code>, <code>[OCTET4]</code>, <code>[DOMAIN]</code>.
        </div>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="setting-resolvers">Public Recursive DNS Resolvers List</label>
        <input
          class="form-control font-monospace"
          id="setting-resolvers"
          name="dns_public_resolvers"
          value="<?= e($publicResolvers) ?>"
          required
        >
        <div class="form-text">Comparison resolver IP list for DNS Lookup &amp; Propagation Inspector tools (comma separated).</div>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center pt-2">
    <button class="btn btn-primary px-4 py-2" type="submit">
      <i class="fa-solid fa-floppy-disk me-1"></i> Save All Settings
    </button>
    <a class="btn btn-outline-info" href="/backup">
      <i class="fa-solid fa-database me-1"></i> Open Backup &amp; Restore
    </a>
  </div>
</form>
