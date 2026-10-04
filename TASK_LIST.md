# TASK LIST — PowerDNS-Admin-PHP v0.3.0

**Status:** Ready for Review
**Tracking Model:** Phased Execution Checklists (Strict TDD & Empirical Verification)
**Safety Invariant:** All changes must maintain backward compatibility with v0.2.1 databases and configs.

---

## Phase 1: Database Migration & Schema Extensions (Non-Breaking)

- [x] **Task 1.1:** Create database migration file `sql/migrations/0.3.0_enterprise_upgrade.sql`
  - Define `pdns_servers` table with `id`, `name`, `api_url`, `api_key_encrypted`, `server_id`, `is_default`, `is_active`, `latency_ms`.
  - Define `webhooks` table with `id`, `name`, `url`, `secret`, `events`, `is_active`, `last_status_code`.
  - Define `dyndns_tokens` table with `id`, `name`, `token_hash`, `hostname`, `record_type`, `user_id`.
  - Add `totp_secret`, `totp_enabled`, `totp_backup_codes` columns to `users` with `IF NOT EXISTS` logic.
- [x] **Task 1.2:** Update `app/bootstrap.php` schema auto-upgrader
  - Ensure single-statement safe schema check runs without dropping existing tables or data.
  - Automatically populate `pdns_servers` with row #1 if legacy `settings` holds active API credentials.
- [x] **Task 1.3:** Verify schema migration on fresh MySQL / MariaDB instances.

---

## Phase 2: Native Two-Factor Authentication (2FA TOTP RFC 6238)

- [x] **Task 2.1:** Implement pure native Base32 encoder/decoder (`app/totp.php`)
  - Encode arbitrary binary strings into RFC 4648 Base32 alphabet (`A-Z`, `2-7`).
  - Decode Base32 string to binary with whitespace and padding tolerance.
- [x] **Task 2.2:** Implement RFC 6238 TOTP algorithm
  - Generate 160-bit cryptographically secure random secret (`random_bytes(20)`).
  - Compute 30-second time-slice counter using `pack('J', $timeSlice)` or 64-bit big-endian integer.
  - Execute `hash_hmac('sha1', $packedTime, $secretBinary, true)` and dynamic 4-byte offset truncation.
  - Modulo $10^6$ to produce 6-digit zero-padded numeric string.
  - Time-drift validation for window $W \in \{-1, 0, +1\}$ (±30s skew tolerance).
- [x] **Task 2.3:** Implement pure PHP vector SVG QR Code Generator
  - Generate compact `data:image/svg+xml;utf8,...` without external GD, Imagick, or remote Google Chart APIs.
- [x] **Task 2.4:** Implement 10 single-use emergency backup recovery scratch codes
  - Generate 8-character alphanumeric codes, hashed with `password_hash()` in JSON array.
- [x] **Task 2.5:** Update authentication pipeline (`app/handlers.php` and `views/login.php`)
  - Add intermediate session challenge `$_SESSION['pending_2fa_user_id']` when TOTP is active.
  - Render clean 2FA challenge form in `views/login.php`.
  - Add 2FA management section in `views/profile.php` (Enable, Verify Test Code, View Scratch Codes, Disable).
- [x] **Task 2.6:** Write automated unit test `tests/test_totp.php` verifying RFC test vectors.

---

## Phase 3: Multi-Server PowerDNS Node Clustering

- [x] **Task 3.1:** Implement `app/PdnsCluster.php`
  - Manage server CRUD operations in `pdns_servers`.
  - Provide `getActiveServer(): array` with session persistence and fallback to legacy settings.
  - Provide `setActiveServer(int $serverId): void`.
- [x] **Task 3.2:** Refactor `pdns()` factory in `app/bootstrap.php`
  - Instantiate `PdnsClient` dynamically using the active server's decrypted API key and base URL.
  - Ensure zero breaking changes for existing code calling `pdns()->getZones()`.
- [x] **Task 3.3:** Add server switcher UI component to top navbar (`views/layout.php`)
  - Render current active node badge with latency pill.
  - Add dropdown to switch active node seamlessly.
- [x] **Task 3.4:** Create Servers Management View (`views/servers.php`)
  - List configured nodes, status badges, API latency, and default indicators.
  - Add/Edit/Delete node modal with connection test button.
- [x] **Task 3.5:** Write automated unit test `tests/test_cluster.php`.

---

## Phase 4: High-Performance In-Memory Caching Adapter

- [x] **Task 4.1:** Implement `app/cache.php`
  - Detect `apcu` extension and shared memory availability.
  - Detect optional `redis` extension connection.
  - Provide unified methods: `cacheGet($key)`, `cacheSet($key, $val, $ttl)`, `cacheDelete($key)`, `cacheFlush($pattern)`.
