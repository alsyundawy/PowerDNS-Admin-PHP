<?php

declare(strict_types=1);

/**
 * @var array<int, array<string, mixed>> $accounts
 * @var array<string, mixed> $user
 */

?>
<form method="post" action="/accounts" class="panel stack mb-3">
  <?= csrfField() ?>
  <h2 class="h6 mb-2">Add Organization / Group Account</h2>
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label small" for="account-name">Account Name</label>
      <input class="form-control" id="account-name" name="name" placeholder="Organization / client name" required>
    </div>
    <div class="col-md-3">
      <label class="form-label small" for="account-contact">Contact</label>
      <input class="form-control" id="account-contact" name="contact" placeholder="Email / Phone">
    </div>
    <div class="col-md-3">
      <label class="form-label small" for="account-notes">Notes</label>
      <input class="form-control" id="account-notes" name="notes" placeholder="Internal notes">
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary w-100" type="submit">Add Account</button>
    </div>
  </div>
</form>

<div class="panel">
  <h2 class="h6 mb-3">Accounts List</h2>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th scope="col">Account Name</th>
          <th scope="col">Contact</th>
          <th scope="col">Notes</th>
          <th scope="col">Zone Count</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($accounts as $a) : ?>
        <tr>
          <td><strong><?= e($a['name']) ?></strong></td>
          <td><?= e($a['contact'] ?: '–') ?></td>
          <td><?= e((string) ($a['notes'] ?? '–')) ?></td>
          <td><span class="badge bg-secondary"><?= (int) $a['zone_count'] ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$accounts) : ?>
        <tr>
          <td colspan="4" class="text-center py-4 muted">No accounts yet.</td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
