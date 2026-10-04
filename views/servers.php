<?php

/**
 * Multi-Server PowerDNS Node Clustering Management View.
 * Displays node telemetry latency, default router, and cluster configuration.
 */

declare(strict_types=1);

/**
 * @var array<string, mixed> $user
 * @var array<int, array<string, mixed>> $servers
 * @var array<string, mixed>|null $activeServer
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h2 class="h5 mb-0"><i class="fa-solid fa-server me-2 text-primary"></i>PowerDNS Cluster Nodes</h2>
    <p class="text-secondary small mb-0">Manage multiple distributed PowerDNS Authoritative daemon servers.</p>
  </div>
  <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modal-add-server">
    <i class="fa-solid fa-plus me-1"></i> Add Server Node
  </button>
</div>

<div class="panel mb-4">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Server Name</th>
          <th>API Endpoint</th>
          <th>Server ID</th>
          <th>Status &amp; Latency</th>
          <th>Role</th>
          <th class="text-end">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($servers)) : ?>
          <tr>
            <td colspan="6" class="text-center text-secondary py-4">
              <i class="fa-solid fa-server fa-2x mb-2 opacity-50 d-block"></i>
              No additional cluster nodes registered. The system is currently using the single default configuration from Settings.
            </td>
          </tr>
        <?php else : ?>
          <?php foreach ($servers as $s) : ?>
            <?php
              $isActiveNode = ((int) ($activeServer['id'] ?? 0) === (int) $s['id']);
              $latency = $s['latency_ms'] !== null ? (int) $s['latency_ms'] : null;
              ?>
            <tr>
              <td>
                <div class="fw-bold d-flex align-items-center gap-2">
                  <span><?= e((string) $s['name']) ?></span>
                  <?php if ($isActiveNode) : ?>
                    <span class="badge bg-success small"><i class="fa-solid fa-check me-1"></i>Active</span>
                  <?php endif; ?>
                </div>
              </td>
              <td><code><?= e((string) $s['api_url']) ?></code></td>
              <td><span class="badge bg-secondary-subtle text-secondary"><?= e((string) $s['server_id']) ?></span></td>
              <td>
                <?php if (!empty($s['is_active'])) : ?>
                  <?php if ($latency !== null) : ?>
                    <?php
                    $latencyBadgeClass = 'bg-danger-subtle text-danger';
                      if ($latency < 100) {
                          $latencyBadgeClass = 'bg-success-subtle text-success';
                      } elseif ($latency < 300) {
                          $latencyBadgeClass = 'bg-warning-subtle text-warning';
                      }
                    ?>
                    <span class="badge <?= $latencyBadgeClass ?>">
                      <i class="fa-solid fa-bolt me-1"></i><?= $latency ?> ms
                    </span>
                  <?php else : ?>
                    <span class="badge bg-secondary-subtle text-secondary">Not checked yet</span>
                  <?php endif; ?>
                <?php else : ?>
                  <span class="badge bg-danger-subtle text-danger">Inactive</span>
                <?php endif; ?>
                <?php if (!empty($s['last_check_at'])) : ?>
                  <small class="text-secondary d-block opacity-75" style="font-size: 11px;"><?= e((string) $s['last_check_at']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($s['is_default'])) : ?>
                  <span class="badge bg-primary-subtle text-primary">Default</span>
                <?php else : ?>
                  <span class="text-secondary small">–</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <?php if (!$isActiveNode && !empty($s['is_active'])) : ?>
                    <a href="/servers/switch?id=<?= (int) $s['id'] ?>" class="btn btn-outline-success" title="Select this server as operational target">
                      <i class="fa-solid fa-arrow-right-to-bracket"></i> Select
                    </a>
                  <?php endif; ?>
                  <a href="/servers/ping?id=<?= (int) $s['id'] ?>" class="btn btn-outline-info" title="Test Latency &amp; Connectivity">
                    <i class="fa-solid fa-satellite-dish"></i> Ping
                  </a>
                  <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modal-edit-server-<?= (int) $s['id'] ?>" title="Edit Settings">
                    <i class="fa-solid fa-pen-to-square"></i>
                  </button>
                  <form method="post" action="/servers/delete" class="d-inline" onsubmit="return confirm('Delete this server node from cluster?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                    <button type="submit" class="btn btn-outline-danger" title="Delete Node">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>

            <!-- Modal Edit Server -->
            <div class="modal fade" id="modal-edit-server-<?= (int) $s['id'] ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog">
                <div class="modal-content">
                  <form method="post" action="/servers/update">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                    <div class="modal-header">
                      <h5 class="modal-title h6">Edit Server Node: <?= e((string) $s['name']) ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body stack">
                      <div>
                        <label class="form-label small" for="edit-name-<?= (int) $s['id'] ?>">Server Name / Label</label>
                        <input class="form-control form-control-sm" id="edit-name-<?= (int) $s['id'] ?>" name="name" value="<?= e((string) $s['name']) ?>" required>
                      </div>
                      <div>
                        <label class="form-label small" for="edit-url-<?= (int) $s['id'] ?>">PowerDNS API URL</label>
                        <input class="form-control form-control-sm" id="edit-url-<?= (int) $s['id'] ?>" name="api_url" value="<?= e((string) $s['api_url']) ?>" required>
                      </div>
                      <div>
                        <label class="form-label small" for="edit-key-<?= (int) $s['id'] ?>">Change API Key (Leave empty to keep current)</label>
                        <input class="form-control form-control-sm" type="password" id="edit-key-<?= (int) $s['id'] ?>" name="api_key" placeholder="••••••••">
                      </div>
                      <div>
                        <label class="form-label small" for="edit-sid-<?= (int) $s['id'] ?>">Server ID (usually: localhost)</label>
                        <input class="form-control form-control-sm" id="edit-sid-<?= (int) $s['id'] ?>" name="server_id" value="<?= e((string) $s['server_id']) ?>" required>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="edit-default-<?= (int) $s['id'] ?>" name="is_default" value="1" <?= !empty($s['is_default']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="edit-default-<?= (int) $s['id'] ?>">Set as Default System Server</label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="edit-active-<?= (int) $s['id'] ?>" name="is_active" value="1" <?= !empty($s['is_active']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="edit-active-<?= (int) $s['id'] ?>">Active Node (Receives Traffic)</label>
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

<!-- Modal Add Server -->
<div class="modal fade" id="modal-add-server" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="/servers/add">
        <?= csrfField() ?>
        <div class="modal-header">
          <h5 class="modal-title h6"><i class="fa-solid fa-plus me-1 text-primary"></i>Add PowerDNS Server Node</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body stack">
          <div>
            <label class="form-label small" for="add-name">Node Name / Label</label>
            <input class="form-control form-control-sm" id="add-name" name="name" placeholder="Example: Primary NS1 (Singapore)" required>
          </div>
          <div>
            <label class="form-label small" for="add-url">PowerDNS API Base URL</label>
            <input class="form-control form-control-sm" id="add-url" name="api_url" placeholder="http://10.0.1.10:8081" required>
            <div class="form-text small">Protocol, IP/host and port of PowerDNS daemon HTTP API.</div>
          </div>
          <div>
            <label class="form-label small" for="add-key">API Key (X-API-Key)</label>
            <input class="form-control form-control-sm" type="password" id="add-key" name="api_key" placeholder="Secret key from pdns.conf" required>
          </div>
          <div>
            <label class="form-label small" for="add-sid">Server ID</label>
            <input class="form-control form-control-sm" id="add-sid" name="server_id" value="localhost" required>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="add-default" name="is_default" value="1">
            <label class="form-check-label small" for="add-default">Set as Default Node</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="add-active" name="is_active" value="1" checked>
            <label class="form-check-label small" for="add-active">Enable Immediately</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-primary">Save &amp; Register Node</button>
        </div>
      </form>
    </div>
  </div>
</div>
