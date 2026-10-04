<?php

declare(strict_types=1);

/**
 * @var array<int, array<string, mixed>> $keys
 * @var array<string, mixed> $user
 * @var string $plain
 */

?>
<?php if (!empty($plain)) : ?>
  <div class="alert alert-warning" role="alert">
    <strong>Save this API Key now:</strong>
    <code class="user-select-all d-block mt-2 p-2 bg-light border rounded"><?= e($plain) ?></code>
    <small class="d-block mt-1">The full key will not be displayed again after you leave this page.</small>
  </div>
<?php endif; ?>

<form method="post" action="/apikeys" class="panel stack mb-3">
  <?= csrfField() ?>
  <h2 class="h6 mb-2">Create New API Key</h2>
  <div class="row g-2 align-items-end">
    <div class="col-md-6">
      <label class="form-label small" for="key-name">Key Identifier Name</label>
      <input class="form-control" id="key-name" name="name" placeholder="Example: CI/CD Deployer" required>
    </div>
    <div class="col-md-4">
      <label class="form-label small" for="key-role">Authorization Role</label>
      <select class="form-select" id="key-role" name="role">
        <option value="user">User (Permitted zones only)</option>
        <?php if (($user['role'] ?? '') === 'admin') : ?>
          <option value="operator">Operator</option>
          <option value="admin">Admin (Full access)</option>
        <?php endif; ?>
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary w-100" type="submit">Create Key</button>
    </div>
  </div>
  <p class="muted small mb-0">
    Panel keys are stored as SHA-256 hashes. Use in the HTTP header: <code>X-API-Key: &lt;key&gt;</code>
    when requesting <code>/api/v1/...</code> endpoints on this panel.
  </p>
</form>

<div class="panel">
  <h2 class="h6 mb-3">Panel API Keys List</h2>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th scope="col">Name</th>
          <th scope="col">Prefix</th>
          <th scope="col">Role</th>
          <th scope="col">Owner</th>
          <th scope="col">Last Used</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($keys as $k) : ?>
        <tr>
          <td><strong><?= e($k['name']) ?></strong></td>
          <td><code><?= e($k['key_prefix']) ?>...</code></td>
          <td><span class="badge bg-secondary"><?= e($k['role']) ?></span></td>
          <td><?= e($k['username']) ?></td>
          <td><?= e((string) ($k['last_used_at'] ?: 'never')) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$keys) : ?>
        <tr>
          <td colspan="5" class="text-center py-4 muted">No active API keys yet.</td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