- [x] **Task 4.2:** Wrap heavy read operations with caching layer
  - Cache zones list (`pdns:zones:server_<id>`) for 60 seconds with instant invalidation on mutation.
  - Cache PowerDNS daemon statistics (`pdns:stats:server_<id>`) for 30 seconds.
- [x] **Task 4.3:** Add cache flush hooks to zone creation, zone deletion, and record save handlers.
- [x] **Task 4.4:** Write automated unit test `tests/test_cache.php`.

---

## Phase 5: Cryptographic Webhook Dispatcher (HMAC-SHA256)

- [x] **Task 5.1:** Implement `app/webhook_services.php`
  - Function `dispatchWebhookEvent(string $event, array $payload): void`.
  - Sign JSON payload with `hash_hmac('sha256', $payloadJson, $secret)`.
  - Send non-blocking cURL POST with `X-PDNS-Signature: sha256=...` and `Content-Type: application/json`.
- [x] **Task 5.2:** Attach webhook triggers to core zone events
  - `zone.created`: When a new forward/reverse zone is created.
  - `zone.deleted`: When a zone is removed.
  - `record.updated`: When RRsets are patched.
- [x] **Task 5.3:** Create Webhooks Management View (`views/webhooks.php`)
  - Form to register webhook URL, event checkboxes, secret generator, and test delivery button.
- [x] **Task 5.4:** Write automated unit test `tests/test_webhooks.php`.

---

## Phase 6: Cross-Zone Bulk Record Operations

- [x] **Task 6.1:** Implement bulk record search service (`app/services.php`)
  - Search across all authoritative zones for matching record content or names with type filters.
- [x] **Task 6.2:** Implement atomic batch replacement & deletion
  - Automatically create snapshot in `zone_snapshots` for every touched zone prior to mutation.
  - Patch PowerDNS RRsets atomically per zone.
- [x] **Task 6.3:** Create Bulk Records View (`views/bulk_records.php`)
  - Search interface, preview of affected zones and records, batch replace confirmation modal.
- [x] **Task 6.4:** Write automated unit test `tests/test_bulk_records.php`.

---

## Phase 7: Zone RFC Compliance & Linting Engine

- [x] **Task 7.1:** Implement `app/zone_linter.php`
  - Check 1: Apex CNAME conflicts with SOA/NS (RFC 1912).
  - Check 2: Missing internal glue records for delegated NS (RFC 1035).
  - Check 3: MX exchange points to CNAME rather than A/AAAA (RFC 2181).
  - Check 4: Dangling CNAME pointers within zone.
  - Check 5: Forward A/AAAA records missing reverse PTR pointer in managed reverse zones.
- [x] **Task 7.2:** Integrate linter into zone editor (`views/zone_show.php`)
  - Render non-blocking "RFC Diagnostics" tab highlighting warnings and best practice suggestions with 1-click fixes.
- [x] **Task 7.3:** Write automated unit test `tests/test_linter.php`.

---

## Phase 8: Advanced DNS Telemetry & Visual Analytics Engine

- [x] **Task 8.1:** Extend `PdnsClient::statistics(true)` parser to decode ring buffers (`queries`, `remotes`, `qtypes`).
- [x] **Task 8.2:** Implement native SVG Vector Chart generator helpers (`app/analytics.php`)
  - Live Donut Gauge: Packet Cache Hit Ratio ($\frac{\text{hits}}{\text{hits} + \text{misses}} \times 100\%$).
  - Protocol Ratio: UDP vs TCP queries visual gauge.
  - Horizontal Bar Charts: Top 10 Queried Domains & Top 10 Remote Client IPs.
  - Query Types Distribution: Breakdown of `A`, `AAAA`, `PTR`, `CNAME`, `TXT`, `MX`.
- [x] **Task 8.3:** Implement `/analytics` route & view (`views/analytics.php`)
  - Real-time auto-refresh selector (Off, 15s, 30s, 60s) with live polling.
  - Server node switcher integration (view analytics for selected cluster node).
  - Privacy compliance toggle (truncate / mask client IP last octet).
- [x] **Task 8.4:** Implement Telemetry Exporter (`/analytics/export`)
  - Streaming download of current statistical snapshot in JSON or CSV.
- [x] **Task 8.5:** Write automated unit test `tests/test_analytics.php`.

---

## Phase 9: Comprehensive Verification & Documentation

- [x] **Task 9.1:** Run all unit tests via Bash:
      `for f in tests/test_*.php; do php "$f"; done` (Exit Code 0).
- [x] **Task 9.2:** Run PHP syntax check (`php -l`) and Bash check (`bash -n`).
- [x] **Task 9.3:** Update `CHANGELOG.md`, `README.md`, and `DOCNOTE.md` with complete v0.3.0 documentation.
