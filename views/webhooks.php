<?php

/**
 * Webhooks Management View.
 * Displays webhook event notification endpoints, last delivery status, and signing secret.
 */

declare(strict_types=1);

/**
 * @var array<string, mixed> $user
 * @var array<int, array<string, mixed>> $webhooks
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h2 class="h5 mb-0"><i class="fa-solid fa-bolt me-2 text-warning"></i>CI/CD &amp; Webhooks Integration</h2>
    <p class="text-secondary small mb-0">Send cryptographically signed HMAC-SHA256 HTTP POST notifications when zones or records change.</p>
  </div>
  <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modal-add-webhook">
    <i class="fa-solid fa-plus me-1"></i> Add Webhook
  </button>
</div>

<div class="panel mb-4">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Webhook Name</th>
          <th>Endpoint URL</th>
          <th>Subscribed Events</th>
          <th>Delivery Status</th>
          <th>Last Delivered</th>
          <th class="text-end">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($webhooks)) : ?>
          <tr>
            <td colspan="6" class="text-center text-secondary py-4">
              <i class="fa-solid fa-bolt fa-2x mb-2 opacity-50 d-block"></i>
              No webhooks registered yet. Add an endpoint URL to receive automated DNS updates.
            </td>
          </tr>
        <?php else : ?>
          <?php foreach ($webhooks as $w) : ?>
            <?php
              $events = array_map('trim', explode(',', (string) $w['events']));
              $statusCode = $w['last_status_code'] !== null ? (int) $w['last_status_code'] : null;
              ?>
            <tr>
              <td>
                <div class="fw-bold"><?= e((string) $w['name']) ?></div>
                <?php if (!empty($w['is_active'])) : ?>
                  <span class="badge bg-success-subtle text-success small">Active</span>
                <?php else : ?>
                  <span class="badge bg-secondary-subtle text-secondary small">Inactive</span>
                <?php endif; ?>
              </td>
              <td><code><?= e((string) $w['url']) ?></code></td>
              <td>
                <div class="d-flex flex-wrap gap-1">
                  <?php foreach ($events as $ev) : ?>
                    <span class="badge bg-info-subtle text-info small"><?= e($ev) ?></span>
                  <?php endforeach; ?>
                </div>
              </td>
              <td>
                <?php if ($statusCode !== null) : ?>
                  <span class="badge <?= ($statusCode >= 200 && $statusCode < 300) ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                    HTTP <?= $statusCode ?>
                  </span>
                <?php else : ?>
                  <span class="badge bg-secondary-subtle text-secondary">No events yet</span>
                <?php endif; ?>
                <?php if (!empty($w['last_error'])) : ?>
                  <small class="text-danger d-block text-truncate" style="max-width: 180px;" title="<?= e((string) $w['last_error']) ?>">
                    <?= e((string) $w['last_error']) ?>
                  </small>
                <?php endif; ?>
              </td>
              <td>
                <small class="text-secondary"><?= e((string) ($w['last_triggered_at'] ?? '–')) ?></small>
              </td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <a href="/webhooks/test?id=<?= (int) $w['id'] ?>" class="btn btn-outline-info" title="Test Event Delivery (Ping)">
                    <i class="fa-solid fa-paper-plane"></i> Test
                  </a>
                  <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modal-edit-webhook-<?= (int) $w['id'] ?>" title="Edit Settings">
                    <i class="fa-solid fa-pen-to-square"></i>
                  </button>
                  <form method="post" action="/webhooks/delete" class="d-inline" onsubmit="return confirm('Delete this webhook?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
                    <button type="submit" class="btn btn-outline-danger" title="Delete">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>

            <!-- Modal Edit Webhook -->
            <div class="modal fade" id="modal-edit-webhook-<?= (int) $w['id'] ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog">
                <div class="modal-content">
                  <form method="post" action="/webhooks/update">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
                    <div class="modal-header">
                      <h5 class="modal-title h6">Edit Webhook: <?= e((string) $w['name']) ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body stack">
                      <div>
                        <label class="form-label small" for="edit-hook-name-<?= (int) $w['id'] ?>">Webhook Name</label>
                        <input class="form-control form-control-sm" id="edit-hook-name-<?= (int) $w['id'] ?>" name="name" value="<?= e((string) $w['name']) ?>" required>
                      </div>
                      <div>
                        <label class="form-label small" for="edit-hook-url-<?= (int) $w['id'] ?>">Payload URL (HTTPS recommended)</label>
                        <input class="form-control form-control-sm" id="edit-hook-url-<?= (int) $w['id'] ?>" name="url" value="<?= e((string) $w['url']) ?>" required>
                      </div>
                      <div>
                        <label class="form-label small" for="edit-hook-secret-<?= (int) $w['id'] ?>">Signing Secret (HMAC-SHA256)</label>
                        <input class="form-control form-control-sm" id="edit-hook-secret-<?= (int) $w['id'] ?>" name="secret" value="<?= e((string) $w['secret']) ?>" required>
                      </div>
                      <div>
                        <span class="form-label small d-block mb-1 fw-medium">Select Subscribed Events</span>
                        <div class="form-check form-check-inline">
                          <input class="form-check-input" type="checkbox" id="edit-ev-create-<?= (int) $w['id'] ?>" name="events[]" value="zone.created" <?= in_array('zone.created', $events, true) ? 'checked' : '' ?>>
                          <label class="form-check-label small" for="edit-ev-create-<?= (int) $w['id'] ?>">zone.created</label>
                        </div>
                        <div class="form-check form-check-inline">
                          <input class="form-check-input" type="checkbox" id="edit-ev-delete-<?= (int) $w['id'] ?>" name="events[]" value="zone.deleted" <?= in_array('zone.deleted', $events, true) ? 'checked' : '' ?>>
                          <label class="form-check-label small" for="edit-ev-delete-<?= (int) $w['id'] ?>">zone.deleted</label>
                        </div>
                        <div class="form-check form-check-inline">
                          <input class="form-check-input" type="checkbox" id="edit-ev-update-<?= (int) $w['id'] ?>" name="events[]" value="record.updated" <?= in_array('record.updated', $events, true) ? 'checked' : '' ?>>
                          <label class="form-check-label small" for="edit-ev-update-<?= (int) $w['id'] ?>">record.updated</label>
                        </div>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="edit-hook-active-<?= (int) $w['id'] ?>" name="is_active" value="1" <?= !empty($w['is_active']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="edit-hook-active-<?= (int) $w['id'] ?>">Enable Webhook Delivery</label>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-sm btn-primary">Save Changes</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Add Webhook -->
<div class="modal fade" id="modal-add-webhook" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="/webhooks/add">
        <?= csrfField() ?>
        <div class="modal-header">
          <h5 class="modal-title h6"><i class="fa-solid fa-plus me-1 text-primary"></i>Register New Webhook</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body stack">
          <div>
            <label class="form-label small" for="add-hook-name">Webhook Name / Service</label>
            <input class="form-control form-control-sm" id="add-hook-name" name="name" placeholder="Example: Slack DNS Alerts or CI Sync" required>
          </div>
          <div>
            <label class="form-label small" for="add-hook-url">Payload URL (Target Endpoint)</label>
            <input class="form-control form-control-sm" id="add-hook-url" name="url" placeholder="https://api.example.com/webhooks/dns" required>
            <div class="form-text small">Endpoint that will receive HTTP POST JSON with <code>X-PDNS-Signature</code> header.</div>
          </div>
          <div>
            <label class="form-label small" for="add-hook-secret">Signing Secret (Optional - Generated automatically if empty)</label>
            <input class="form-control form-control-sm" id="add-hook-secret" name="secret" placeholder="Leave empty for random 32-character secret">
          </div>
          <div>
            <span class="form-label small d-block mb-1 fw-medium">Select Subscribed Events</span>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" id="add-ev-create" name="events[]" value="zone.created" checked>
              <label class="form-check-label small" for="add-ev-create">zone.created</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" id="add-ev-delete" name="events[]" value="zone.deleted" checked>
              <label class="form-check-label small" for="add-ev-delete">zone.deleted</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" id="add-ev-update" name="events[]" value="record.updated" checked>
              <label class="form-check-label small" for="add-ev-update">record.updated</label>
            </div>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="add-hook-active" name="is_active" value="1" checked>
            <label class="form-check-label small" for="add-hook-active">Enable Immediately</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-primary">Save Webhook</button>
        </div>
      </form>
    </div>
  </div>
</div>
