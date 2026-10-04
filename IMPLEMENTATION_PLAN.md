# IMPLEMENTATION PLAN — PowerDNS-Admin-PHP v0.3.0

**Project:** PowerDNS-Admin-PHP
**Target Release:** `v0.3.0`
**Architect:** Principal Staff Engineer & Linux System Specialist
**Design Paradigm:** Native PHP 8.1+ (PSR-compliant, PDO), Zero-Framework, Zero-Python, Zero-Node, Air-Gapped Zero-CDN
**Backward Compatibility Guarantee:** 100% Non-Breaking Preservation of v0.2.1 Features, Tables, and Configurations

---

## 1. Architectural Vision & Core Invariants

Version **0.3.0** transforms PowerDNS-Admin-PHP from a single-instance zone editor into an **Enterprise-Grade Distributed Authoritative DNS Control Plane**, integrating battle-tested architectural strengths from user reference repositories:

- **Clustering & Multi-Node Routing** (from [PHP-PDNSManager](https://github.com/alsyundawy/PHP-PDNSManager))
- **In-Memory Caching & Rate-Limiting** (from [OrbDNS-PDNSAdmin-PHP](https://github.com/alsyundawy/OrbDNS-PDNSAdmin-PHP))
- **BIND9 Syntax & Zone Integration** (from [php-bind-dashboard](https://github.com/alsyundawy/php-bind-dashboard))
- **Real-Time Error Handling & Status Telemetry** (from [ndash](https://github.com/alsyundawy/ndash))

### Non-Negotiable System Invariants

1. **Pure Native PHP (Zero Frameworks):** No Symfony, Laravel, Yii, or Slim. Pure PHP PDO, native routing, and modular procedural services.
2. **Zero Python & Zero Node.js at Runtime:** All cryptographic algorithms (TOTP RFC 6238, HMAC-SHA256, AES-256-GCM), QR code vector generation, BIND parsing, and IP math must execute natively within the PHP engine.
3. **100% Backward Compatibility ("Do No Harm"):**
   - Existing single-server setups in `settings` or `config.php` continue to work out of the box as Server `#1` (Default Node).
   - Existing database tables (`users`, `zones`, `history`, `zone_snapshots`, `templates`) are preserved intact. All schema changes are strictly additive (`ADD COLUMN IF NOT EXISTS` or new independent tables).
4. **Air-Gapped & Sovereign Network Ready:** All assets (FontAwesome 6.7.2, CSS tokens, JS components) remain 100% offline local.

---

## 2. Feature Roadmap & Technical Specifications

```text
┌─────────────────────────────────────────────────────────────────────────────────────────────────┐
│                           PowerDNS-Admin-PHP v0.3.0 CONTROL PLANE                               │
├───────────────────────────────┬─────────────────────────────────┬───────────────────────────────┤
│   Multi-Server Clustering     │      Enterprise Security        │    Performance & Automation   │
│  - Multi-node pdns_servers    │  - RFC 6238 TOTP 2FA Native     │  - Native APCu / Redis Cache  │
│  - Active Node Switching      │  - SVG QR Code Engine (No GD)   │  - HMAC-SHA256 Webhook Engine │
│  - Telemetry & Ping Latency   │  - Emergency Scratch Codes      │  - Bulk Record Search/Replace │
│  - Node Health Error Banner   │  - Scoped DynDNS Auth Tokens    │  - Zone RFC Compliance Linter │
│  - Query Telemetry & Gauges   │  - Anonymized Client IP Mode    │  - Ring Buffer Visual Charts  │
└───────────────────────────────┴─────────────────────────────────┴───────────────────────────────┘
```

### Feature 1: Multi-Server PowerDNS Node Clustering (`pdns_servers`)

- **Objective:** Manage multiple independent PowerDNS Authoritative instances (e.g., Primary NS1, Secondary NS2, Edge Anycast Nodes) from a unified console.
- **Database Schema Addition:**

  ```sql
  CREATE TABLE IF NOT EXISTS pdns_servers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(128) NOT NULL,
    api_url VARCHAR(255) NOT NULL,
    api_key_encrypted TEXT NOT NULL,
    server_id VARCHAR(64) NOT NULL DEFAULT 'localhost',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    latency_ms INT NULL,
    last_check_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pdns_server_name (name)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  ```

- **Execution Logic:**
  - `active_server_id` stored in session `$_SESSION['active_pdns_server_id']`.
  - Fallback: If `pdns_servers` is empty, fallback transparently to legacy `settings` table (`pdns_api_url`, `pdns_api_key`), ensuring 100% zero-regression backward compatibility.
  - Active node switcher dropdown added to navigation bar.
  - Asynchronous-like latency ping (`/api/servers/ping`) measuring round-trip time in milliseconds.

### Feature 2: Native Two-Factor Authentication (2FA TOTP RFC 6238)

- **Objective:** Add hardware/app authenticator (Google Authenticator, Authy, Apple Passwords, 1Password) security without external Composer packages.
- **Database Schema Addition:**

  ```sql
  ALTER TABLE users
    ADD COLUMN totp_secret VARCHAR(64) NULL AFTER password_hash,
    ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret,
    ADD COLUMN totp_backup_codes TEXT NULL AFTER totp_enabled;
  ```

- **Native Implementation Details:**
  - Base32 encoding/decoding implemented in pure PHP (`base32_encode`, `base32_decode`).
  - RFC 6238 time-step calculation: `floor(time() / 30)`.
  - Binary HMAC-SHA1 generation via `hash_hmac('sha1', $time_packed, $secret_binary, true)`.
  - Dynamic truncation (extracting 4-byte integer from 16-byte HMAC hash) to yield 6-digit code.
  - Time-drift window verification: Checks `T-1`, `T0`, and `T+1` (±30 seconds) to tolerate clock skew.
  - Pure SVG QR Code Generator: Renders clean vector SVG without GD, Imagick, or external APIs (`data:image/svg+xml;utf8,...`).
  - 10 cryptographically random 8-character single-use emergency scratch codes hashed with Bcrypt.

### Feature 3: High-Performance In-Memory Caching Subsystem

- **Objective:** Accelerate zone lists, query counts, and server metadata, reducing PowerDNS API latency from 45ms to <1ms.
- **Adapter Architecture:**
  - Unified Interface: `CacheInterface` with `get($key)`, `set($key, $val, $ttl)`, `delete($key)`, `flush()`.
  - Native APCu Driver: When `extension_loaded('apcu')` and `apc.enabled=1`, stores serialized arrays in shared memory.
  - Optional Redis Driver: When `extension_loaded('redis')` and configured in settings, connects to local Redis instance.
  - Null/Database Fallback: When neither is present, operates transparently without cache (or stores transient state in memory for the lifecycle of the request).
  - Smart Invalidation: Cache keys tagged by zone name (`pdns:zone:example.com`) flushed automatically upon record creation, update, or deletion.

### Feature 4: Cryptographic Webhook Dispatcher (HMAC-SHA256)

- **Objective:** Enable event-driven CI/CD integration, notifying external systems (Slack, Discord, internal orchestrators, DNS propagation monitors) when zones or records mutate.
- **Database Schema Addition:**

  ```sql
  CREATE TABLE IF NOT EXISTS webhooks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(128) NOT NULL,
    url VARCHAR(512) NOT NULL,
    secret VARCHAR(128) NOT NULL,
    events VARCHAR(255) NOT NULL, -- comma-separated: zone.create,zone.delete,record.update
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_status_code INT NULL,
    last_error VARCHAR(255) NULL,
    last_triggered_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  ```

- **Security & Dispatch Details:**
  - Payload signed with HMAC-SHA256 sent via HTTP header:
    `X-PDNS-Signature: sha256=hash_hmac('sha256', $json_payload, $webhook_secret)`.
  - Non-blocking cURL delivery with tight 3-second timeout to prevent slowing down user interface requests.

### Feature 5: Cross-Zone Bulk Record Operations

- **Objective:** Allow administrators to perform mass IP migrations or domain redirection updates across hundreds of zones in one operation.
- **Capabilities:**
  - Search across all authoritative zones for matching record content or names (e.g. Find all `A` records pointing to `192.0.2.1`).
  - Batch Replace: Safely replace target IP `192.0.2.1` with `198.51.100.1` across selected or all zones.
  - Batch Delete: Mass remove deprecated legacy records across all zones.
  - Automatic Snapshot Generation: Automatically creates a zone snapshot before executing batch changes, allowing 1-click rollback.

### Feature 6: Granular DynDNS Multi-Token Gateway

- **Objective:** Expand the DynDNS endpoint (`/api/v1/dyndns`) to support independent per-device or per-subdomain authentication tokens without exposing the master account password.
- **Database Schema Addition:**

  ```sql
  CREATE TABLE IF NOT EXISTS dyndns_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(128) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    hostname VARCHAR(255) NOT NULL, -- e.g. vpn.example.com
    record_type ENUM('A','AAAA','BOTH') NOT NULL DEFAULT 'BOTH',
    user_id INT UNSIGNED NOT NULL,
    last_ip VARCHAR(64) NULL,
    last_update_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dyndns_token (token_hash),
    KEY idx_dyndns_host (hostname)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  ```

### Feature 7: Zone RFC Compliance, Linter & Sanity Engine

- **Objective:** Run automated RFC diagnostics before saving or committing zone changes to prevent DNS misconfigurations.
- **Rule Checks (Static Linter):**
  1. **Apex CNAME Conflict (RFC 1912 & RFC 2181):** Warns if a `CNAME` record is created at the apex (`example.com.`) alongside `SOA` or `NS` records, recommending native PowerDNS `ALIAS` instead.
  2. **Dangling CNAME Detection:** Detects CNAME records pointing to non-existent internal records.
  3. **Glue Record Verification (RFC 1035):** Verifies that authoritative nameservers inside the same domain (e.g. `ns1.example.com` for `example.com`) have corresponding `A`/`AAAA` glue records.
  4. **MX Target Validation (RFC 2181 §10.3):** Flags any `MX` record whose exchange target points to a `CNAME` rather than an `A`/`AAAA` hostname.
  5. **Reverse PTR Missing Pointer:** Highlights forward `A`/`AAAA` records lacking a corresponding reverse PTR entry.

### Feature 8: Real-Time Node Health Banner & Circuit Breaker (Inspired by `ndash`)

- **Objective:** Provide instant, graceful degradation when a PowerDNS daemon or database node experiences network disruption.
- **UI Components:**
  - Global error banner with visual pulsing badge (Green = Healthy, Amber = High Latency, Red = Offline).
  - 1-Click "Retry Connection" button that bypasses cache and re-validates the connection.
  - Detailed diagnostic message explaining whether the issue is an HTTP 401 (API key mismatch), HTTP 404 (Zone missing), or connection timeout (PDNS daemon down).

### Feature 9: Advanced DNS Telemetry & Visual Analytics Engine

- **Objective:** Provide actionable insights, traffic patterns, and performance metrics from the PowerDNS Authoritative daemon and zone database without external telemetry collectors.
- **Data Collection & Ring Buffers (`?include_rings=true`):**
  - **Packet Cache Hit Ratio:** Calculates $\frac{\text{packetcache-hit}}{\text{packetcache-hit} + \text{packetcache-miss}} \times 100\%$ and renders a live visual donut gauge.
  - **Protocol Distribution:** Evaluates `udp-queries` vs `tcp-queries` to monitor transport volume.
  - **Top 10 Queried Domains:** Decodes the `queries` ring buffer to identify hot domains.
  - **Top 10 Client IP Remotes:** Decodes the `remotes` ring buffer to identify heavy resolvers and detect traffic anomalies (with an optional anonymization toggle for privacy compliance).
  - **Query Type Distribution:** Decodes the `qtypes` ring buffer (`A`, `AAAA`, `PTR`, `CNAME`, `TXT`, `MX`, `SOA`, `SRV`).
- **Zero-CDN Native Visualization:**
  - Visual charts rendered using lightweight pure SVG vectors and HTML5 Canvas with native CSS token theming (Cyberpunk OLED dark and Daylight light mode). Zero external CDN or JavaScript libraries required.
  - Auto-refresh toggle: Off, 15s, 30s, 60s with subtle countdown indicator.
  - Telemetry snapshot exporter: One-click export of current metrics to JSON or CSV for external reporting.

---

## 3. Phased Implementation Roadmap

| Phase       | Milestone Name                     | Scope & Deliverables                                                                                           | Estimated Impact                                                           |
| ----------- | ---------------------------------- | -------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| **Phase 1** | **Database & Migration Layer**     | Add `pdns_servers`, `webhooks`, `dyndns_tokens` tables and `users` 2FA columns with safe `ALTER TABLE` checks. | Zero downtime, 100% backward compatible.                                   |
| **Phase 2** | **Core Security & Crypto Engine**  | Implement native RFC 6238 TOTP math, Base32 codec, and SVG vector QR renderer in `app/totp.php`.               | Enterprise MFA security without external dependencies.                     |
| **Phase 3** | **Multi-Server Routing & Caching** | Create `app/PdnsCluster.php` and `app/cache.php` (APCu / Redis abstraction).                                   | Multi-node management with sub-millisecond cache latency.                  |
| **Phase 4** | **Automation & Bulk Operations**   | Implement `app/webhook_services.php` (HMAC-SHA256 dispatcher) and bulk record search/replace.                  | Rapid CI/CD orchestration and enterprise mass updates.                     |
| **Phase 5** | **Zone RFC Linter & UI Polish**    | Implement pre-flight RFC checks, health banner with auto-retry, and 2FA profile setup modal.                   | Enhanced sysadmin ergonomics and error-free DNS authoring.                 |
| **Phase 6** | **Telemetry & Visual Analytics**   | Implement PowerDNS ring buffer parser, native SVG gauges, top domains/remotes, and telemetry export.           | Full visibility into DNS traffic, cache efficiency, and anomalous queries. |
| **Phase 7** | **Unit Testing & Verification**    | Add automated test suites for TOTP math, Webhooks, Cluster failover, RFC linter, and Analytics parser.         | Zero regressions across all 13 pillars (Exit Code 0).                      |
