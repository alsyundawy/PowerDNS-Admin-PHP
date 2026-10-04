<?php

declare(strict_types=1);

/**
 * @var string $title
 * @var array<string, mixed> $user
 * @var string $activeTab
 * @var string $cidr
 * @var array<string, mixed>|null $result
 * @var string|null $error
 * @var string|null $subnet
 * @var int|null $targetMask
 * @var array<int, string>|null $previewSubnets
 * @var int|null $totalCount
 */

$tab = $activeTab ?? 'ipcalc';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h2 class="h4 mb-1 text-light">
      <i class="fa-solid fa-calculator text-info me-2"></i>Kalkulator Subnet IPCalc &amp; IPv6 Splitter
    </h2>
    <p class="text-secondary small mb-0">Perhitungan bitwise subnetting IPv4, ekspansi 128-bit IPv6, reverse DNS (PTR), dan generator subnetting.</p>
  </div>
  <nav class="btn-group" aria-label="Navigasi Tab Tools">
    <a href="/tools/ipcalc" class="btn btn-sm <?= $tab === 'ipcalc' ? 'btn-primary' : 'btn-outline-secondary' ?>">
      <i class="fa-solid fa-network-wired me-1"></i>IPCalc
    </a>
    <a href="/tools/ipv6-splitter" class="btn btn-sm <?= $tab === 'splitter' ? 'btn-primary' : 'btn-outline-secondary' ?>">
      <i class="fa-solid fa-diagram-project me-1"></i>IPv6 Splitter
    </a>
  </nav>
</div>

<?php if (!empty($error)) : ?>
  <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
    <i class="fa-solid fa-circle-exclamation flex-shrink-0"></i>
    <div><?= e($error) ?></div>
  </div>
<?php endif; ?>

