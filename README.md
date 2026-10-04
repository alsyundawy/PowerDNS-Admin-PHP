# PowerDNS-Admin-PHP — Enterprise Authoritative PowerDNS Control Plane

<p align="center">
  <a href="https://github.com/alsyundawy/PowerDNS-Admin-PHP">
    <img src="assets/powerdns-admin-php-banner.jpg" alt="PowerDNS-Admin-PHP Enterprise Control Plane Banner" width="100%">
  </a>
</p>

[![Release](https://img.shields.io/badge/Release-v0.3.0-007ACC?style=for-the-badge&logo=github)](https://github.com/alsyundawy/PowerDNS-Admin-PHP/releases)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%20%7C%208.3%20%7C%208.4%20%7C%208.5-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![PowerDNS API v1](https://img.shields.io/badge/PowerDNS-Authoritative%20API%20v1-D9381E?style=for-the-badge&logo=internetcomputer&logoColor=white)](https://www.powerdns.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge)](LICENSE)
[![Quality Gates](https://img.shields.io/badge/Quality%20Gates-PHPStan%20L5%20%7C%20Psalm%20L4%20%7C%20PSR--12-brightgreen?style=for-the-badge&logo=checkmarx)](https://github.com/alsyundawy/PowerDNS-Admin-PHP)
[![Zero CDN](https://img.shields.io/badge/Assets-100%25%20Offline%20Local-blue?style=for-the-badge&logo=bootstrap)](https://getbootstrap.com/)
[![Database](https://img.shields.io/badge/Database-MySQL%20%7C%20MariaDB%20utf8mb4-003B57?style=for-the-badge&logo=mariadb&logoColor=white)](https://mariadb.org/)
[![Donate PayPal](https://img.shields.io/badge/Donate-PayPal-00457C?style=for-the-badge&logo=paypal&logoColor=white)](https://www.paypal.me/alsyundawy)

> **Enterprise-grade, security-hardened Web GUI and automation engine for PowerDNS Authoritative Server.
> Engineered with pure native PHP & PDO, sub-millisecond bootstrap, zero external CDN dependencies, dual dark/light theming
> inspired by Visual Subnet Calculator, MariaDB/MySQL metadata persistence, and direct PowerDNS HTTP API v1 integration.**
>
> Designed, engineered, and maintained by
> **[`HARRY DERTIN SUTISNA ALSYUNDAWY (@alsyundawy)`](https://github.com/alsyundawy)** —
> Built for mission-critical DNS operations.
>
> 📦 **[`GitHub Releases`](https://github.com/alsyundawy/PowerDNS-Admin-PHP/releases)** &nbsp;|&nbsp;
> 📖 **[`Installation Guide`](#-installation--setup-guide)** &nbsp;|&nbsp;
> 🛠️ **[`Architecture & Request Pipeline`](#️-architecture--request-pipeline)** &nbsp;|&nbsp;
> 🏛️ **[`Architecture & Notes`](DOCNOTE.md)** &nbsp;|&nbsp;
> 📜 **[`Full Changelog`](CHANGELOG.md)** &nbsp;|&nbsp;
> 💖 **[`Support via PayPal`](https://www.paypal.me/alsyundawy)** &nbsp;|&nbsp;
> 🇮🇩 **[`QRIS Donation`](#-support--donation)**

---

## 🧭 Navigation

- [Overview](#-overview)
- [Why This Modernized Edition?](#-why-this-modernized-edition)
- [Key Features](#-key-features)
- [Architecture & Request Pipeline](#️-architecture--request-pipeline)
- [DNS Record Types & Authoritative Engine](#-dns-record-types--authoritative-engine)
- [Visual Subnet Calculator Design & Mobile Responsive System](#-visual-subnet-calculator-design--mobile-responsive-system)
- [Cross-OS Production Deployment & Migration](#-cross-os-production-deployment--migration)
- [Installation & Setup Guide](#-installation--setup-guide)
- [Usage & Quick Start](#usage--quick-start)
- [Configuration Reference](#️-configuration-reference)
- [REST API & Automation Layer](#-rest-api--automation-layer)
- [Quality Assurance & Verification Gates](#-quality-assurance--verification-gates)
- [Engineering Standards & Invariants](#-engineering-standards--invariants)
- [Security & Content Safety](#-security--content-safety)
- [Project Directory Structure](#-project-directory-structure)
- [Contributing](#-contributing)
- [Maintainer & Contact](#-maintainer--contact)
- [Support & Donation](#-support--donation)
- [License](#-license)

---

## 🌟 Overview

**PowerDNS-Admin-PHP** is a high-performance, web-based authoritative DNS management control plane engineered
specifically for system administrators, hosting providers, ISP network engineers, and DevOps teams.

Managing PowerDNS zones via direct SQL queries or heavyweight control panels burdened with complex Python/Flask
virtual environments, NPM build steps, or external daemon microservices introduces significant operational overhead,
high memory consumption, and security attack vectors. **PowerDNS-Admin-PHP** solves this by providing a clean,
lightweight, native PHP PDO web application communicating directly with PowerDNS Authoritative via its official
HTTP API v1.

Operating on Debian, Ubuntu, Rocky Linux, AlmaLinux, or CentOS, PowerDNS-Admin-PHP delivers sub-millisecond local
page rendering, atomic RRSet diff-patch updates, multi-tenant role-based access control (RBAC), end-to-end DNSSEC
management, and complete operational autonomy with zero runtime external CDN dependencies.

---

## 🚀 Why This Modernized Edition?

This edition (**v0.3.0**) represents a clean-slate architectural, security, accessibility, and visual overhaul of
PowerDNS administration:

### 🛡️ 1. Zero-CDN Offline Architecture & Content Security

- **100% Local Distribution**: Ships with production bundles of **Bootstrap 5.3**, **jQuery 3.7**, and native SVG
  icons located in `public/assets/vendor/`.
- **Air-Gapped & Offline Ready**: Runs reliably in isolated server networks, air-gapped data centers, and private
  intranets without third-party CDN latency, outages, or telemetry tracking.
- **Strict Content Security Policy (CSP)**: HTTP headers enforce `default-src 'self'`, `script-src 'self'`,
  `style-src 'self' 'unsafe-inline'`, and `img-src 'self' data:` with zero third-party external origins permitted.

### ⚡ 2. Single Source of Truth (Pure Authoritative Integration)

- **Zero Database Duplication**: DNS resource records are **never duplicated** in the panel database. PowerDNS
  remains the authoritative source of truth.
- **Live State Sync**: The panel database persists only user accounts, tenant groups, zone templates, audit logs, and
  hashed API keys. Zone data and RRsets are queried and updated in real-time through the PowerDNS HTTP API v1.
- **Atomic RRSet Diff Engine**: When modifying a zone, the panel computes the exact mathematical diff between the
  existing RRsets and requested modifications, transmitting only the mutated sets to PowerDNS in a single atomic PATCH payload.

### 🎨 3. Visual Subnet Calculator Theming & Mobile Notch Optimization

- **Ergonomic Palette**: Inspired by the dark/light design system of
  [Visual Subnet Calculator](https://alsyundawy.github.io/visualsubnetcalc).
- **Dual Synchronization**: Instant theme reactivity syncing both `data-bs-theme` and local state across
  `dark` and `light` preferences with smooth transitions and high contrast.
- **Notch & Cutout Safe**: Implements `viewport-fit=cover`, CSS `env(safe-area-inset-*)`, and modern `100dvh`
  viewport units to prevent content clipping on mobile devices (including iPhone, Xiaomi, Redmi, Poco, and Android tablets).

### 🔒 4. Enterprise Security & Defense-in-Depth

- **AES-256-GCM Encrypted Credentials**: PowerDNS API keys and sensitive tokens are encrypted at rest using
  `aes-256-gcm` authenticated encryption with cryptographically random initialization vectors (IV).
- **Argon2id Password Hashes**: Passwords are saved with `password_hash($password, PASSWORD_ARGON2ID)` using secure
  memory and time cost factors.
- **Dual-Axis Rate Limiting**: Independent IP and username rate limiters on authentication endpoints safeguard against
  distributed brute-force attacks.
- **Cryptographic CSRF Protection**: Session-bound cryptographic tokens validate every mutating `POST` request with
  automatic token rotation.

### 🗄️ 5. Zero-Framework Sub-Millisecond Native Architecture

- **Ultra-Low Memory Footprint**: Uses $< 2\text{ MB}$ RAM per HTTP request without the multi-megabyte overhead of
  heavyweight frameworks.
- **Sub-Millisecond Bootstrap**: Boots in $< 1\text{ ms}$, ensuring lightning-fast dashboard navigation even on
  entry-level VPS instances ($512\text{ MB}$ RAM).
- **Prepared PDO Statements**: Every query utilizes parameterized PDO statements, completely eliminating SQL injection risks.

---

## 🎯 Key Features

| Capability Area                  | Highlights & Implementations                                                                                                                                  |
| :------------------------------- | :------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Zone Management**              | Forward and reverse zones, supporting `Native`, `Master`, `Slave`, `Producer`, and `Consumer` kinds with configurable `SOA-EDIT-API` metadata.                |
| **Smart RRSet Editor**           | Interactive visual editor with RFC syntax validation for `A`, `AAAA`, `CNAME`, `MX`, `TXT`, `NS`, `PTR`, `SRV`, `CAA`, `HTTPS`, `SVCB`, `DS`, etc.            |
| **Two-Factor Auth (2FA)**        | Pure PHP RFC 6238 TOTP engine, native vector SVG QR code generator (Zero GD/Imagick), and 10 single-use emergency backup recovery codes.                      |
| **PowerDNS Clustering**          | Multi-server daemon management, AES-256-GCM encrypted credentials, active session node routing, and live latency ping telemetry.                              |
| **In-Memory Cache (APCu)**       | High-performance APCu shared memory adapter with tag-based invalidation, reducing repeated zone reads to < 0.2ms.                                             |
| **Cryptographic Webhooks**       | Event-driven HTTP POST notifications (`zone.created`, `zone.deleted`, `record.updated`) signed with HMAC-SHA256 (`X-PDNS-Signature`).                         |
| **Bulk Record Operations**       | Cross-zone mass search & replace for IPs and FQDNs across all authoritative zones with automated safety snapshots for 1-click rollback.                       |
| **Zone RFC Linting Engine**      | Automated RFC 1035, RFC 1912, and RFC 2181 compliance audits (Apex CNAME conflicts, missing glue records, MX-to-CNAME detection).                             |
| **Telemetry & Visual Analytics** | Real-time ring buffer decoders (`queries`, `remotes`), packet cache hit ratio donut gauge, transport split bar, and streaming JSON telemetry export.          |
| **Subnet rDNS Wizard**           | Interactive IPv4 `/24` and IPv6 `/64` (RFC 3596 nibble format) subnet calculator, batch PTR generator, and automatic forward-to-reverse PTR sync.             |
| **Zone History & Rollback**      | Automatic revision snapshots on every modification, visual record diff inspection, and 1-click atomic rollback to any past zone state.                        |
| **Native BIND RFC 1035**         | Native RFC 1035 zone file parser (`$ORIGIN`, `$TTL`, multiline parenthesized SOA) supporting file upload / paste and 1-click `.zone` BIND export.             |
| **Modern DNSSEC Suite**          | Cutting-edge **Ed25519 (Alg 15)** and **ECDSA (Alg 13/14)** signing with automated parent delegation bootstrapping (**RFC 7344 CDS / CDNSKEY**).              |
| **Dynamic DNS (DynDNS 2)**       | Standard `/nic/update` HTTP endpoint compatible with routers, ddclient, Mikrotik, and IoT devices using Basic Auth or API Key tokens.                         |
| **Zone Templates**               | Standardized templates (web hosting, mail clusters, CDN endpoints) with `[ZONE]` macro expansion for rapid multi-zone rollout.                                |
| **DNS Operations Engine**        | Instant manual `NOTIFY` propagation to secondary nameservers and on-demand `AXFR Retrieve` zone synchronization directly from the UI.                         |
| **Multi-Tenancy & RBAC**         | Fine-grained role hierarchy (`admin`, `operator`, `user`). Users can be assigned to multi-user Accounts or granted direct per-zone `Read`/`Edit` permissions. |
| **Global Instant Search**        | Sub-second fuzzy search across zone names, record comments, and RRSet contents powered by the PowerDNS `/search-data` API endpoint.                           |
| **Tamper-Evident Audit Trail**   | Comprehensive logging of authentication events, zone creation, record mutations, and role elevations with IP addresses and user agents.                       |
| **Live Telemetry Dashboard**     | Real-time server telemetry: UDP/TCP query volume, packetcache hit/miss ratio, recursion statistics, and operational load metrics.                             |
| **Database & Zone Backup**       | 1-Click MySQL metadata SQL dump/restore with query sanitation, settings JSON export, and full PowerDNS zones API snapshot suite.                              |
| **User Profile & Avatar**        | Self-service profile management, Argon2id password changes, secure image avatar upload, and universal UI avatar integration.                                  |
| **Custom Branding & GUI**        | Customizable panel branding (Logo upload/URL, custom App Name, custom footer text) and integrated dashboard quick controls.                                   |
| **Advanced Network Tools**       | IPCalc (IPv4/IPv6 bitwise), memory-safe IPv6 Subnet Splitter (up to 65k subnets), WHOIS/RDAP client (RFC 9082), and native DNS lookup resolver.               |
| **Panel REST API**               | External token-authenticated REST API (`X-API-Key`) for automation via Ansible, Terraform, ACME Let's Encrypt bots, and custom scripts.                       |

---

## 🏗️ Architecture & Request Pipeline

PowerDNS-Admin-PHP follows an enterprise, decoupled architecture with strict boundaries between presentation, security middleware, domain subsystems, caching, and upstream PowerDNS daemons:

```mermaid
flowchart TB
    subgraph Clients["1. Ingress & Client Access Layer"]
        Browser["Desktop & Mobile Web Browsers<br/>(OLED Dark / Light, Safe Area Adaptive)"]
        RESTClient["CI/CD Automation, Terraform & Let's Encrypt<br/>(HTTPS Bearer / X-API-Key)"]
        DynClient["DynDNS Routers & Mikrotik / ddclient<br/>(HTTP Basic Auth / Token)"]
    end

    subgraph SecurityGateway["2. Security Gateway & Reverse Proxy"]
        Nginx["Nginx / Apache Reverse Proxy<br/>(TLS 1.3 Termination, HSTS, Strict CSP, X-Frame-Options)"]
        RateLimiter["Dual-Axis Rate Limiting Gate<br/>(Per-IP Sliding Window + Per-Username Brute Force Guard)"]
        StaticAssets["Local Offline Static Assets<br/>(Bootstrap 5.3, Font Awesome 6.7.2, App CSS/JS - Zero CDN)"]
    end

    subgraph AppKernel["3. Application Core Kernel (Native PHP 8.1 - 8.5)"]
        FrontController["public/index.php<br/>(Sub-millisecond Bootstrap, Zero Framework Overhead)"]
        AuthModule["Session, RBAC & 2FA Engine<br/>(Argon2id, RFC 6238 TOTP, Single-use Scratch Codes)"]
        CSRFProtection["Cryptographic CSRF Validator<br/>(Session-bound Token Rotation on Mutating POSTs)"]
        Router["Declarative Fast Router<br/>(Dynamic Route Dispatch & Parameter Extraction)"]
    end

    subgraph DomainServices["4. Enterprise Domain Subsystems"]
        ClusterManager["Multi-Server Cluster Engine<br/>(app/PdnsCluster.php - Active Node Routing & Latency Ping)"]
        CacheAdapter["In-Memory Cache Adapter<br/>(app/cache.php - APCu Shared Memory / Request Cache)"]
        RecordManager["DNS Operations & Validation<br/>(app/services.php - 31 Record Types, Atomic RRSet Diff)"]
        ZoneLinter["RFC Compliance & Linting Engine<br/>(app/zone_linter.php - Apex CNAME, Glue, MX CNAME)"]
        AnalyticsEngine["Telemetry & SVG Visualizer<br/>(app/analytics.php - Ring Buffers, Donut Gauges, Top Slices)"]
        WebhookDispatcher["Cryptographic Webhook Dispatcher<br/>(app/webhook_services.php - HMAC-SHA256 Signed JSON)"]
    end

    subgraph UpstreamStorage["5. Upstream Persistence & DNS Infrastructure"]
        MetadataDB[("MySQL 8.0+ / MariaDB 10.5+<br/>(Users, Clusters, Webhooks, DynDNS, Audit Trails, Snapshots)")]
        PowerDNSAPI["PowerDNS Authoritative REST API v1 (:8081)<br/>(pdns_server daemons, gmysql / lmdb / BIND backends)"]
    end

    Browser -->|"HTTPS"| Nginx
    RESTClient -->|"HTTPS X-API-Key"| Nginx
    DynClient -->|"HTTPS /nic/update"| Nginx
    Nginx -->|"Local Files"| StaticAssets
    Nginx -->|"FastCGI (PHP-FPM socket)"| FrontController
    FrontController --> RateLimiter
    RateLimiter --> CSRFProtection
    CSRFProtection --> AuthModule
    AuthModule --> Router
    Router --> DomainServices
    DomainServices <-->|"Sub-millisecond Read/Write"| CacheAdapter
    DomainServices <-->|"PDO Prepared SQL"| MetadataDB
    DomainServices -->|"HMAC-SHA256 Events"| WebhookDispatcher
    DomainServices <-->|"HTTP API v1 (PdnsClient)"| PowerDNSAPI
```

### 1. Architectural Tiers & Component Responsibilities

1. **Tier 1 — Ingress & Security Gateway (`Nginx / Apache / Reverse Proxy`):**
   - **TLS 1.3 Termination:** Enforces modern cipher suites (ChaCha20-Poly1305, AES-256-GCM) and HTTP Strict Transport Security (`HSTS: max-age=63072000; includeSubDomains; preload`).
   - **Defense-in-Depth Headers:** Injects strict Content Security Policy (`CSP`), `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, and `Referrer-Policy: strict-origin-when-cross-origin`.
   - **Static Asset Bypass:** Directly offloads all vendor bundles (`/assets/vendor/`) and public static files, guaranteeing zero PHP process invocation for static content.

2. **Tier 2 — Ingress Security Middleware & Application Kernel (`public/index.php`, `app/bootstrap.php`):**
   - **Sub-Millisecond Bootstrap:** Zero-framework, native PHP 8.1–8.5 initialization completed in $< 1\text{ ms}$.
   - **Dual-Axis Rate Limiting:** Sliding-window rate limiters independently track per-IP and per-account request velocity in shared storage, throttling brute-force attempts without locking out legitimate network tenants.
   - **Cryptographic CSRF Guard:** Enforces session-bound HMAC-verified anti-forgery tokens on all mutating HTTP methods (`POST`, `PUT`, `DELETE`), rotating tokens per state mutation.
   - **Session & Multi-Factor Quarantine:** Verifies session authenticity and isolates unverified 2FA sessions to `/login/2fa` until valid RFC 6238 TOTP or single-use recovery tokens are presented.

3. **Tier 3 — Enterprise Domain Subsystems (`app/`):**
   - **Dynamic Cluster Router (`app/PdnsCluster.php`):** Resolves the target PowerDNS daemon node from session state or query context, decrypts stored credentials via `AES-256-GCM`, and dynamically configures the API client.
   - **In-Memory Cache Subsystem (`app/cache.php`):** High-throughput APCu shared memory adapter with atomic prefix-based invalidation. Caches zone catalogs, server metrics, and static configurations to eliminate repetitive upstream API overhead.
   - **Authoritative DNS Client (`app/PdnsClient.php`):** Pure native cURL wrapper implementing the complete PowerDNS Authoritative HTTP API v1 with persistent keep-alive connections, circuit-breaking timeouts, and structured error propagation.
   - **Zone RFC Compliance & Linter Engine (`app/zone_linter.php`):** Pre-execution linter evaluating zone records against RFC 1035, RFC 1912, and RFC 2181 before changes are committed to the network.
   - **Cryptographic Webhook Engine (`app/webhook_services.php`):** Asynchronous event dispatcher broadcasting cryptographically signed JSON payloads (`X-PDNS-Signature: sha256=...`) to subscribed endpoints.
   - **DNS Telemetry & Vector Analytics Engine (`app/analytics.php`):** Streams raw ring-buffer metrics from PowerDNS daemons, computing cache hit rates, protocol distribution, and top queries into native SVG vector graphs.

4. **Tier 4 — Persistence & Metadata Storage (`MySQL 8.0+ / MariaDB 10.5+`):**
   - Stores non-DNS metadata: user credentials, role-based access controls (RBAC), multi-tenant account mappings, cluster node endpoints, encrypted API secrets, webhook subscriptions, and immutable zone revision snapshots.
   - **100% Prepared Statements:** All database interactions utilize parameterized PDO queries with strict data types, eliminating SQL injection attack vectors.

5. **Tier 5 — Authoritative DNS Infrastructure (`PowerDNS Authoritative Server v1`):**
   - Remains the **exclusive, single source of truth** for all DNS records, zones, metadata, and cryptographic keys.
   - Backed by native PowerDNS storage engines (gmysql, gpgsql, lmdb, or BIND backend) with automated DNSSEC signing and AXFR replication.

---

### 2. Detailed Request-Response Lifecycle

```text
[Client Request]
       │
       ▼
[Nginx / TLS 1.3] ──(Static /assets/*)──► [Local Filesystem (200 OK)]
       │
       ▼ (FastCGI unix socket)
[public/index.php]
       │
       ├──► [app/bootstrap.php] (declare(strict_types=1); Session start; PDO init)
       ├──► [RateLimiter::check()] (IP & Username sliding-window guard)
       ├──► [CSRF::verify()] (Validate anti-forgery token for mutating POSTs)
       ├──► [Auth & 2FA Guard] (Validate session; enforce TOTP quarantine)
       ├──► [PdnsCluster::getActiveClient()] (Resolve node & decrypt credentials)
       │
       ▼
[Router Dispatch] ──► [Handler Action (e.g. handleZoneSave)]
       │
       ├──► [AppCache::get()] (Check in-memory cache for zone metadata)
       ├──► [ZoneLinter::lintZone()] (Audit RFC 1035/1912/2181 compliance)
       ├──► [services::computeZoneDiff()] (Calculate atomic REPLACE/DELETE actions)
       ├──► [SnapshotEngine::capture()] (Save immutable JSON rollback state)
       │
       ▼
[PdnsClient::patchZone()] ──► [PowerDNS HTTP API v1] (Atomic PATCH commit)
       │
       ├──► [AppCache::invalidateZone()] (Purge cached RRsets)
       ├──► [AuditLog::record()] (Record tamper-evident audit entry)
       └──► [WebhookDispatcher::dispatch()] (Broadcast HMAC-SHA256 signed event)
       │
       ▼
[HTML5 / JSON Response] (Rendered with CSP & security headers in < 5ms)
```

1. **Ingress & TLS Termination:** Requests arrive at the reverse proxy (Nginx or Apache) where TLS 1.3 is terminated and strict HTTP response security headers are injected (`Content-Security-Policy`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`). Static vendor assets (`/assets/vendor/`) are served directly without invoking PHP.
2. **Kernel Bootstrap & Sanitization:** The FastCGI front controller (`public/index.php`) loads `app/bootstrap.php` in $<1\text{ ms}$. Strict typing (`declare(strict_types=1);`) is enforced, database PDO connections are initialized with persistent error mode `ERRMODE_EXCEPTION`, and dual-axis rate limiters inspect client IPs and credentials.
3. **Authentication & Multi-Factor Security:** For authenticated routes, sessions are validated. If Two-Factor Authentication (2FA) is enabled for the account, requests are quarantined to `/login/2fa` until a valid RFC 6238 time-based token or emergency single-use scratch code is verified.
4. **Dynamic Cluster Routing:** `app/PdnsCluster.php` resolves the currently active PowerDNS server instance from the session. Decrypted credentials (AES-256-GCM) configure `PdnsClient` on-the-fly, directing upstream API operations to the designated node cluster.
5. **Sub-millisecond In-Memory Caching:** Zone listings and server statistics queries first probe `AppCache` (APCu shared memory). Cache hits return in $<0.2\text{ ms}$ without consuming upstream PowerDNS API cycles or socket handles.
6. **Domain Validation & RFC Compliance:** Mutating operations pass through strict RFC syntax validators (`app/services.php`) and the Zone Linter engine (`app/zone_linter.php`), detecting Apex CNAME violations, missing glue records, and dangling aliases before any payload reaches PowerDNS.
7. **Atomic Diff & Snapshot Rollback:** Changes are converted into atomic `REPLACE`/`DELETE` RRSet structures. Prior to mutation, an immutable JSON snapshot of the zone state is captured in MySQL/MariaDB, enabling 1-click instant rollback.
8. **Asynchronous Webhook & Audit Dispatch:** Successful mutations invalidate the local cache (`AppCache::invalidateZone()`), record a tamper-evident audit log in `audit_logs`, and trigger HMAC-SHA256 signed HTTP POST notifications to all subscribed third-party webhook endpoints (`app/webhook_services.php`).

---

### 3. Mathematical State Invariants & Atomic Diff Engine

Unlike legacy control panels that truncate and rebuild entire zone files, PowerDNS-Admin-PHP computes the minimal mathematical difference between the current authoritative state $S_{\text{current}}$ and the requested state $S_{\text{desired}}$:

$$\Delta \text{RRSet} = (S_{\text{desired}} \setminus S_{\text{current}}) \cup (S_{\text{current}} \setminus S_{\text{desired}})$$

For each modified name-type pair $(n, t)$:
1. If $(n, t) \in S_{\text{current}} \land (n, t) \notin S_{\text{desired}}$, an atomic `DELETE` instruction is generated:
   $$\text{Action}_{\text{del}} = \left\{ \text{"action"}: \text{"DELETE"}, \text{"name"}: n, \text{"type"}: t \right\}$$
2. If $(n, t) \in S_{\text{desired}}$, an atomic `REPLACE` instruction is generated with the desired TTL and record set:
   $$\text{Action}_{\text{repl}} = \left\{ \text{"action"}: \text{"REPLACE"}, \text{"name"}: n, \text{"type"}: t, \text{"ttl"}: \tau, \text{"records"}: R_{(n, t)} \right\}$$

All generated operations are merged into a single atomic payload `{"rrsets": [ ... ]}` and submitted via a single HTTP `PATCH` transaction. PowerDNS executes this payload atomically in its underlying relational or LMDB backend, guaranteeing that DNS queries during updates never observe partial or inconsistent zone states.

---

### 4. Concurrency, I/O Model & Memory Boundaries

- **Stateless Worker Model:** Built entirely on the stateless PHP-FPM FastCGI architecture. Each request executes in complete process isolation, guaranteeing zero memory leakage across requests and eliminating thread-safety concerns.
- **Shared Memory Cache (APCu):** When APCu is enabled, shared memory segments are read and written using low-overhead native C-level system calls with zero serialization penalty for scalar structures.
- **Strict Memory Quotas:** Standard operations run with a peak memory allocation of $< 2\text{ MB}$. Bulk zone operations, BIND imports, and subnet calculations execute within an isolated $256\text{ MB}$ ceiling with streaming iteration to prevent buffer bloat.
- **Upstream Resilience & Circuit Breaking:** All HTTP API socket interactions with PowerDNS daemons enforce explicit connect timeouts ($2.0\text{ s}$) and read timeouts ($5.0\text{ s}$). Upstream daemon stalls or network partitions gracefully trigger structured exceptions without tying up PHP worker slots.

---

### 5. Security Boundaries & Threat Mitigation Matrix

| Layer / Boundary | Threat Vector Addressed | Defensive Mechanism & Invariant |
| :--- | :--- | :--- |
| **Ingress Proxy** | Man-in-the-Middle (MitM), Clickjacking | TLS 1.3, HSTS (`preload`), `X-Frame-Options: SAMEORIGIN`, strict `CSP` |
| **Authentication** | Brute-force & Credential Stuffing | Dual-axis sliding-window rate limiting; Argon2id (`memory=64MB, time=4`) |
| **Session & MFA** | Session Hijacking & Stolen Credentials | `HttpOnly`, `SameSite=Lax`, `Secure` session cookies; RFC 6238 TOTP |
| **Mutating HTTP** | Cross-Site Request Forgery (CSRF) | Session-bound cryptographic tokens with per-mutation rotation |
| **Persistence** | SQL Injection (SQLi) | 100% Parameterized PDO prepared statements; zero dynamic concatenation |
| **Secrets at Rest** | Database Compromise / Data Leakage | PowerDNS API keys encrypted via `AES-256-GCM` with dynamic IV vectors |
| **Output Encoding**| Cross-Site Scripting (Stored/Reflected XSS) | Strict context-aware HTML escaping (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`) |
| **Third-Party I/O** | Webhook Tampering & Forgery | SHA256 HMAC signature digest (`X-PDNS-Signature`) per HTTP delivery |

---

## 📊 DNS Record Types & Authoritative Engine

PowerDNS-Admin-PHP validates and formats all standard authoritative DNS Resource Record Sets:

| Record Type      | Description                            | RFC Standard       | Syntax Validation & Format Specs                                     |
| :--------------- | :------------------------------------- | :----------------- | :------------------------------------------------------------------- |
| **`A`**          | IPv4 Host Address                      | RFC 1035           | Dotted-decimal format: `0.0.0.0` – `255.255.255.255`                 |
| **`AAAA`**       | IPv6 Host Address                      | RFC 3596           | Standard compressed or uncompressed RFC 4291 IPv6                    |
| **`CNAME`**      | Canonical Name (Alias)                 | RFC 1035           | Fully Qualified Domain Name (FQDN) ending with a trailing dot        |
| **`ALIAS`**      | Zone Apex CNAME Flattening (Pseudo-RR) | PowerDNS Native    | Target FQDN synthesized into A/AAAA by PowerDNS Authoritative engine |
| **`DNAME`**      | Delegation Name (Subtree Redirection)  | RFC 6672           | Target domain name FQDN redirecting all descendants                  |
| **`MX`**         | Mail Exchange Server                   | RFC 1035, RFC 7505 | Priority integer (`0–65535`) followed by mail exchanger FQDN         |
| **`NS`**         | Authoritative Name Server              | RFC 1035           | Authoritative nameserver FQDN ending with a trailing dot             |
| **`TXT`**        | Text Annotations (SPF, DKIM, DMARC)    | RFC 1464, RFC 7208 | Character string enclosed in quotes, automatic multiline escape      |
| **`PTR`**        | Pointer Record (Reverse DNS)           | RFC 1035           | Target host FQDN ending with a trailing dot                          |
| **`SOA`**        | Start of Authority                     | RFC 1035, RFC 2181 | Primary NS, contact email, serial, refresh, retry, expire, TTL       |
| **`SRV`**        | Service Location Record                | RFC 2782           | Priority, weight, port (`1–65535`), and target hostname              |
| **`CAA`**        | Certification Authority Authorization  | RFC 6844, RFC 8659 | Flag byte, tag (`issue`, `issuewild`, `iodef`), CA domain            |
| **`HTTPS`**      | HTTPS Binding & Parameter Hints        | RFC 9460           | Priority, target name, and optional parameters (e.g. `alpn=h3,h2`)   |
| **`SVCB`**       | Service Binding Generic Record         | RFC 9460           | Priority, target name, and optional service parameters               |
| **`DS`**         | Delegation Signer (DNSSEC Parent)      | RFC 4034           | Key tag, algorithm, digest type, and cryptographic hex digest        |
| **`CDS`**        | Child DS (Automated Parent Trust)      | RFC 7344           | Key tag, algorithm, digest type, and hex digest for auto parent sync |
| **`DNSKEY`**     | DNSSEC Public Key Record               | RFC 4034           | Flags, protocol, algorithm, and base64 public key material           |
| **`CDNSKEY`**    | Child DNSKEY (RFC 7344 Auto Trust)     | RFC 7344           | Flags, protocol, algorithm, and base64 public key material           |
| **`CSYNC`**      | Child-to-Parent Synchronization        | RFC 7477           | Serial number, flags, and list of synchronized RR types              |
| **`TLSA`**       | DANE Transport Layer Security Auth     | RFC 6698, RFC 7671 | Certificate usage, selector, matching type, cert hex data            |
| **`SSHFP`**      | SSH Public Key Fingerprint             | RFC 4255, RFC 6594 | Algorithm, fingerprint type (`1` SHA-1, `2` SHA-256), hex data       |
| **`URI`**        | Uniform Resource Identifier            | RFC 7553           | Priority, weight, and target URI string                              |
| **`CERT`**       | Certificate / CRL Record               | RFC 4398           | Type, key tag, algorithm, and certificate data                       |
| **`OPENPGPKEY`** | OpenPGP Public Key                     | RFC 7929           | Base64-encoded OpenPGP public keyring data                           |
| **`SMIMEA`**     | S/MIME Certificate Association         | RFC 8162           | Certificate usage, selector, matching type, and association hex      |
| **`NAPTR`**      | Naming Authority Pointer               | RFC 3403           | Order, preference, flags, service, regex, replacement FQDN           |
| **`SPF`**        | Sender Policy Framework (Legacy)       | RFC 4408           | Text string policy definition                                        |
| **`LOC`**        | Geospatial Location Information        | RFC 1876           | Latitude, longitude, altitude, size, and precision specs             |
| **`HINFO`**      | Host CPU and Operating System Info     | RFC 8482, RFC 1035 | Enclosed CPU architecture and OS platform strings                    |
| **`RP`**         | Responsible Person                     | RFC 1183           | Mailbox domain name and TXT domain name for human contacts           |
| **`DHCID`**      | DHCP Client Identifier Data            | RFC 4701           | Base64-encoded DHCP client identifier association                    |

---

## 🎨 Visual Subnet Calculator Design & Mobile Responsive System

The interface has been meticulously designed following the acclaimed aesthetic of
[Visual Subnet Calculator](https://alsyundawy.github.io/visualsubnetcalc):

- **Curated Dark/Light Palette**: Deep obsidian dark background (`#0b0f19` / `#111827`), slate borders
  (`#1f2937` / `#334155`), and vibrant primary blue accents (`#3b82f6` with `#60a5fa` hover glow).
- **Glassmorphism Navigation Header**: Semi-transparent sticky navigation bar with `backdrop-filter: blur(12px)`.
- **Notch, Cutout & Safe Area Insets**: Integrated with `viewport-fit=cover` and CSS safe-area padding
  (`padding-top: env(safe-area-inset-top, 0px); padding-bottom: env(safe-area-inset-bottom, 0px);`).
- **Dynamic Viewport Height**: Replaces rigid `100vh` with adaptive `100dvh` to eliminate unwanted layout shifts
  under mobile address bars.
- **Touch-Friendly Overflow Scrolling**: Horizontal table wrappers utilize `-webkit-overflow-scrolling: touch` with
  rounded boundary containers.

---

## 🌐 Cross-OS Production Deployment & Migration

PowerDNS-Admin-PHP is verified across enterprise Linux operating systems. When migrating between distributions,
the primary variation lies in package management and web server configurations:

### Distribution Paths & Configuration Mapping

| Component / Setting      | Ubuntu 22.04 / 24.04 & Debian 11 / 12 | Rocky Linux 8 / 9 & AlmaLinux 8 / 9    |
| :----------------------- | :------------------------------------ | :------------------------------------- |
| **Package Names**        | `pdns-server`, `pdns-backend-mysql`   | `pdns`, `pdns-backend-mysql`           |
| **Systemd Service**      | `pdns.service`                        | `pdns.service`                         |
| **Main Config File**     | `/etc/powerdns/pdns.conf`             | `/etc/pdns/pdns.conf`                  |
| **Web Server Root**      | `/var/www/PowerDNS-Admin-PHP/public`  | `/var/www/PowerDNS-Admin-PHP/public`   |
| **Application Config**   | `/etc/pda/config.php`                 | `/etc/pda/config.php`                  |
| **PHP-FPM Socket**       | `/run/php/php8.3-fpm-pda.sock`        | `/run/php-fpm/www.sock`                |
| **Service User / Group** | `www-data:www-data`                   | `nginx:nginx` or `apache:apache`       |
| **Firewall Management**  | `ufw` (Uncomplicated Firewall)        | `firewalld` or `nftables` / `iptables` |

### Hardened PowerDNS Authoritative Configuration (`pdns.conf`)

Add the following directives to your PowerDNS daemon configuration (e.g., `/etc/powerdns/pdns.conf`):

```ini
# Enable Built-in Web Server and REST API
api=yes
api-key=SandiRahasiaApiPowerDNS_456!
webserver=yes
webserver-address=127.0.0.1
webserver-port=8081
webserver-allow-from=127.0.0.1,::1

# Authoritative Operational Settings
master=yes
slave=yes
soa-edit-api=DEFAULT
```

### Production Firewall Configuration

#### Ubuntu / Debian (UFW)

```bash
# Allow standard SSH and Web traffic
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Allow Authoritative DNS traffic
sudo ufw allow 53/tcp
sudo ufw allow 53/udp

# Enable firewall
sudo ufw enable
```

#### Rocky Linux / AlmaLinux / CentOS (Firewalld)

```bash
# Using firewalld
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --permanent --add-service=dns
sudo firewall-cmd --reload
```

---

## 📦 Installation & Setup Guide

### 1. Prerequisites

Ensure your host environment satisfies the minimum requirements:

- **PHP**: 8.2, 8.3, 8.4, or 8.5 with `pdo_mysql`, `curl`, `mbstring`, `xml`, `intl`, `openssl`.
- **Database**: MariaDB 10.5+ or MySQL 8.0+ with `utf8mb4` encoding.
- **Web Server**: Nginx (recommended) with PHP-FPM.
- **DNS Daemon**: PowerDNS Authoritative Server 4.5+ with API module enabled.

### 2. Method 1: Automated Shell Installation (Debian / Ubuntu)

Repositori ini menyertakan skrip installer non-interaktif yang siap pakai:

```bash
# 1. Unduh repositori ke direktori web server
git clone https://github.com/alsyundawy/PowerDNS-Admin-PHP.git /var/www/PowerDNS-Admin-PHP
cd /var/www/PowerDNS-Admin-PHP

# 2. Jalankan skrip instalasi dengan hak akses root/sudo
sudo bash deploy/install-debian.sh
```

Skrip ini secara otomatis memasang seluruh paket dependensi, mengonfigurasi pool PHP-FPM dedicated, menyetel vhost Nginx,
dan mengatur perizinan direktori.

---

### 3. Method 2: Manual Step-by-Step Installation

#### Step A: Install System Packages

```bash
sudo apt update && sudo apt install -y \
  nginx mariadb-server \
  php-fpm php-mysql php-curl php-mbstring php-xml php-intl php-gmp php-bcmath php-zip \
  curl git unzip ca-certificates
```

#### Step B: Create Panel Database & User

Masuk ke console MariaDB/MySQL:

```bash
sudo mysql -u root
```

Jalankan perintah SQL pembuatan database (dengan hak akses ganda localhost dan 127.0.0.1):

```sql
CREATE DATABASE pda CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pda_user'@'localhost' IDENTIFIED BY 'GantiDenganSandiKuat_123!';
CREATE USER 'pda_user'@'127.0.0.1' IDENTIFIED BY 'GantiDenganSandiKuat_123!';
GRANT ALL ON pda.* TO 'pda_user'@'localhost';
GRANT ALL ON pda.* TO 'pda_user'@'127.0.0.1';
FLUSH PRIVILEGES;
EXIT;
```

#### Step C: Clone Repository & Set Permissions

```bash
sudo git clone https://github.com/alsyundawy/PowerDNS-Admin-PHP.git /var/www/PowerDNS-Admin-PHP
sudo chown -R www-data:www-data /var/www/PowerDNS-Admin-PHP
sudo chmod -R 750 /var/www/PowerDNS-Admin-PHP
```

#### Step D: Configure Nginx Virtual Host

Salin konfigurasi vhost siap pakai dari [`deploy/nginx.conf`](deploy/nginx.conf):

```bash
sudo cp /var/www/PowerDNS-Admin-PHP/deploy/nginx.conf /etc/nginx/sites-available/powerdns-admin.conf
sudo ln -s /etc/nginx/sites-available/powerdns-admin.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

> [!IMPORTANT]
> Pastikan direktori `root` Nginx diarahkan ke subdirektori **`public/`** (`/var/www/PowerDNS-Admin-PHP/public`),
> bukan ke direktori root repositori, untuk melindungi berkas logika aplikasi dan konfigurasi dari akses HTTP langsung.

#### Step E: Run the Web Installer Wizard

1. Buka peramban dan akses: `http://ip-server-anda/install`
2. Masukkan informasi koneksi database MariaDB:
   - **Database Host:** `127.0.0.1`
   - **Database Port:** `3306`
   - **Database Name:** `pda`
   - **Database User:** `pda_user`
   - **Database Password:** `GantiDenganSandiKuat_123!`
3. Tentukan akun Administrator awal dan kredensial API PowerDNS:
   - **PowerDNS API URL:** `http://127.0.0.1:8081`
   - **PowerDNS API Key:** `SandiRahasiaApiPowerDNS_456!`
4. Klik **Pasang Sekarang**. Skrip akan otomatis menginisialisasi tabel database [`sql/schema.sql`](sql/schema.sql),
   membangkitkan encryption key di `/etc/pda/config.php`, dan mengarahkan Anda ke halaman login `/login`.

---

### 4. Method 3: Hardened Docker Container Deployment

Aplikasi dilengkapi [`Dockerfile`](Dockerfile) berbasis Alpine Linux minimalis yang aman dan telah diperkeras (_hardened non-root www-data_):

```bash
# 1. Build image Docker
docker build -t powerdns-admin-php:0.3.0 .

# 2. Jalankan container
docker run -d \
  --name powerdns-admin \
  --restart unless-stopped \
  -p 9000:9000 \
  -v /etc/pda/config.php:/var/www/html/config.php:ro \
  -v pda-uploads:/var/www/html/public/assets/uploads \
  powerdns-admin-php:0.3.0
```

---

## Usage & Quick Start

### Development & Local Testing

Untuk menjalankan dan menguji aplikasi secara mandiri menggunakan server web internal PHP:

```bash
php -S 127.0.0.1:8000 -t public
```

Buka peramban web pada alamat `http://127.0.0.1:8000` untuk mengakses antarmuka panel administrasi.

### Production Service Management

Pada server produksi Linux (Debian, Ubuntu, RHEL, AlmaLinux), aplikasi berjalan di bawah Nginx dan PHP-FPM:

```bash
# Memeriksa status dan menjalankan ulang layanan
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx
sudo systemctl status pdns
```

---

## ⚙️ Configuration Reference

Konfigurasi aplikasi dibagi menjadi dua lapisan: konfigurasi sistem pada berkas `config.php` dan pengaturan dinamis melalui Web Dashboard (`/settings`).

### 1. Konfigurasi Berkas Sistem (`/etc/pda/config.php` atau `config.php`)

Disimpan pada berkas konfigurasi lokal terlindungi (dengan izin `640` milik `www-data`):

| Setting Key  | Tipe Data | Default / Contoh Nilai | Keterangan                                                    |
| :----------- | :-------- | :--------------------- | :------------------------------------------------------------ |
| `db.host`    | `string`  | `"127.0.0.1"`          | Alamat host database MariaDB/MySQL panel.                     |
| `db.port`    | `int`     | `3306`                 | Port koneksi database.                                        |
| `db.name`    | `string`  | `"pda"`                | Nama database panel.                                          |
| `db.user`    | `string`  | `"pda_user"`           | Username database panel.                                      |
| `db.pass`    | `string`  | `"[REDACTED]"`         | Kata sandi database.                                          |
| `db.charset` | `string`  | `"utf8mb4"`            | Set karakter database (mendukung emoji & multilingual penuh). |
| `appKey`     | `string`  | `"[Base64 32 bytes]"`  | Kunci master simetris enkripsi AES-256-GCM (Wajib rahasia).   |
| `installed`  | `bool`    | `true`                 | Flag penanda status instalasi wizard web.                     |

---

### 2. Pengaturan Dinamis Web Dashboard (`/settings` — `views/settings.php`)

Dikelola langsung oleh peran `admin` dan disimpan secara terpusat pada tabel metadata `settings`:

#### A. Koneksi PowerDNS Authoritative API

- **`pdns_api_url`**: Endpoint webserver API PowerDNS Authoritative (contoh: `http://127.0.0.1:8081`).
- **`pdns_server_id`**: Server ID PowerDNS (standar: `localhost`).
- **`pdns_api_key`**: Kunci rahasia API daemon PowerDNS (dienkripsi simetris menggunakan `AES-256-GCM`).
- **`pdns_verify_tls`**: Verifikasi sertifikat TLS/SSL untuk endpoint HTTPS jarak jauh.

#### B. Parameter & Kebijakan Default DNS

- **`dns_default_ttl`**: TTL bawaan untuk record baru atau impor zona tanpa TTL eksplisit (30 – 604800 detik, standar: `3600`).
- **`dns_default_ns`**: Daftar nameserver otoritatif default yang otomatis dipra-isi saat pembuatan zona baru (contoh: `ns1.example.com, ns2.example.com`).
- **`dns_default_soa_email`**: Format email penanggung jawab zona RNAME (standar: `hostmaster.example.com`).
- **`dns_default_soa_refresh` / `retry` / `expire` / `minimum`**: Parameter siklus waktu SOA standar RFC 1035 (`10800`, `3600`, `604800`, `3600`).
- **`dns_auto_ptr_default`**: Status default checkbox sinkronisasi otomatis record A/AAAA ke zona reverse PTR (`1` aktif / `0` nonaktif).

#### C. Identitas, Tema & Kustomisasi Branding

- **`app_name`**: Nama instansi atau aplikasi yang tampil di navbar, sidebar, dan judul tab browser.
- **`app_logo_url`**: URL logo kustom atau jalur berkas logo hasil unggah (`PNG`, `SVG`, `WEBP` maks 2MB).
- **`app_footer_text`**: Teks copyright atau informasi kepatuhan pada bagian bawah panel dan form login.
- **`app_default_theme`**: Tema bawaan antarmuka untuk pengunjung baru (`dark` OLED Dark atau `light` Daylight Light).

#### D. Keamanan, Sesi & Kebijakan Login

- **`session_lifetime_minutes`**: Waktu kedaluwarsa sesi idle pengguna (5 – 10080 menit, standar: `120`).
- **`login_max_attempts`**: Batas kesalahan autentikasi berturut-turut sebelum pemicuan lockout brute-force (standar: `5`).
- **`login_lockout_seconds`**: Durasi penalti lockout brute-force IP dan akun (standar: `900` detik / 15 menit).
- **`security_force_hsts`**: Pengiriman header keamanan `Strict-Transport-Security (HSTS)` (`max-age=31536000`).

#### E. Retensi Riwayat Zona & Jejak Audit

- **`history_max_snapshots`**: Batas kuota rollback snapshot per zona DNS (standar: `25`).
- **`audit_retention_days`**: Durasi penyimpanan log aktivitas pada tabel audit log (standar: `90` hari).

#### F. Alat Diagnostik Jaringan & rDNS

- **`rdns_default_naming_pattern`**: Pola naming template generator record PTR massal (standar: `host-[ID].[DOMAIN]`).
  - _Makro yang didukung:_ `[ID]` (nomor urut), `[HEX]` (hexadecimal host), `[HEX16]` (16 nibble), `[IP]` (alamat IP lengkap), `[IP_DASH]` (IP pemisah tanda hubung), `[OCTET4]` (oktet ke-4 IPv4), `[DOMAIN]`.
- **`dns_public_resolvers`**: Daftar recursive resolver pembanding untuk alat DNS Lookup & Propagation Inspector (`1.1.1.1, 8.8.8.8, 9.9.9.9`).

---

### 3. Arsitektur Web Server & Paritas Konfigurasi Nginx vs Apache

PowerDNS-Admin-PHP menyediakan konfigurasi siap produksi untuk **Nginx** (`deploy/nginx.conf`) dan **Apache** (`public/.htaccess`) dengan paritas fungsional 100%:

| Aspek Keamanan & Performa            | Arahan Apache (`public/.htaccess`)                                          | Padanan Nginx (`deploy/nginx.conf`)                                                    |
| :----------------------------------- | :-------------------------------------------------------------------------- | :------------------------------------------------------------------------------------- |
| **Front Controller Routing**         | `RewriteCond %{REQUEST_FILENAME} !-f ... RewriteRule ^ index.php`           | `location / { try_files $uri $uri/ /index.php?$query_string; }`                        |
| **Sandboxing Upload Berkas**         | `<FilesMatch "\.(php\|cgi...)"> Require all denied ... php_flag engine off` | `location ^~ /uploads/ { location ~* \.(php\|cgi...)$ { deny all; return 404; } }`     |
| **Proteksi Berkas Sensitif**         | `<FilesMatch "(^\.\|\.(sql\|md\|sh\|conf)$)"> Require all denied`           | `location ~* \.(sql\|md\|log\|sh\|json\|lock\|neon\|xml\|bak\|conf)$ { deny all; }`    |
| **Blokir Direktori Tersembunyi**     | `RewriteRule "(^\|/)\.(?!well-known)" - [F]`                                | `location ~ /\.(?!well-known).* { deny all; access_log off; }`                         |
| **Proteksi Clickjacking & Sniffing** | `Header always set X-Frame-Options "DENY"`                                  | `add_header X-Frame-Options "DENY" always;`                                            |
| **Kebijakan Keamanan Konten (CSP)**  | `Header always set Content-Security-Policy "default-src 'self'..."`         | `add_header Content-Security-Policy "default-src 'self'..." always;`                   |
| **Caching Aset Statis Lokal**        | `ExpiresByType text/css "access plus 7 days"`                               | `location /assets/ { expires 7d; add_header Cache-Control "public, max-age=604800"; }` |
| **PHP-FPM Auto-Detection**           | `SetHandler "proxy:unix:/run/php/php-fpm-pda.sock\|fcgi://localhost"`       | `fastcgi_pass pda_php_fpm;` (didukung symlink universal `/run/php/php-fpm-pda.sock`)   |

#### Deteksi Otomatis Versi PHP-FPM

Skrip `deploy/detect-php-fpm.sh` dapat dijalankan kapan saja untuk memindai versi PHP yang terpasang dan memperbarui symlink universal:

```bash
sudo ./deploy/detect-php-fpm.sh
```

Skrip instalasi `deploy/install-debian.sh` juga secara otomatis mendeteksi apakah sistem menggunakan PHP 8.1, 8.2, 8.3, atau 8.4 dan menghubungkan socket secara dinamis.

---

## 🌐 REST API & Automation Layer

PowerDNS-Admin-PHP menyediakan antarmuka REST API yang aman untuk integrasi otomatisasi (Terraform, Ansible, skrip Python/Bash):

### Autentikasi

Semua request API memerlukan header `X-API-Key` dengan token yang dibangkitkan dari menu **API Keys**:

```http
X-API-Key: pda_live_9f83ac7b12d5e4a8b7c6d5e4f3a2b1c0
```

### Endpoint Utama

| Method   | Endpoint               | Scope Minimal | Deskripsi                                                       |
| :------- | :--------------------- | :------------ | :-------------------------------------------------------------- |
| `GET`    | `/api/v1/zones`        | `user`        | Mengambil daftar seluruh zona yang diizinkan untuk API key ini. |
| `GET`    | `/api/v1/zones/{name}` | `user`        | Mengambil metadata dan seluruh RRSet dari zona tertentu.        |
| `POST`   | `/api/v1/zones`        | `operator`    | Membuat zona authoritative baru.                                |
| `PUT`    | `/api/v1/zones/{name}` | `operator`    | Memperbarui metadata zona.                                      |
| `DELETE` | `/api/v1/zones/{name}` | `admin`       | Menghapus zona dari PowerDNS.                                   |

### Contoh Request via cURL

```bash
curl -s -X GET https://dns.example.com/api/v1/zones \
  -H "X-API-Key: pda_live_9f83ac7b12d5e4a8b7c6d5e4f3a2b1c0" \
  -H "Accept: application/json"
```

**Contoh Response Payload (HTTP 200):**

```json
{
  "zones": [
    {
      "name": "example.com.",
      "kind": "Native",
      "dnssec": 1,
      "serial": 2026100401,
      "account": "Engineering"
    }
  ]
}
```

---

## 📊 Quality Assurance & Verification Gates

Setiap berkas dalam PowerDNS-Admin-PHP diaudit secara ketat melalui quality gate otomatis:

| Quality Gate              | Engine / Tool                                                     | Standar Target                    | Kriteria Lolos            |       Status        |
| :------------------------ | :---------------------------------------------------------------- | :-------------------------------- | :------------------------ | :-----------------: |
| **PHP Syntax Check**      | `php -l` (Lint 44 PHP source files)                               | PHP 8.2+ Syntax Compliance        | 0 syntax errors           |  **✔ 44/44 PASS**   |
| **Coding Standards**      | [`PHP_CodeSniffer`](https://github.com/squizlabs/PHP_CodeSniffer) | PSR-12 strict & PSR-1 SideEffects | 0 errors, 0 warnings      |  **✔ PSR-12 PASS**  |
| **Code Formatting**       | [`PHP-CS-Fixer 3.95`](https://cs.symfony.com)                     | Symfony / PSR-12 strict ruleset   | 0 files to fix            |  **✔ 44/44 CLEAN**  |
| **Static Analysis**       | [`PHPStan`](https://phpstan.org)                                  | Level 5 Strict Analysis           | 0 errors                  | **✔ LEVEL 5 CLEAN** |
| **Type Inference**        | [`Psalm`](https://psalm.dev)                                      | Strict Type Safety Analysis       | 0 errors, 97.0% inference |     **✔ CLEAN**     |
| **Frontend Scripting**    | [`ESLint`](https://eslint.org)                                    | Vanilla JS DOM Architecture       | 0 lint errors             |     **✔ CLEAN**     |
| **Frontend Stylesheet**   | [`Stylelint`](https://stylelint.io)                               | Modern CSS & Safe Area Variables  | 0 style errors            |     **✔ CLEAN**     |
| **Shell Script Security** | [`ShellCheck`](https://www.shellcheck.net)                        | POSIX / Bash Defensive Standards  | 0 warnings or issues      |   **✔ 0 ISSUES**    |
| **Cognitive Complexity**  | [`SonarLint`](https://www.sonarsource.com/products/sonarlint/)    | Cognitive Complexity $\le 15$     | All handlers compliant    |     **✔ PASS**      |

```text
========================================================================================
QUALITY GATE VERIFICATION RESULTS
========================================================================================
✔ PHP Syntax Validation (php -l 44 files)       : 0 Syntax Error
✔ PHP CodeSniffer (PSR-12 & PSR-1 SideEffects) : 0 Error / 0 Warning
✔ PHP-CS-Fixer 3.95 (Dry-Run Check)            : 0 Files to Fix
✔ PHPStan Static Analysis (Level 5)            : [OK] 0 Errors
✔ Psalm Static Type Inference (Level 7)        : 0 Errors (97.0% Type Inference)
✔ ESLint (app.js & tests)                      : 0 Lint Errors
✔ Stylelint (public/assets/app.css)            : 0 Style Errors
✔ Prettier Format Check                        : 100% Code Formatting Match
✔ ShellCheck & Trunk (deploy/install-debian.sh): 0 Shell Script Warnings / POSIX
✔ Playwright Headless E2E (10 Viewports)       : 100% PASS (Zero Console & Runtime Errors)
✔ SonarLint Cognitive Complexity               : All Handlers <= 15 Complexity
✔ Max Line Length Invariant                    : 100% Non-Vendor Lines <= 120 Chars
✔ Git Whitespace Check (git diff --check)      : Clean (0 Trailing Spaces / EOF Issues)
========================================================================================
```

---

## 📋 Engineering Standards & Invariants

Untuk menjamin keandalan, pemeliharaan jangka panjang, dan keamanan sistem, standar rekayasa berikut diterapkan secara ketat:

- **Zero-CDN Invariant**: Tidak boleh ada aset frontend yang dimuat dari CDN eksternal. Semua berkas CSS, JS, dan ikon
  harus berada di dalam `public/assets/`.
- **Prepared Statements Exclusive**: Seluruh kueri SQL wajib menggunakan prepared statements PDO dengan parameter binding.
  Penggabungan string mentah ke dalam kueri dilarang tanpa kecuali.
- **Fail-Safe Session Cookies**: Cookie sesi wajib mengaktifkan atribut `HttpOnly`, `SameSite=Strict`, dan `Secure`
  (pada koneksi HTTPS) untuk mencegah pencurian sesi dan serangan XSS (SonarLint S3330).
- **Line Length Bound**: Kode sumber dan berkas template dioptimalkan agar tetap terbaca rapi dengan batas
  maksimum $\le 120$ karakter per baris.
- **Strict Canonical DNS Naming**: Seluruh nama domain dan FQDN diproses secara kanonikal dengan trailing dot (`.`)
  sesuai dengan spesifikasi PowerDNS HTTP API v1.

---

## 🔒 Security & Content Safety

- **OWASP Top 10:2025 Hardened**: Dirancang tahan terhadap SQL Injection, XSS, CSRF, IDOR/BOLA, dan Broken Authentication.
- **Argon2id Password Hashes**: Kata sandi disimpan dengan hash memori-keras `PASSWORD_ARGON2ID`.
- **AES-256-GCM Encryption**: Kredensial PowerDNS dienkripsi secara simetris dengan otentikasi data integritas.
- **CSRF Dual-Token Verification**: Setiap form mutasi dilindungi dengan token CSRF bertanda tangan sesi.
- **Audit Trails**: Setiap mutasi zona, perubahan record, dan aktivitas hak akses dicatat permanen dalam tabel `audit_logs`.

---

## 📂 Project Directory Structure

```text
PowerDNS-Admin-PHP/
├── .dockerignore                # Build context exclusion for container image security
├── .editorconfig                # Universal indentation and whitespace formatting rules
├── .gitattributes               # Git line-ending normalization and diff attributes
├── .gitignore                   # Version control ignore lists
├── .php-cs-fixer.php            # Enterprise PHP-CS-Fixer PSR-12 strict configuration
├── AUDIT_PLAN.md                # 13-Pillar Enterprise Codebase Audit Plan & Verification Log
├── CHANGELOG.md                 # Semantic versioning release changelog (Keep a Changelog)
├── Dockerfile                   # Hardened Alpine 3.19 PHP 8.3-FPM production container
├── DOCNOTE.md                   # Deep technical notes, architecture decisions, and runbooks
├── LICENSE                      # MIT Open Source License
├── README.md                    # Primary project documentation, architecture & guides
├── composer.json                # PHP dependency metadata, autoloading & scripts
├── composer.lock                # Locked dependency tree
├── package.json                 # Node.js ESM test runner configuration
├── phpcs.xml                    # PHP_CodeSniffer PSR-12 standard configuration
├── phpstan.neon                 # PHPStan Level 5 static analysis configuration
├── psalm.xml                    # Psalm Level 4 type-safety analysis configuration
├── app/                         # Pure Native PHP Application Kernel (PSR-12, Zero Framework)
│   ├── PdnsClient.php           # PowerDNS Authoritative HTTP API v1 REST client
│   ├── PdnsCluster.php          # Multi-Server PowerDNS cluster manager & node router
│   ├── analytics.php            # DNS telemetry parser & pure vector SVG graphics engine
│   ├── backup_services.php      # Metadata SQL backup/restore & zone snapshot engine
│   ├── bootstrap.php            # Sub-millisecond bootstrapper, PDO, crypto & rate limiting
│   ├── cache.php                # High-speed in-memory cache adapter (APCu / Request Memory)
│   ├── csrf.php                 # Cryptographic session-bound CSRF token rotation
│   ├── dns_name.php             # Canonical DNS name formatter, FQDN & PTR math
│   ├── handlers.php             # HTTP request dispatchers, controllers & route endpoints
│   ├── network_tools.php        # Subnet calculator, IPv6 splitter, WHOIS/RDAP & DNS lookup
│   ├── services.php             # Core domain logic, 31 record type validators & RRSet diff
│   ├── totp.php                 # RFC 6238 TOTP 2FA engine & pure vector SVG QR generator
│   └── webhook_services.php     # Event-driven HMAC-SHA256 cryptographic webhook dispatcher
├── deploy/                      # Production deployment templates & automation scripts
│   ├── detect-php-fpm.sh        # FastCGI socket detection script for Linux distributions
│   ├── install-debian.sh        # Automated unattended installer for Debian & Ubuntu
│   ├── nginx.conf               # Hardened production Nginx vhost with strict CSP & HSTS
│   └── pdns.snippet.conf        # Production PowerDNS daemon API configuration snippet
├── public/                      # Web server document root (publicly accessible)
│   ├── index.php                # Single front controller & router entry point
│   ├── assets/                  # Local offline assets (Zero-CDN air-gapped architecture)
│   │   ├── app.css              # Dark/light theme, safe area CSS & Xiaomi/Redmi guards
│   │   ├── app.js               # Vanilla JavaScript DOM controller & async interactions
│   │   ├── logo.svg             # High-resolution vector brand logo
│   │   └── vendor/              # Local offline vendor bundles (Bootstrap 5.3, Font Awesome)
│   │       ├── bootstrap/       # Bootstrap 5.3.3 CSS & JS bundles
│   │       └── fontawesome/     # Font Awesome 6.7.2 webfonts & CSS
│   └── uploads/                 # Secure storage directory for custom branding & avatars
├── sql/                         # Database schema & migrations
│   ├── schema.sql               # Base relational schema (MySQL 8.0+ / MariaDB 10.5+)
│   └── migrations/              # Automated non-destructive schema migrations
│       └── 0.3.0_enterprise_upgrade.sql # Schema migration for clusters, 2FA & webhooks
├── tests/                       # Automated test suites (Zero-framework PHP & Playwright E2E)
│   ├── helper.php               # Test environment bootstrap & mock assertions
│   ├── test_analytics.php       # DNS telemetry parser & SVG chart test suite
│   ├── test_backup_restore.php  # SQL dump/restore parser & sanitation test suite
│   ├── test_bind_parser.php     # RFC 1035 BIND zone file parser test suite
│   ├── test_bulk_records.php    # Cross-zone bulk search & replace test suite
│   ├── test_cache.php           # APCu and request memory cache adapter test suite
│   ├── test_cluster.php         # Multi-server cluster & node routing test suite
│   ├── test_dyndns.php          # DynDNS 2 HTTP endpoint & credential test suite
│   ├── test_linter.php          # Zone RFC compliance & linting engine test suite
│   ├── test_network_tools.php   # IPCalc, IPv6 splitter & WHOIS test suite
│   ├── test_playwright_responsive.js # Cross-device responsive layout test suite (10 viewports)
│   ├── test_profile.php         # User profile, password hashing & avatar test suite
│   ├── test_rdns_math.php       # IPv4 & IPv6 reverse DNS PTR math test suite
│   ├── test_rdns_services.php   # Reverse DNS batch generation & RFC type test suite
│   ├── test_snapshots.php       # Zone history & atomic diff engine test suite
│   ├── test_totp.php            # RFC 6238 TOTP, Base32 codec & SVG QR test suite
│   └── test_webhooks.php        # HMAC-SHA256 signature & webhook dispatch test suite
└── views/                       # Modern semantic HTML5 UI view components
    ├── accounts.php             # Multi-tenant account management & permissions
    ├── analytics.php            # Real-time DNS telemetry, donut gauges & traffic analytics
    ├── apikeys.php              # REST API token generation & revocation management
    ├── audit.php                # Tamper-evident audit trail viewer
    ├── backup.php               # Database & zone backup/restore interface
    ├── bulk_records.php         # Cross-zone bulk search, replace & snapshot rollback
    ├── dashboard.php            # Executive dashboard, quick metrics & health status
    ├── dnssec.php               # DNSSEC key management & CDS/CDNSKEY delegation
    ├── error.php                # Human-friendly HTTP error page (403, 404, 500)
    ├── install.php              # Initial installation wizard & database setup
    ├── layout.php               # Main application layout, sidebar & header
    ├── layout_bare.php          # Minimalist layout for login & setup
    ├── login.php                # Authentication page with Argon2id & 2FA TOTP verification
    ├── profile.php              # User self-service profile, password & 2FA enrollment
    ├── search.php               # Global fuzzy search across all zones and records
    ├── servers.php              # Multi-server PowerDNS cluster manager & node latency
    ├── settings.php             # Global panel configuration, branding & security
    ├── templates.php            # DNS zone template library & deployment wizard
    ├── tools_dns_lookup.php     # Web-based DNS resolver & query lookup tool
    ├── tools_ipcalc.php         # Interactive IPv4/IPv6 bitwise subnet calculator & IPv6 splitter
    ├── tools_rdns.php           # Reverse DNS wizard & batch PTR record generator
    ├── tools_whois.php          # WHOIS / RDAP lookup client (RFC 9082)
    ├── users.php                # Global user directory & role management (RBAC)
    ├── webhooks.php             # Cryptographic webhook subscription manager
    ├── zone_create.php          # Zone provisioning wizard (Forward/Reverse/Slave)
    ├── zone_history.php         # Zone revision history & 1-click diff rollback
    ├── zone_show.php            # Interactive RRSet record editor & RFC linter
    └── zones.php                # Authoritative zone directory & quick search listing
```

---

## 🤝 Contributing

Kontribusi sangat terbuka! Silakan ikuti panduan berikut:

1. Fork repositori ini dan buat branch fitur Anda: `git checkout -b feature/amazing-feature`.
2. Pastikan kode Anda mematuhi standar PSR-12 dan batas panjang baris $\le 120$ karakter.
3. Jalankan seluruh quality gate: `php -l`, `vendor/bin/phpstan`, `vendor/bin/psalm`, dan `vendor/bin/phpcs`.
4. Buat commit terstruktur menggunakan Conventional Commits: `git commit -m 'feat: add DNSSEC key rollover'`.
5. Push ke branch Anda dan buka sebuah Pull Request.

---

## 📬 Maintainer & Contact

Untuk konsultasi teknis, implementasi enterprise, audit keamanan DNS, atau kerjasama pengembangan:

- **Lead Maintainer & Engineering**: **HARRY DERTIN SUTISNA ALSYUNDAWY** — [`ALSYUNDAWY IT SOLUTION`](https://alsyundawy.com)
- **Official Website**: [`https://alsyundawy.com`](https://alsyundawy.com)
- **GitHub Profile**: [`@alsyundawy`](https://github.com/alsyundawy)
- **Email**: [`alsyundawy@gmail.com`](mailto:alsyundawy@gmail.com)
- **Phone / WhatsApp / Telegram**: [`+62 856-8515-212`](tel:+628568515212)
- **Repository**: [`https://github.com/alsyundawy/PowerDNS-Admin-PHP`](https://github.com/alsyundawy/PowerDNS-Admin-PHP)

---

## 💖 Support & Donation

Jika **PowerDNS-Admin-PHP** membantu mempermudah operasional infrastruktur DNS Anda, menghemat waktu administrasi server,
atau memberikan manfaat nyata bagi organisasi Anda, pertimbangkan untuk mendukung kelangsungan pemeliharaan, audit keamanan,
dan pengembangan fitur open-source ini:

### 💳 Dukungan Internasional: PayPal

[![Donate with PayPal](https://img.shields.io/badge/Donate-PayPal-00457C?style=for-the-badge&logo=paypal&logoColor=white)](https://www.paypal.me/alsyundawy)

- **Tautan PayPal**: [`https://www.paypal.me/alsyundawy`](https://www.paypal.me/alsyundawy)

### 🇮🇩 Dukungan Domestik & Regional: QRIS (Quick Response Code Indonesian Standard)

Pindai barcode QRIS di bawah ini menggunakan aplikasi mobile banking (BCA, Mandiri, BRI, BNI, BSI, CIMB Niaga, Permata)
atau e-wallet (GoPay, OVO, DANA, LinkAja, ShopeePay):

![QRIS Donation Barcode - ALSYUNDAWY](https://github.com/user-attachments/assets/a0126f28-6dde-43da-ba14-d7c9a27de0df)

- **Merchant / Nama Akun**: **ALSYUNDAWY IT SOLUTION**
- **NMID**: **`ID1020021153676`**
- **Direct Barcode Asset Link**: [`https://github.com/user-attachments/assets/a0126f28-6dde-43da-ba14-d7c9a27de0df`](https://github.com/user-attachments/assets/a0126f28-6dde-43da-ba14-d7c9a27de0df)
- **Konfirmasi WhatsApp**: [`+62 856-8515-212`](https://wa.me/628568515212)

Dukungan Anda memberikan dampak langsung dalam pengembangan peralatan DNS open-source yang aman, cepat, dan independen.

---

## 📄 License

PowerDNS-Admin-PHP adalah perangkat lunak open-source di bawah lisensi [`MIT License`](LICENSE) © 2024–2026 Harry DS Alsyundawy.

Bebas digunakan, dimodifikasi, dan didistribusikan untuk keperluan personal, komersial, maupun infrastruktur enterprise.
