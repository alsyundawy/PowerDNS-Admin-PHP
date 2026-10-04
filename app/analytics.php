<?php

/**
 * Advanced DNS Telemetry & Visual Analytics Engine.
 * Decodes PowerDNS HTTP API ring buffers (queries, remotes, qtypes),
 * calculates cache hit ratios, and generates native Zero-CDN vector SVG gauges.
 */

declare(strict_types=1);

/**
 * Calculate packet cache hit ratio in percentage (0.0 to 100.0).
 */
function calculatePacketCacheRatio(int $hits, int $misses): float
{
    $total = $hits + $misses;
    if ($total <= 0) {
        return 0.0;
    }
    return round(($hits / $total) * 100, 1);
}

/**
 * Extract and aggregate frequency from a PowerDNS ring buffer array.
 *
 * @param array<int, array{name?: string, value?: string}>|array<int, mixed> $ringItems
 * @return array<int, array{item: string, count: int, percentage: float}>
 */
function aggregateRingBuffer(array $ringItems, int $limit = 10, bool $anonymizeIp = false): array
{
    $counts = [];
    $total = 0;

    foreach ($ringItems as $row) {
        $val = '';
        if (is_array($row)) {
            $val = (string) ($row['name'] ?? $row['value'] ?? '');
        } elseif (is_string($row)) {
            $val = $row;
        }

        $val = trim($val);
        if ($val === '') {
            continue;
        }

        if ($anonymizeIp) {
            $val = anonymizeClientIp($val);
        }

        $counts[$val] = ($counts[$val] ?? 0) + 1;
        $total++;
    }

    arsort($counts, SORT_NUMERIC);
    $results = [];
    $sliced = array_slice($counts, 0, $limit, true);

    foreach ($sliced as $item => $cnt) {
        $pct = $total > 0 ? round(($cnt / $total) * 100, 1) : 0.0;
        $results[] = [
            'item' => (string) $item,
            'count' => $cnt,
            'percentage' => $pct,
        ];
    }

    return $results;
}

/**
 * Alias for aggregateRingBuffer.
 *
 * @param array<int, array{name?: string, value?: string}>|array<int, mixed> $ringItems
 * @return array<int, array{item: string, count: int, percentage: float}>
 */
function parseRingBuffer(array $ringItems, int $limit = 10, bool $anonymizeIp = false): array
{
    return aggregateRingBuffer($ringItems, $limit, $anonymizeIp);
}

/**
 * Mask client IP last octet for privacy compliance.
 */
function anonymizeClientIp(string $ip): string
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        $parts[3] = '0/24';
        return implode('.', $parts);
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $packed = inet_pton($ip);
        if ($packed !== false) {
            // Keep first 64 bits (8 bytes) and mask remainder
            $masked = substr($packed, 0, 8) . str_repeat("\0", 8);
            $expanded = inet_ntop($masked);
            return ($expanded !== false ? $expanded : $ip) . '/64';
        }
    }
    return $ip;
}

/**
 * Render pure vector SVG Donut Gauge for percentage ratios.
 */
function renderSvgDonutGauge(float $percentage, string $title, string $color = '#10b981', int $size = 180): string
{
    $percentage = max(0.0, min(100.0, $percentage));
    $radius = 70;
    $circumference = 2 * M_PI * $radius; // ~439.82
    $dashOffset = $circumference * (1 - ($percentage / 100));

    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeColor = htmlspecialchars($color, ENT_QUOTES, 'UTF-8');

    $svgOpen = sprintf(
        '<svg viewBox="0 0 180 180" width="%d" height="%d" class="donut-gauge" role="img" aria-label="%s">',
        $size,
        $size,
        $safeTitle
    );

    return $svgOpen
        . '<circle cx="90" cy="90" r="' . $radius . '" fill="none" stroke="currentColor" '
        . 'stroke-opacity="0.12" stroke-width="14" />'
        . '<circle cx="90" cy="90" r="' . $radius . '" fill="none" stroke="' . $safeColor . '" stroke-width="14" '
        . 'stroke-dasharray="' . round($circumference, 2) . '" stroke-dashoffset="' . round($dashOffset, 2) . '" '
        . 'stroke-linecap="round" transform="rotate(-90 90 90)" />'
        . '<text x="90" y="85" text-anchor="middle" font-size="28" font-weight="700" fill="currentColor">'
        . number_format($percentage, 1) . '%</text>'
        . '<text x="90" y="112" text-anchor="middle" font-size="12" fill="currentColor" opacity="0.75">'
        . $safeTitle . '</text>'
        . '</svg>';
}

/**
 * Render pure vector SVG Protocol Split Bar (UDP vs TCP).
 */
function renderSvgProtocolRatio(int $udp, int $tcp, int $width = 320, int $height = 20): string
{
    $total = $udp + $tcp;
    $udpPct = $total > 0 ? ($udp / $total) * 100 : 50.0;

    $udpWidth = max(0, min($width, (int) round(($udpPct / 100) * $width)));
    $tcpWidth = $width - $udpWidth;

    $svgOpen = sprintf(
        '<svg viewBox="0 0 %d %d" width="100%%" height="%d" class="proto-ratio-bar" role="img" '
        . 'aria-label="Rasio UDP vs TCP">',
        $width,
        $height,
        $height
    );

    return $svgOpen
        . '<rect x="0" y="0" width="' . $udpWidth . '" height="' . $height . '" rx="4" fill="#3b82f6" />'
        . '<rect x="' . $udpWidth . '" y="0" width="' . $tcpWidth . '" height="' . $height . '" '
        . 'rx="4" fill="#8b5cf6" />'
        . '</svg>';
}

/**
 * Render pure vector SVG Horizontal Bar Chart for Top Queries / Remotes.
 *
 * @param array<int, array{item: string, count: int, percentage: float}> $items
 */
function renderSvgHorizontalBarChart(array $items, int $width = 460, int $rowHeight = 32): string
{
    if (empty($items)) {
        return '<div class="text-secondary small py-3 text-center">'
            . 'Tidak ada data ring buffer telemetry saat ini.</div>';
    }

    $height = count($items) * $rowHeight;
    $svg = sprintf(
        '<svg viewBox="0 0 %d %d" width="100%%" height="%d" class="bar-chart" role="img">',
        $width,
        $height,
        $height
    );

    $maxCount = 1;
    foreach ($items as $row) {
        if ($row['count'] > $maxCount) {
            $maxCount = $row['count'];
        }
    }

    $barMaxWidth = $width - 170; // 120px for label + 50px for count
    foreach ($items as $idx => $row) {
        $y = $idx * $rowHeight;
        $barW = max(4, (int) round(($row['count'] / $maxCount) * $barMaxWidth));
        $label = htmlspecialchars($row['item'], ENT_QUOTES, 'UTF-8');
        if (strlen($label) > 22) {
            $label = substr($label, 0, 20) . '&hellip;';
        }

        $formattedCount = number_format($row['count']) . ' (' . $row['percentage'] . '%)';
        $svg .= '<text x="0" y="' . ($y + 20) . '" font-size="12" fill="currentColor" opacity="0.85">'
            . $label . '</text>'
            . '<rect x="125" y="' . ($y + 8) . '" width="' . $barW . '" height="15" rx="3" '
            . 'fill="#0ea5e9" opacity="0.85" />'
            . '<text x="' . ($width - 5) . '" y="' . ($y + 20) . '" text-anchor="end" font-size="12" '
            . 'font-weight="600" fill="currentColor">'
            . $formattedCount
            . '</text>';
    }

    $svg .= '</svg>';
    return $svg;
}