<?php if ($tab === 'ipcalc') : ?>
  <div class="panel">
    <form method="get" action="/tools/ipcalc" class="row g-2 align-items-end mb-3">
      <div class="col-md-9 col-12">
        <label for="cidr-input" class="form-label small fw-semibold">Masukkan Alamat IP / CIDR Prefix</label>
        <div class="input-group">
          <span class="input-group-text bg-dark border-secondary text-secondary">
            <i class="fa-solid fa-hashtag"></i>
          </span>
          <input type="text" class="form-control" id="cidr-input" name="cidr"
                 value="<?= e($cidr) ?>"
                 placeholder="Contoh: 192.168.1.0/24 atau 2001:db8::/32" required autofocus>
        </div>
      </div>
      <div class="col-md-3 col-12">
        <button type="submit" class="btn btn-primary w-100">
          <i class="fa-solid fa-magnifying-glass me-1"></i>Hitung Subnet
        </button>
      </div>
    </form>
    <div class="d-flex flex-wrap gap-2 pt-1">
      <span class="text-secondary small align-self-center">Contoh cepat:</span>
      <a href="/tools/ipcalc?cidr=10.0.0.0%2F8" class="badge bg-secondary-subtle text-light text-decoration-none">10.0.0.0/8</a>
      <a href="/tools/ipcalc?cidr=172.16.0.0%2F12" class="badge bg-secondary-subtle text-light text-decoration-none">172.16.0.0/12</a>
      <a href="/tools/ipcalc?cidr=192.168.1.0%2F24" class="badge bg-secondary-subtle text-light text-decoration-none">192.168.1.0/24</a>
      <a href="/tools/ipcalc?cidr=2001%3Adb8%3A%3A%2F32" class="badge bg-secondary-subtle text-light text-decoration-none">2001:db8::/32</a>
      <a href="/tools/ipcalc?cidr=2400%3A6180%3A%3A%2F48" class="badge bg-secondary-subtle text-light text-decoration-none">2400:6180::/48</a>
    </div>
  </div>

  <?php if (!empty($result)) : ?>
    <?php if (($result['version'] ?? 4) === 4) : ?>
      <!-- IPv4 Result Grid -->
      <div class="panel">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <h3 class="h6 mb-0 text-info fw-bold">
            <i class="fa-solid fa-circle-nodes me-2"></i>Hasil Analisis IPv4: <?= e((string) $result['cidr']) ?>
          </h3>
          <span class="pill <?= ($result['scope'] ?? '') === 'Private (RFC 1918)' ? 'accent' : 'ok' ?>">
            <i class="fa-solid fa-shield me-1"></i><?= e((string) ($result['scope'] ?? 'Public')) ?> &bull; Kelas <?= e((string) ($result['class'] ?? 'N/A')) ?>
          </span>
        </div>

        <div class="ipcalc-result-grid mb-3">
          <div class="ipcalc-card">
            <div class="ipcalc-card-title">Network Address</div>
            <div class="ipcalc-card-value text-info"><?= e((string) $result['network']) ?></div>
          </div>
          <div class="ipcalc-card">
            <div class="ipcalc-card-title">Netmask</div>
            <div class="ipcalc-card-value"><?= e((string) $result['netmask']) ?></div>
          </div>
          <div class="ipcalc-card">
            <div class="ipcalc-card-title">Wildcard Mask</div>
            <div class="ipcalc-card-value"><?= e((string) $result['wildcard']) ?></div>
          </div>
          <div class="ipcalc-card">
            <div class="ipcalc-card-title">Broadcast Address</div>
            <div class="ipcalc-card-value text-danger"><?= e((string) $result['broadcast']) ?></div>
          </div>
          <div class="ipcalc-card">
            <div class="ipcalc-card-title">Host Pertama (Usable)</div>
            <div class="ipcalc-card-value text-success"><?= e((string) $result['first_usable']) ?></div>
          </div>
          <div class="ipcalc-card">
            <div class="ipcalc-card-title">Host Terakhir (Usable)</div>
            <div class="ipcalc-card-value text-success"><?= e((string) $result['last_usable']) ?></div>
          </div>
          <div class="ipcalc-card">
            <div class="ipcalc-card-title">Jumlah Usable Hosts</div>
            <div class="ipcalc-card-value text-warning"><?= number_format((int) $result['usable_hosts']) ?></div>
          </div>
          <div class="ipcalc-card">
            <div class="ipcalc-card-title">Total Alamat (Termasuk Net/Bcast)</div>
            <div class="ipcalc-card-value"><?= number_format((int) $result['total_hosts']) ?></div>
          </div>
        </div>

        <div class="card mb-3 border-secondary">
          <div class="card-header py-2 small fw-semibold text-secondary">
            <i class="fa-solid fa-arrows-rotate me-1"></i>Reverse DNS (in-addr.arpa Pointer Zone)
          </div>
          <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <code class="fs-6" id="rdns-ipv4-val"><?= e((string) $result['reverse_dns']) ?></code>
              <button class="btn btn-sm btn-outline-secondary btn-copy-target" data-target="rdns-ipv4-val" type="button">
                <i class="fa-solid fa-copy me-1"></i>Salin Zone rDNS
              </button>
            </div>
          </div>
        </div>

        <div class="card border-secondary">
          <div class="card-header py-2 small fw-semibold text-secondary">
            <i class="fa-solid fa-binary me-1"></i>Representasi Bitwise Biner 32-Bit
          </div>
          <div class="card-body py-2">
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead>
                  <tr>
                    <th scope="col" class="text-secondary" style="width: 140px;">Properti</th>
                    <th scope="col" class="text-secondary">Nilai Biner</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <th scope="row" class="text-secondary fw-normal">IP Address:</th>
                    <td><code><?= e((string) $result['binary_ip']) ?></code></td>
                  </tr>
                  <tr>
                    <th scope="row" class="text-secondary fw-normal">Subnet Mask:</th>
                    <td><code><?= e((string) $result['binary_mask']) ?></code></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    <?php else : ?>
      <!-- IPv6 Result Grid -->
      <div class="panel">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <h3 class="h6 mb-0 text-info fw-bold">
            <i class="fa-solid fa-network-wired me-2"></i>Hasil Analisis IPv6: <?= e((string) $result['cidr']) ?>
          </h3>
          <span class="pill accent">
            <i class="fa-solid fa-globe me-1"></i><?= e((string) ($result['scope'] ?? 'Global')) ?>
          </span>
        </div>

        <div class="ipcalc-result-grid mb-3">
          <div class="ipcalc-card">
            <div class="ipcalc-card-title">Alamat Network</div>
            <div class="ipcalc-card-value text-info"><?= e((string) $result['network']) ?>/<?= e((string) $result['mask']) ?></div>
          </div>
          <div class="ipcalc-card">
            <div class="ipcalc-card-title">Compressed Address</div>
            <div class="ipcalc-card-value text-success"><?= e((string) $result['compressed']) ?></div>
          </div>
          <div class="ipcalc-card">
            <div class="ipcalc-card-title">Prefix Mask</div>
            <div class="ipcalc-card-value">/<?= e((string) $result['mask']) ?> bit</div>
          </div>
          <div class="ipcalc-card">
            <div class="ipcalc-card-title">Total Subnet /64 Tersedia</div>
            <div class="ipcalc-card-value text-warning"><?= e((string) $result['subnets_slash_64']) ?></div>
          </div>
        </div>

        <div class="card mb-3 border-secondary">
          <div class="card-header py-2 small fw-semibold text-secondary">
            <i class="fa-solid fa-expand me-1"></i>Format Full Expanded (32-Digit Hex, 8 Kelompok)
          </div>
          <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <code class="fs-6" id="expanded-ipv6-val"><?= e((string) $result['uncompressed']) ?></code>
              <button class="btn btn-sm btn-outline-secondary btn-copy-target" data-target="expanded-ipv6-val" type="button">
                <i class="fa-solid fa-copy me-1"></i>Salin
              </button>
            </div>
          </div>
        </div>

        <div class="card mb-3 border-secondary">
          <div class="card-header py-2 small fw-semibold text-secondary">
            <i class="fa-solid fa-arrows-rotate me-1"></i>Reverse DNS (ip6.arpa Pointer Zone)
          </div>
          <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <code class="fs-6" id="rdns-ipv6-val"><?= e((string) $result['reverse_dns']) ?></code>
              <button class="btn btn-sm btn-outline-secondary btn-copy-target" data-target="rdns-ipv6-val" type="button">
                <i class="fa-solid fa-copy me-1"></i>Salin Zone rDNS
              </button>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-end">
          <a href="/tools/ipv6-splitter?subnet=<?= urlencode((string) $result['cidr']) ?>&target_mask=<?= min(128, (int) $result['mask'] + 16) ?>"
             class="btn btn-sm btn-outline-primary">
            <i class="fa-solid fa-diagram-project me-1"></i>Bagi Prefix Ini di IPv6 Splitter &raquo;
          </a>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>

