<?php

declare(strict_types=1);

/**
 * @var string $zone
 * @var array<int, array<string, mixed>> $keys
 * @var array<string, mixed> $user
 * @var string|null $error
 * @var bool $cdsPublished
 * @var bool $cdnskeyPublished
 */

$cleanZone = rawurlencode(rtrim($zone, '.'));
?>
<div class="zone-head mb-3">
  <div>
    <p class="muted mb-1">
      <a href="/zones/<?= e($cleanZone) ?>" class="text-decoration-none">← Return to Zone Editor</a>
    </p>
    <h2 class="m-0">DNSSEC &amp; Authoritative Keys: <code><?= e($zone) ?></code></h2>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="/zones/<?= e($cleanZone) ?>">Record Editor</a>
    <a class="btn btn-outline-secondary" href="/zones/<?= e($cleanZone) ?>/history">History &amp; Rollback</a>
  </div>
</div>

<?php if (!empty($error)) : ?>
  <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-md-7">
    <div class="panel h-100">
      <h2 class="h6 mb-2">DNSSEC Information</h2>
      <p class="muted mb-2">
        Private keys are securely stored in the PowerDNS backend and never exposed.
        Combining modern algorithms like <strong>Ed25519 (Alg 15)</strong>
        provides the fastest and most efficient cryptographic signatures.
      </p>
      <ul class="muted small mb-0 ps-3">
        <li>
          <strong>CSK (Combined Signing Key)</strong>: A single key pair acts as both KSK and ZSK.
          Modern PowerDNS efficient standard.
        </li>
        <li>
          <strong>Split (KSK + ZSK)</strong>: Separation of Key-Signing Key and Zone-Signing Key roles
          for specific audit or compliance requirements.
        </li>
      </ul>
    </div>
  </div>

  <div class="col-md-5">
    <div
      class="panel h-100 border-start border-4 <?= ($cdsPublished || $cdnskeyPublished)
        ? 'border-success' : 'border-secondary' ?>"
    >
      <div class="d-flex justify-content-between align-items-start mb-2">
        <h2 class="h6 m-0">RFC 7344 Automation (CDS / CDNSKEY)</h2>
        <?php if ($cdsPublished || $cdnskeyPublished) : ?>
          <span class="badge bg-success">Active</span>
        <?php else : ?>
          <span class="badge bg-secondary">Inactive</span>
        <?php endif; ?>
      </div>
      <p class="muted small mb-3">
        Automatically publish CDS/CDNSKEY records so registrars / parent TLDs supporting RFC 7344 &amp; 8078
        can sync DS records without manual intervention.
      </p>

      <?php if (in_array($user['role'] ?? '', ['admin', 'operator'], true)) : ?>
        <form method="post" action="/zones/<?= e($cleanZone) ?>/dnssec/cds">
            <?= csrfField() ?>
          <input
            type="hidden"
            name="enable_cds"
            value="<?= ($cdsPublished || $cdnskeyPublished) ? '0' : '1' ?>"
          >
            <?php if ($cdsPublished || $cdnskeyPublished) : ?>
            <button class="btn btn-outline-danger btn-sm w-100" type="submit">
              Disable CDS / CDNSKEY Publication
            </button>
            <?php else : ?>
            <button class="btn btn-outline-success btn-sm w-100" type="submit">
              Enable Automatic CDS / CDNSKEY Publication
            </button>
            <?php endif; ?>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (in_array($user['role'] ?? '', ['admin', 'operator'], true)) : ?>
  <form method="post" action="/zones/<?= e($cleanZone) ?>/dnssec" class="panel stack mb-3">
    <?= csrfField() ?>
    <h2 class="h6 mb-2">Generate / Add DNSSEC Key</h2>
    <div class="row g-2 align-items-end">
      <div class="col-md-5">
        <label class="form-label" for="key-algo">Cryptographic Algorithm</label>
        <select class="form-select" id="key-algo" name="algorithm">
          <option value="ed25519" selected>Ed25519 (Alg 15, Curve25519) - Modern &amp; Fast (Recommended)</option>
          <option value="ecdsa256">ECDSA P-256 (Alg 13, SHA-256) - Industry Standard</option>
          <option value="ecdsa384">ECDSA P-384 (Alg 14, SHA-384) - High Security</option>
          <option value="rsasha256">RSA/SHA-256 2048-bit (Alg 8) - Traditional Legacy</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="key-mode">Key Mode</label>
        <select class="form-select" id="key-mode" name="mode">
          <option value="csk">CSK (Combined Signing Key)</option>
          <option value="split">Split (Separate KSK + ZSK)</option>
        </select>
      </div>
      <div class="col-md-3">
        <button class="btn btn-primary w-100" type="submit">Generate Key Now</button>
      </div>
    </div>
  </form>
<?php endif; ?>

<div class="panel">
  <h2 class="h6 mb-3">Cryptokeys &amp; DS Records List</h2>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th scope="col">ID</th>
          <th scope="col">Type</th>
          <th scope="col">Algorithm</th>
          <th scope="col">Bits</th>
          <th scope="col">Active</th>
          <th scope="col">Published</th>
          <th scope="col">Delegation Signer (DS)</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($keys as $key) : ?>
        <tr>
          <td><?= (int) ($key['id'] ?? 0) ?></td>
          <td><span class="badge bg-secondary"><?= e((string) ($key['keytype'] ?? '')) ?></span></td>
          <td><code><?= e((string) ($key['algorithm'] ?? '')) ?></code></td>
          <td><?= (int) ($key['bits'] ?? 0) ?></td>
          <td><?= !empty($key['active']) ? '<span class="pill ok">yes</span>' : '<span class="pill">no</span>' ?></td>
          <td>
            <?= !empty($key['published']) ? '<span class="pill ok">yes</span>' : '<span class="pill">no</span>' ?>
          </td>
          <td>
            <?php if (!empty($key['ds'])) : ?>
                <?php foreach ((array) $key['ds'] as $ds) : ?>
                <code><?= e((string) $ds) ?></code><br>
                <?php endforeach; ?>
            <?php else : ?>
              <span class="muted">–</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$keys) : ?>
        <tr>
          <td colspan="7" class="text-center py-4 muted">
            No active cryptokeys found on this zone. Click the button above to generate a new key.
          </td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
