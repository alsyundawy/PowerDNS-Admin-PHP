# BREAKDOWN — Technical Architecture & Component Analysis for v0.3.0

**Project:** PowerDNS-Admin-PHP
**Version Scope:** `v0.3.0` Enterprise Upgrade
**Author:** Harry Dertin Sutisna Alsyundawy ([@alsyundawy](https://github.com/alsyundawy))
**Auditor:** Principal Staff Engineer & Linux System Specialist
**Design Standard:** Strict Native PHP, Zero Framework, Zero Runtime Python/Node, 100% Backward Compatible

---

## 1. Deep Technical Component Breakdown

### 1.1 Native RFC 6238 TOTP Engine (`app/totp.php`)

To eliminate bloated external Composer packages (e.g. `robthree/twofactorauth` or `bacon/bacon-qr-code`) while maintaining 100% standard compliance with Google Authenticator, Authy, Apple Passwords, and 1Password, the TOTP engine is implemented entirely in native PHP.

#### Mathematical & Byte-Level Pipeline

1. **Base32 Encoding/Decoding (RFC 4648 §6):**
   - Base32 converts 5-bit chunks into an alphabet of 32 characters (`A-Z`, `2-7`).
   - In pure PHP, string bytes are read bitwise or via 8-bit to 5-bit lookup tables:
     $$\text{5-bit index} = (\text{byte} \gg \text{shift}) \land \text{0x1F}$$
2. **Time Step Counter Calculation:**
   - Standard 30-second time slice: $T_0 = 0$, $X = 30$.
   - Counter $C = \lfloor \frac{\text{time}()}{30} \rfloor$.
   - Counter is packed into an 8-byte (64-bit) big-endian binary string:

     ```php
     $timePacked = pack('J', $timeSlice); // 64-bit unsigned big-endian
     ```

3. **HMAC-SHA1 & Dynamic Binary Truncation:**
   - HMAC hash is computed: $H = \text{hash\_hmac}('sha1', \$timePacked, \$secretBinary, \text{true})$ (20 bytes).
   - The last byte's lowest 4 bits determine the dynamic offset:
     $$\text{offset} = \text{ord}(H[19]) \land 0\text{x0F}$$
   - A 4-byte big-endian integer is extracted from $H[\text{offset} \dots \text{offset}+3]$ and masked to 31 bits:
     $$\text{binaryCode} = ((\text{ord}(H[\text{offset}]) \land 0\text{x7F}) \ll 24) \lor ((\text{ord}(H[\text{offset}+1])) \ll 16) \lor ((\text{ord}(H[\text{offset}+2])) \ll 8) \lor (\text{ord}(H[\text{offset}+3]))$$
   - The 6-digit TOTP code is obtained via modulo $10^6$:
     $$\text{code} = \text{str\_pad}(\text{binaryCode} \pmod{10^6}, 6, '0', \text{STR\_PAD\_LEFT})$$
4. **Time Drift Window & Constant-Time Comparison:**
   - The verification checks $T \in \{C - 1, C, C + 1\}$ to allow for $\pm 30$ seconds of clock drift.
   - Verification uses `hash_equals()` to prevent timing attack vulnerabilities (CWE-208).

#### Vector SVG QR Code Engine

- Instead of calling external APIs (e.g., Google Chart API, which leaks private TOTP secrets to third parties) or requiring GD/Imagick image extensions, QR matrix data is encoded natively and rendered directly as an SVG vector element:
  `<svg viewBox="0 0 size size" ...><rect ...></svg>` or formatted as a secure data-URI:
  `data:image/svg+xml;utf8,<svg ...>`.

---

### 1.2 Multi-Server PowerDNS Node Clustering Engine (`app/PdnsCluster.php`)

Inspired by the cluster control plane in [alsyundawy/PHP-PDNSManager](https://github.com/alsyundawy/PHP-PDNSManager), this engine manages multiple PowerDNS Authoritative daemons across data centers.

#### Component Architecture

```text
                                  ┌────────────────────────┐
                                  │   Web Admin / Client   │
                                  └───────────┬────────────┘
                                              │ Request
                                              ▼
                                 ┌─────────────────────────┐
                                 │   app/PdnsCluster.php   │
                                 │   Active Server Router  │
                                 └────────────┬────────────┘
                                              │
                      ┌───────────────────────┼───────────────────────┐
                      ▼                       ▼                       ▼
            ┌───────────────────┐   ┌───────────────────┐   ┌───────────────────┐
            │  Node 1 (Primary) │   │ Node 2 (Secondary)│   │  Node 3 (Anycast) │
            │ http://10.0.1.10  │   │ http://10.0.2.10  │   │ http://10.0.3.10  │
            │   api-key-aes-gcm │   │   api-key-aes-gcm │   │   api-key-aes-gcm │
            └───────────────────┘   └───────────────────┘   └───────────────────┘
```

#### Non-Breaking Routing Logic

1. If `pdns_servers` contains configured records:
   - Route requests to `$_SESSION['active_pdns_server_id']`.
   - If not set in session, select the server flagged with `is_default = 1` or the first active row.
2. If `pdns_servers` is empty:
   - Transparently route to legacy credentials stored in `settings` (`pdns_api_url`, `pdns_api_key`), ensuring existing single-node installations continue without interruption.
3. Node Telemetry & Latency Ping:
   - A lightweight `curl_multi` or non-blocking cURL inspects `/api/v1/servers/localhost` and records connection latency in milliseconds (`latency_ms`).
   - If a node is unreachable, the system triggers the **Graceful Degradation Banner** (inspired by `ndash`), displaying actionable recovery buttons rather than an unhandled 500 error.

---

### 1.3 High-Performance In-Memory Caching Subsystem (`app/cache.php`)

DNS zones and statistics query operations can cause repetitive round-trips to the PowerDNS API. Adapted from [alsyundawy/OrbDNS-PDNSAdmin-PHP](https://github.com/alsyundawy/OrbDNS-PDNSAdmin-PHP), this subsystem implements a tiered cache.

#### Storage Tier Priority

1. **Tier 1: APCu Shared Memory (`apcu_*`):**
   - Sub-millisecond latency.
   - Zero network overhead.
   - Ideal for single-server or single-node FPM setups.
2. **Tier 2: Redis Socket / Network (`phpredis`):**
   - Centralized shared cache across multiple web workers or load-balanced web servers.
3. **Tier 3: Request-Level In-Memory Static Cache:**
   - Fallback if no extensions are installed. Prevents multiple API calls within a single HTTP request lifecycle.

#### Smart Cache Invalidation Matrix

| Mutation Action | Affected Cache Keys                                                   | Invalidation Method        |
| --------------- | --------------------------------------------------------------------- | -------------------------- |
| Create Zone     | `pdns:zones:server_*`, `pdns:stats:server_*`                          | Immediate cache deletion   |
| Delete Zone     | `pdns:zones:server_*`, `pdns:zone:<zone_name>`, `pdns:stats:server_*` | Immediate cache deletion   |
| Update RRsets   | `pdns:zone:<zone_name>`, `pdns:stats:server_*`                        | Immediate cache deletion   |
| Server Switch   | All request-scoped cache pointers                                     | Re-scoped to new server ID |

---

### 1.4 Cryptographic Webhook Dispatcher (`app/webhook_services.php`)

Enables real-time event-driven automation for external orchestration tools (Ansible, Terraform, GitHub Actions, custom DNS monitors).

#### Dispatch Pipeline & Security

1. **Event Capture:** Triggers fired upon `zone.created`, `zone.deleted`, `record.updated`.
2. **Payload Construction:**

   ```json
   {
     "event": "record.updated",
     "timestamp": 1728045600,
     "server": "ns1.primary",
     "zone": "example.com.",
     "operator": "admin",
     "ip": "192.168.1.100",
     "changes": {
       "rrsets_modified": 1
     }
   }
   ```

3. **Cryptographic Signature Generation:**
   $$\text{Signature} = \text{hash\_hmac}('sha256', \$payloadJson, \$webhookSecret)$$
   Sent via HTTP header: `X-PDNS-Signature: sha256=<hex_hash>`.
4. **Resilient Non-Blocking Delivery:**
   cURL execution configured with a strict 3-second timeout (`CURLOPT_TIMEOUT => 3`) so web UI response times are unaffected by slow webhook receivers.

---

### 1.5 Cross-Zone Bulk Record Operations

Managing large hosting environments requires mass search-and-replace capabilities.

#### Processing Steps

1. **Cross-Zone Indexing & Search:**
   - Queries PowerDNS API search endpoint (`/api/v1/servers/localhost/search-data?q=...`) or iterates active zones to locate matching `A`, `AAAA`, `CNAME`, or `TXT` records.
2. **Atomic Pre-Change Snapshot:**
   - For every zone scheduled for modification, automatically calls `saveZoneSnapshot()` to write a complete rollback state into `zone_snapshots`.
3. **Batch Patch Execution:**
   - Sends atomic PATCH payloads containing updated records to PowerDNS.
   - Logs detailed audit trails in `history`.

---

### 1.6 Zone RFC Compliance & Linting Engine (`app/zone_linter.php`)

Pre-flight static verification prevents operators from inadvertently introducing invalid DNS states into production:

| RFC Standard         | Validation Rule                                                              | Diagnostic Severity  | Remediation Recommendation                                                 |
| -------------------- | ---------------------------------------------------------------------------- | -------------------- | -------------------------------------------------------------------------- |
| **RFC 1912 / 2181**  | CNAME at Zone Apex (`example.com.`) alongside SOA/NS.                        | **CRITICAL WARNING** | Convert apex CNAME to native PowerDNS `ALIAS` record for CNAME flattening. |
| **RFC 1035 §3.3.11** | Delegated NS points to in-zone hostname without matching A/AAAA glue record. | **HIGH WARNING**     | Add glue A/AAAA record for `ns1.example.com.` inside `example.com.` zone.  |
| **RFC 2181 §10.3**   | MX record points to a CNAME rather than an A/AAAA hostname.                  | **MEDIUM WARNING**   | Direct MX exchange target to canonical hostname with an A/AAAA record.     |
| **RFC 1035**         | Dangling CNAME pointer (target points to non-existent host in same zone).    | **LOW WARNING**      | Verify target hostname exists or point to external FQDN with trailing dot. |
| **RFC 2317 / 3596**  | Forward A/AAAA record lacks corresponding PTR in managed reverse zones.      | **INFO**             | Use 1-Click "Create Reverse PTR" button to generate matching PTR record.   |

---

### 1.7 Advanced DNS Telemetry & Visual Analytics Architecture (`app/analytics.php`)

To provide actionable operational intelligence without depending on external heavy monitoring stacks (Prometheus, Grafana, or Datadog), this subsystem processes native PowerDNS daemon counters and ring buffers.

#### PowerDNS Ring Buffer Ingestion (`include_rings=true`)

PowerDNS maintains circular ring buffers in memory for recent query activities:

1. **`queries` Ring Buffer:** Array of `[name, count]` tuples recording the most frequently resolved domain names.
2. **`remotes` Ring Buffer:** Array of `[remote_ip, count]` tuples recording client/resolver IP addresses sending queries.
3. **`qtypes` Ring Buffer:** Array of `[type, count]` recording the distribution of DNS record types requested.

#### Mathematical Formulas & Gauges

1. **Packet Cache Efficiency Ratio:**
   $$\text{Hit Ratio} = \left(\frac{\text{packetcache-hit}}{\text{packetcache-hit} + \text{packetcache-miss}}\right) \times 100\%$$
2. **Transport Protocol Distribution:**
   $$\text{UDP Ratio} = \left(\frac{\text{udp-queries}}{\text{udp-queries} + \text{tcp-queries}}\right) \times 100\%$$
   $$\text{TCP Ratio} = \left(\frac{\text{tcp-queries}}{\text{udp-queries} + \text{tcp-queries}}\right) \times 100\%$$
3. **Server Error Answer Rate:**
   $$\text{ServFail Rate} = \left(\frac{\text{servfail-answers}}{\text{udp-answers} + \text{tcp-answers}}\right) \times 100\%$$

#### Zero-CDN Native SVG Vector Gauge Engine

To maintain the strict Zero-CDN offline invariant, all visual charts are rendered directly by PHP as scalable vector graphics:

- **Circular Donut Gauge:**
  Rendered using SVG `<circle>` primitives with `stroke-dasharray="251.2"` ($2 \pi r$ where $r=40$) and `stroke-dashoffset`:
  $$\text{offset} = 251.2 \times \left(1 - \frac{\text{ratio}}{100}\right)$$
- **Horizontal Ranked Bar Chart:**
  Rendered using CSS Grid and proportional SVG `<rect>` elements styled dynamically via CSS custom properties (`--bar-width: X%`), eliminating heavy external charting libraries.

#### Privacy & Anonymization Protection

- **GDPR / Privacy Mode:** When enabled in Settings, remote client IP addresses extracted from the `remotes` ring buffer are automatically masked (e.g. `203.0.113.45` &rarr; `203.0.113.0/24`, `2001:db8:85a3::8a2e` &rarr; `2001:db8:85a3::/48`) before rendering or exporting.

#### In-Memory Telemetry Caching

- Ring buffer data is cached in APCu or Redis for 15 seconds (`pdns:telemetry:server_<id>`). This prevents concurrent administrator dashboard visits from flooding the PowerDNS control socket.

---

## 2. Database Delta Schema (v0.3.0)

All migrations are 100% additive, non-destructive, and idempotent:

```sql
-- 1. PowerDNS Multi-Server Clustering
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

-- 2. Cryptographic Webhooks
CREATE TABLE IF NOT EXISTS webhooks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(128) NOT NULL,
  url VARCHAR(512) NOT NULL,
  secret VARCHAR(128) NOT NULL,
  events VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_status_code INT NULL,
  last_error VARCHAR(255) NULL,
  last_triggered_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Granular DynDNS Multi-Token Gateway
CREATE TABLE IF NOT EXISTS dyndns_tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(128) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  hostname VARCHAR(255) NOT NULL,
  record_type ENUM('A','AAAA','BOTH') NOT NULL DEFAULT 'BOTH',
  user_id INT UNSIGNED NOT NULL,
  last_ip VARCHAR(64) NULL,
  last_update_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dyndns_token (token_hash),
  KEY idx_dyndns_host (hostname),
  CONSTRAINT fk_dyndns_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Native 2FA Two-Factor Authentication Columns
-- (Executed safely via procedural information_schema checks in bootstrap.php)
ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) NULL AFTER password_hash;
ALTER TABLE users ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret;
ALTER TABLE users ADD COLUMN totp_backup_codes TEXT NULL AFTER totp_enabled;
```

---

## 3. Blast Radius & Security Risk Analysis

| Component                   | Potential Risk                                           | Mitigation & Guardrail                                                                                                               |
| --------------------------- | -------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| **Multi-Server Clustering** | Access to wrong PowerDNS node if session corrupted.      | Strict verification of `server_id`; fail-closed if server not found; admin-only permission to add/edit cluster nodes.                |
| **Native 2FA TOTP**         | User locked out if phone lost.                           | 10 pre-generated cryptographically secure single-use recovery scratch codes; admin can reset TOTP from Users panel.                  |
| **In-Memory Caching**       | Stale zone data served after external updates.           | Smart invalidation on all mutations; "Purge Cache" manual button in settings; short default TTL (30-60s).                            |
| **Webhooks**                | SSRF (CWE-918) via malicious webhook URLs.               | Admin-only configuration; URL validation rejecting private IPs unless explicitly permitted in settings; 3-second cURL timeout.       |
| **Bulk Record Operations**  | Erroneous mass edit destroying DNS records across zones. | Mandatory pre-change snapshot generated in `zone_snapshots` for every modified zone; two-step confirmation modal with preview.       |
| **Telemetry & Analytics**   | Information disclosure or socket starvation under load.  | Ring buffer responses cached for 15s in memory; client IP masking toggle; read-only endpoints restricted to authenticated operators. |

---

## 4. Verification & Testing Strategy

Each new capability will be accompanied by dedicated unit tests:

1. `tests/test_totp.php`: Validates RFC 6238 against official test vectors (SHA-1, 8 time steps), Base32 round-trip, and scratch code verification.
2. `tests/test_cluster.php`: Validates server switching, fallback to legacy settings, and credential decryption.
3. `tests/test_cache.php`: Validates get/set/delete across APCu and fallback drivers, TTL expiry, and tag invalidation.
4. `tests/test_webhooks.php`: Validates payload formatting, HMAC-SHA256 signature verification, and error handling.
5. `tests/test_linter.php`: Validates all 5 RFC rules against mock zone RRsets.
6. `tests/test_analytics.php`: Validates ring buffer decoding, packetcache ratio math, and IP anonymization masking.