<?php else : ?>
  <!-- Tab 2: IPv6 Subnet Splitter -->
  <div class="panel">
    <form method="get" action="/tools/ipv6-splitter" class="row g-3 align-items-end mb-3">
      <div class="col-md-6 col-12">
        <label for="subnet-input" class="form-label small fw-semibold">Base Subnet IPv6 (Sumber)</label>
        <div class="input-group">
          <span class="input-group-text bg-dark border-secondary text-secondary">
            <i class="fa-solid fa-sitemap"></i>
          </span>
          <input type="text" class="form-control" id="subnet-input" name="subnet"
                 value="<?= e($subnet ?? '2001:db8::/32') ?>"
                 placeholder="Contoh: 2001:db8::/32" required autofocus>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <label for="target-mask-select" class="form-label small fw-semibold">Target Prefix Tujuan</label>
        <select class="form-select" id="target-mask-select" name="target_mask">
          <?php for ($m = 32; $m <= 128; $m += 4) : ?>
            <?php
            $optLabel = '';
              if ($m === 48) {
                  $optLabel = '(Site / Enterprise)';
              } elseif ($m === 64) {
                  $optLabel = '(Standard SLAAC Subnet)';
              }
              ?>
            <option value="<?= $m ?>" <?= ($targetMask ?? 48) === $m ? 'selected' : '' ?>>
              /<?= $m ?><?= $optLabel !== '' ? ' ' . $optLabel : '' ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-3 col-6">
        <button type="submit" class="btn btn-primary w-100">
          <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Generate Subnet
        </button>
      </div>
    </form>

    <div class="d-flex flex-wrap gap-2 pt-1">
      <span class="text-secondary small align-self-center">Preset Populer:</span>
      <a href="/tools/ipv6-splitter?subnet=2001%3Adb8%3A%3A%2F32&target_mask=48" class="badge bg-secondary-subtle text-light text-decoration-none">/32 ke /48 (65.536 subnet)</a>
      <a href="/tools/ipv6-splitter?subnet=2001%3Adb8%3A%3A%2F48&target_mask=64" class="badge bg-secondary-subtle text-light text-decoration-none">/48 ke /64 (65.536 subnet)</a>
      <a href="/tools/ipv6-splitter?subnet=2001%3Adb8%3A%3A%2F56&target_mask=64" class="badge bg-secondary-subtle text-light text-decoration-none">/56 ke /64 (256 subnet)</a>
    </div>
  </div>

  <?php if (!empty($previewSubnets)) : ?>
    <div class="panel">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
          <h3 class="h6 mb-0 text-info fw-bold">
            <i class="fa-solid fa-list-check me-2"></i>Daftar Subnet yang Dihasilkan
          </h3>
          <small class="text-secondary">
            Total: <strong><?= number_format((int) ($totalCount ?? count($previewSubnets))) ?></strong> subnet /<?= e((string) ($targetMask ?? 48)) ?>
            <?php if (($totalCount ?? 0) > count($previewSubnets)) : ?>
              (Menampilkan <?= count($previewSubnets) ?> subnet pertama dalam pratinjau)
            <?php endif; ?>
          </small>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-secondary btn-copy-target" data-target="subnet-preview-box" type="button">
            <i class="fa-solid fa-copy me-1"></i>Salin Pratinjau
          </button>
          <a href="/tools/ipv6-splitter?subnet=<?= urlencode((string) $subnet) ?>&target_mask=<?= e((string) $targetMask) ?>&download=1"
             class="btn btn-sm btn-success">
            <i class="fa-solid fa-download me-1"></i>Download Semua (.txt)
          </a>
        </div>
      </div>

      <div class="raw-output-box" id="subnet-preview-box"><?php
        foreach ($previewSubnets as $sub) {
            echo e($sub) . "\n";
        }
      ?></div>
    </div>
  <?php endif; ?>
<?php endif; ?>
