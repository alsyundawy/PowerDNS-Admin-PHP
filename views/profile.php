<?php

declare(strict_types=1);

/**
 * @var array<string, mixed> $user
 * @var array<string, mixed> $profile
 */

$avatarUrl = !empty($profile['avatar_url']) ? (string) $profile['avatar_url'] : '';
$displayName = !empty($profile['display_name']) ? (string) $profile['display_name'] : (string) $profile['username'];
$initials = strtoupper(substr((string) $profile['username'], 0, 2));
?>
<div class="row g-3">
  <div class="col-lg-4">
    <!-- User Profile Summary Card -->
    <div class="panel text-center mb-3">
      <div class="avatar-wrapper mx-auto mb-3">
        <?php if ($avatarUrl !== '') : ?>
          <img src="<?= e($avatarUrl) ?>" alt="Avatar" class="profile-avatar-img">
        <?php else : ?>
          <div class="profile-avatar-placeholder">
            <span><?= e($initials) ?></span>
          </div>
        <?php endif; ?>
      </div>
      <h2 class="h5 mb-1"><?= e($displayName) ?></h2>
      <p class="text-secondary small mb-2">@<?= e((string) $profile['username']) ?></p>
      <div class="d-flex justify-content-center gap-2 mb-3">
        <span class="badge bg-primary text-uppercase"><?= e((string) $profile['role']) ?></span>
        <?php if (!empty($profile['active'])) : ?>
          <span class="badge bg-success-subtle text-success">Active</span>
        <?php else : ?>
          <span class="badge bg-danger-subtle text-danger">Inactive</span>
        <?php endif; ?>
      </div>

      <div class="text-start border-top border-secondary-subtle pt-3 small text-secondary">
        <div class="d-flex justify-content-between mb-1">
          <span><i class="fa-solid fa-clock-rotate-left me-1"></i> Last Sign In:</span>
          <span class="text-light"><?= e((string) ($profile['last_login_at'] ?? 'Never')) ?></span>
        </div>
        <div class="d-flex justify-content-between">
          <span><i class="fa-solid fa-calendar me-1"></i> Registered Since:</span>
          <span class="text-light"><?= e(substr((string) ($profile['created_at'] ?? '–'), 0, 10)) ?></span>
        </div>
      </div>
    </div>

    <!-- Avatar Upload / Removal Form -->
    <div class="panel">
      <h3 class="h6 mb-3"><i class="fa-solid fa-camera me-2 text-info"></i>Profile Picture</h3>
      <form method="post" action="/profile/avatar" enctype="multipart/form-data" class="stack">
        <?= csrfField() ?>
        <div>
          <label class="form-label small" for="avatar-file">Select New Image (PNG, JPG, WEBP, SVG)</label>
          <input
            class="form-control form-control-sm"
            type="file"
            id="avatar-file"
            name="avatar"
            accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml"
            required
          >
          <div class="form-text small">Maximum 2 MB. Image will be stored securely.</div>
        </div>
        <button class="btn btn-sm btn-primary w-100" type="submit">
          <i class="fa-solid fa-upload me-1"></i> Upload Profile Picture
        </button>
      </form>

      <?php if ($avatarUrl !== '') : ?>
        <form method="post" action="/profile/avatar/delete" class="mt-2"
              onsubmit="return confirm('Delete profile picture and use default initials avatar?');">
          <?= csrfField() ?>
          <button class="btn btn-sm btn-outline-danger w-100" type="submit">
            <i class="fa-solid fa-trash me-1"></i> Delete Profile Picture
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-8">
    <!-- User Information Form -->
    <div class="panel mb-3">
      <h3 class="h6 mb-3"><i class="fa-solid fa-user-pen me-2 text-info"></i>Account Information</h3>
      <form method="post" action="/profile/update" class="stack">
        <?= csrfField() ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small" for="prof-username">Username</label>
            <input class="form-control" id="prof-username" value="<?= e((string) $profile['username']) ?>" disabled>
            <div class="form-text small">Permanent system username cannot be changed.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label small" for="prof-role">System Role</label>
            <input class="form-control" id="prof-role" value="<?= e((string) $profile['role']) ?>" disabled>
            <div class="form-text small">Access privilege level on the administration panel.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label small" for="prof-display">Display Name</label>
            <input
              class="form-control"
              id="prof-display"
              name="display_name"
              value="<?= e((string) $profile['display_name']) ?>"
              placeholder="Full Name"
              required
            >
          </div>
          <div class="col-md-6">
            <label class="form-label small" for="prof-email">Email Address</label>
            <input
              class="form-control"
              type="email"
              id="prof-email"
              name="email"
              value="<?= e((string) $profile['email']) ?>"
              placeholder="name@domain.com"
            >
          </div>
        </div>
        <div class="pt-2">
          <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-floppy-disk me-1"></i> Save Profile Changes
          </button>
        </div>
      </form>
    </div>

    <!-- Password Change Form -->
    <div class="panel">
      <h3 class="h6 mb-3"><i class="fa-solid fa-key me-2 text-warning"></i>Change Password</h3>
      <form method="post" action="/profile/password" class="stack">
        <?= csrfField() ?>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label small" for="pass-curr">Current Password</label>
            <input
              class="form-control"
              type="password"
              id="pass-curr"
              name="current_password"
              autocomplete="current-password"
              required
            >
          </div>
          <div class="col-md-6">
            <label class="form-label small" for="pass-new">New Password</label>
            <input
              class="form-control"
              type="password"
              id="pass-new"
              name="new_password"
              minlength="8"
              autocomplete="new-password"
              placeholder="Minimum 8 characters"
              required
            >
          </div>
          <div class="col-md-6">
            <label class="form-label small" for="pass-conf">Confirm New Password</label>
            <input
              class="form-control"
              type="password"
              id="pass-conf"
              name="confirm_password"
              minlength="8"
              autocomplete="new-password"
              placeholder="Repeat new password"
              required
            >
          </div>
        </div>
        <div class="pt-2">
          <button class="btn btn-warning" type="submit">
            <i class="fa-solid fa-shield-halved me-1"></i> Update Password
          </button>
        </div>
      </form>
    </div>

    <!-- Two-Factor Authentication (2FA TOTP RFC 6238) Section -->
    <div class="panel mt-3">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h6 mb-0"><i class="fa-solid fa-shield-halved me-2 text-success"></i>Two-Factor Authentication (2FA TOTP)</h3>
        <?php if (!empty($profile['totp_enabled'])) : ?>
          <span class="badge bg-success-subtle text-success"><i class="fa-solid fa-check me-1"></i>2FA Active</span>
        <?php else : ?>
          <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
        <?php endif; ?>
      </div>

      <?php if (!empty($profile['totp_enabled'])) : ?>
        <p class="small text-secondary mb-3">Your account is protected with the RFC 6238 TOTP standard (Google Authenticator, Authy, Apple Passwords, 1Password). Each sign in requires a 6-digit verification code.</p>
        <form method="post" action="/profile/2fa/disable" onsubmit="return confirm('Disable 2FA protection for this account?');" class="stack">
          <?= csrfField() ?>
          <div class="row g-2 align-items-end">
            <div class="col-md-8">
              <label class="form-label small" for="disable-2fa-pw">Enter Current Password to Disable</label>
              <input class="form-control form-control-sm" type="password" id="disable-2fa-pw" name="current_password" required>
            </div>
            <div class="col-md-4">
              <button class="btn btn-sm btn-outline-danger w-100" type="submit">
                <i class="fa-solid fa-lock-open me-1"></i> Disable 2FA
              </button>
            </div>
          </div>
        </form>
      <?php elseif (!empty($totpSetup)) : ?>
        <div class="border border-success-subtle rounded p-3 bg-dark-subtle mb-3">
          <h4 class="h6 text-success mb-2"><i class="fa-solid fa-qrcode me-1"></i> Configure New Authenticator</h4>
          <p class="small text-secondary mb-3">Scan the following QR code using your authenticator app of choice or enter the secret key manually below:</p>
          <div class="row g-3 align-items-center">
            <div class="col-sm-5 text-center">
              <div class="bg-white p-2 rounded d-inline-block shadow-sm">
                <?= $totpSetup['qrSvg'] ?>
              </div>
            </div>
            <div class="col-sm-7">
              <label for="secret-copy-input" class="form-label small text-secondary">Secret Key (Manual Entry):</label>
              <div class="input-group input-group-sm mb-3">
                <input type="text" class="form-control font-monospace" value="<?= e((string) $totpSetup['secret']) ?>" id="secret-copy-input" readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('secret-copy-input').value); alert('Secret key copied!');">
                  <i class="fa-solid fa-copy"></i> Copy
                </button>
              </div>
              <div class="alert alert-warning p-2 small mb-0">
                <strong>Save Emergency Backup Codes:</strong>
                <div class="font-monospace small mt-1 d-flex flex-wrap gap-1">
                  <?php foreach ($totpSetup['backup']['plaintext'] as $bCode) : ?>
                    <span class="badge bg-secondary"><?= e($bCode) ?></span>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
          <form method="post" action="/profile/2fa/verify" class="mt-3 pt-3 border-top border-secondary-subtle">
            <?= csrfField() ?>
            <div class="row g-2 align-items-end">
              <div class="col-md-7">
                <label class="form-label small" for="confirm-code">Enter 6-Digit Code from App to Confirm</label>
                <input class="form-control form-control-sm font-monospace text-center fs-6" id="confirm-code" name="code" placeholder="123456" inputmode="numeric" required>
              </div>
              <div class="col-md-5">
                <button class="btn btn-sm btn-success w-100" type="submit">
                  <i class="fa-solid fa-check me-1"></i> Enable &amp; Lock 2FA
                </button>
              </div>
            </div>
          </form>
        </div>
      <?php else : ?>
        <p class="small text-secondary mb-3">Enhance your account security with two-step verification (TOTP). Does not require SMS or an internet connection to generate codes.</p>
        <form method="post" action="/profile/2fa/setup">
          <?= csrfField() ?>
          <button class="btn btn-sm btn-primary" type="submit">
            <i class="fa-solid fa-qrcode me-1"></i> Begin 2FA Activation
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
