<?php

declare(strict_types=1);

/**
 * @var list<string> $types
 * @var list<array{id: int, name: string, description: string, rec_count: int}> $templates
 */
?>
<form method="post" action="/templates" class="panel stack mb-3">
  <?= csrfField() ?>
  <h2 class="h6 mb-2">Create DNS Record Template</h2>

  <div class="row g-2">
    <div class="col-md-4">
      <label class="form-label small" for="tpl-name">Template Name</label>
      <input class="form-control" id="tpl-name" name="name" placeholder="Standard Web Hosting" required>
    </div>
    <div class="col-md-8">
      <label class="form-label small" for="tpl-desc">Description</label>
      <input
        class="form-control"
        id="tpl-desc"
        name="description"
        placeholder="Default A, MX, and CNAME for new domains"
      >
    </div>
  </div>

  <p class="muted small mb-1">
    Use placeholder <code>[ZONE]</code> to automatically replace with the zone name when applied.
  </p>

  <div class="row g-2 align-items-center">
    <div class="col-md-3">
      <label class="form-label small" for="tpl-rname">Record Name</label>
      <input class="form-control" id="tpl-rname" name="r_name[]" placeholder="@ or www">
    </div>
    <div class="col-md-2">
      <label class="form-label small" for="tpl-rtype">Type</label>
      <select class="form-select" id="tpl-rtype" name="r_type[]">
        <?php foreach ($types as $t) : ?>
            <?php if ($t !== 'SOA') : ?>
            <option value="<?= e($t) ?>"><?= e($t) ?></option>
            <?php endif; ?>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small" for="tpl-rttl">TTL</label>
      <input class="form-control" type="number" min="30" id="tpl-rttl" name="r_ttl[]" value="3600">
    </div>
    <div class="col-md-5">
      <label class="form-label small" for="tpl-rcontent">Content</label>
      <input class="form-control" id="tpl-rcontent" name="r_content[]" placeholder="192.0.2.10 or mail.[ZONE].">
    </div>
  </div>

  <div>
    <button class="btn btn-primary" type="submit">Save Template</button>
  </div>
</form>

<div class="panel">
  <h2 class="h6 mb-3">Saved Templates List</h2>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th scope="col">Template Name</th>
          <th scope="col">Description</th>
          <th scope="col">Record Count</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($templates as $t) : ?>
        <tr>
          <td><strong><?= e($t['name']) ?></strong></td>
          <td><?= e($t['description'] ?: '–') ?></td>
          <td><span class="badge bg-secondary"><?= (int) $t['rec_count'] ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$templates) : ?>
        <tr>
          <td colspan="3" class="text-center py-4 muted">No templates created yet.</td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
