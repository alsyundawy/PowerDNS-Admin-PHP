<?php

declare(strict_types=1);

/**
 * @var string $q
 * @var array<int, array<string, mixed>> $results
 * @var string|null $error
 */

?>
<form class="search-inline mb-3" method="get" action="/search">
  <input class="form-control" name="q" value="<?= e($q) ?>"
         placeholder="Search hostname, record type, zone, or content..." aria-label="Search query">
  <button class="btn btn-primary" type="submit">Search</button>
</form>

<?php if (!empty($error)) : ?>
  <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<div class="panel">
  <header>
    <h2>Search Results</h2>
  </header>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th scope="col">Object Type</th>
          <th scope="col">Name</th>
          <th scope="col">Authoritative Zone</th>
          <th scope="col">Content</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($results as $r) : ?>
            <?php if (is_array($r)) : ?>
        <tr>
          <td><span class="badge bg-secondary"><?= e((string) ($r['object_type'] ?? '')) ?></span></td>
          <td><strong><?= e((string) ($r['name'] ?? '')) ?></strong></td>
          <td>
                <?php $z = (string) ($r['zone'] ?? $r['zone_id'] ?? ''); ?>
                <?php if ($z !== '') : ?>
              <a href="/zones/<?= e(rawurlencode(rtrim($z, '.'))) ?>"><?= e(dnsDisplay($z)) ?></a>
                <?php else : ?>
              –
                <?php endif; ?>
          </td>
          <td><code><?= e((string) ($r['content'] ?? '')) ?></code></td>
        </tr>
            <?php endif; ?>
      <?php endforeach; ?>
      <?php if (!$results && $q !== '') : ?>
        <tr>
          <td colspan="4" class="text-center py-4 muted">No records matching your search query were found.</td>
        </tr>
      <?php elseif (!$results) : ?>
        <tr>
          <td colspan="4" class="text-center py-4 muted">
            Enter a search term above to search across all PowerDNS zones.
          </td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
