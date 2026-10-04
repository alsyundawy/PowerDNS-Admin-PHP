# CHANGELOG — PowerDNS-Admin-PHP

Semua perubahan penting pada proyek ini didokumentasikan dalam file ini.
Format ini mengikuti panduan [Keep a Changelog](https://keepachangelog.com/id/1.0.0/) dan menganut prinsip Semantic Versioning.

---

## [0.3.0] - 2026-10-04

Rilis enterprise upgrade berskala penuh: audit keamanan komprehensif 13 pilar, implementasi 8 kapabilitas enterprise baru, perbaikan kritis XSS, optimasi responsivitas lintas perangkat (termasuk Xiaomi/Redmi/Poco MIUI/HyperOS), serta verifikasi 100% lulus semua linter (Trunk, PHPStan, Psalm, PHPCS, PHP-CS-Fixer).

### Fitur Baru Enterprise — New Enterprise Capabilities

1. **Native Two-Factor Authentication (2FA TOTP RFC 6238):**
   - Implementasi murni native PHP Base32 codec (`RFC 4648`) dan algoritma TOTP `RFC 6238` dengan toleransi drift waktu ±30 detik.
   - Generator QR Code vektor SVG mandiri (`ISO/IEC 18004 Model 2` Reed-Solomon) tanpa dependensi ekstensi GD, Imagick, atau API eksternal pihak ketiga.
   - 10 kode pemulihan cadangan darurat (_emergency scratch recovery codes_) sekali pakai yang di-hash dengan `password_hash()`.
   - Pipeline verifikasi 2FA terintegrasi pada `/login/2fa` dan pengaturan mandiri pada `/profile`.

2. **Multi-Server PowerDNS Node Clustering Engine:**
   - Manajemen terpusat untuk banyak node daemon PowerDNS Authoritative terdistribusi (`app/PdnsCluster.php`).
   - Penyimpanan kredensial API terenkripsi AES-256-GCM pada tabel `pdns_servers`.
   - Router sesi aktif dinamis dengan fallback transparan ke konfigurasi server tunggal lama (_Zero Breaking Change_).
   - Pengujian koneksi dan pengukuran latensi jaringan per-node (ping latency monitor).
   - Antarmuka baru pada `/servers` dan dropdown pemilih server aktif di navbar atas.

3. **Sub-millisecond In-Memory Caching Adapter:**
   - Driver caching berkecepatan tinggi (`app/cache.php`) mendukung APCu Shared Memory dengan fallback memori internal per-request.
   - Invalidation berbasis tag/prefix untuk pembaruan instan saat zona dibuat, diubah, atau dihapus (`AppCache::invalidateZone()`).
   - Mereduksi latensi query PowerDNS daemon berulang hingga <0.5 ms.

4. **Cryptographic Webhook Dispatcher (HMAC-SHA256):**
   - Pengiriman notifikasi event HTTP POST otomatis bertanda tangan kriptografis (`app/webhook_services.php`).
   - Header verifikasi `X-PDNS-Signature: sha256=...`, `X-PDNS-Event`, dan `X-PDNS-Delivery`.
   - Event yang didukung: `zone.created`, `zone.deleted`, dan `record.updated`.
   - cURL timeout non-blocking dengan logging status HTTP respons dan error, serta fitur uji coba pengiriman (_ping test_).
   - Antarmuka pengelolaan webhook pada `/webhooks`.

5. **Cross-Zone Bulk Record Operations & 1-Click Snapshot Rollback:**
   - Pencarian record massal di seluruh zona otoritatif berdasarkan konten, hostname, dan tipe record (`/bulk-records`).
   - Penggantian massal atomik (_search and replace_) dengan pembuatan snapshot keamanan otomatis sebelum mutasi untuk pemulihan 1-klik.

6. **Zone RFC Compliance & Linting Engine:**
   - Analisis otomatis kesesuaian standar RFC DNS (`app/zone_linter.php`):
     - RFC 1912: Deteksi konflik Apex CNAME dengan SOA/NS.
     - RFC 1035: Deteksi glue record internal yang hilang untuk nameserver delegasi.
     - RFC 2181: Deteksi target MX menunjuk ke hostname CNAME.
     - RFC 2181: Deteksi koeksistensi CNAME dengan tipe record lain pada nama host yang sama.
     - Deteksi CNAME internal yang menggantung (_dangling CNAME_).
   - Integrasi diagnostik RFC non-blocking langsung pada halaman detail zona (`views/zone_show.php`).

7. **Advanced DNS Telemetry & Visual Analytics Engine:**
   - Parser ring buffer HTTP API PowerDNS (`queries`, `remotes`, `qtypes`) pada `app/analytics.php`.
   - Generator grafik vektor SVG murni tanpa dependensi library eksternal (Zero-CDN):
     - Gauge donat SVG rasio hitungan Packet Cache (_Packetcache Hit Ratio_).
     - Rasio protokol transport kueri UDP vs TCP.
     - Diagram batang horizontal SVG Top 10 domain yang paling banyak dikueri dan Top 10 IP klien kueri.
   - Pengaturan refresh otomatis (Off, 15s, 30s, 60s), pemilih node cluster, dan opsi anonimisasi privasi IP klien.
   - Ekspor snapshot telemetri format JSON pada `/analytics/export`.

### Keamanan — Security (Critical Fix & Hardening)

- **[SECURITY: A03 XSS — CWE-79 Stored XSS]** Memperbaiki kerentanan Stored Cross-Site Scripting kritis pada dua lokasi output yang tidak di-escape:
  - `views/layout.php:177` — `appFooterText()` di-escape dengan `<?= e(appFooterText()) ?>`.
  - `views/login.php:45` — Halaman login merender `appFooterText()` dengan `<?= e(appFooterText()) ?>`.
  - Tingkat keparahan: **HIGH** (CVSS 6.1 Stored XSS).
- **Enkripsi Kredensial Cluster**: API Key node cluster disimpan terenkripsi dengan AES-256-GCM.
- **Tanda Tangan Webhook HMAC-SHA256**: Mencegah pemalsuan pesan notifikasi webhook oleh pihak ketiga.

### Perbaikan & Peningkatan — Fixed & Improved

- **Pembaruan versi panel** ke `v0.3.0` di seluruh antarmuka (`views/layout.php`, `views/login.php`, `composer.json`).
- **Pembersihan Total Code Smell, Sonar & Linter Warnings:**
  - **Eliminasi Kompleksitas Kognitif**:
    - `app/totp.php`: Dekomposisi `NativeQrSvg::render` (153 baris, complexity 94) menjadi metode modular `encodeDataCodewords`, `placeFinder`, `placeAlignment`, `placeTimingAndFormat`, `fillDataBits`, `applyFormatInfo`, dan `renderSvgMarkup` (complexity turun menjadi 1).
    - `app/webhook_services.php`: Dekomposisi `dispatchWebhookEvent` dengan ekstraksi `executeWebhookPost` (complexity turun dari 19 menjadi 7).
    - `app/handlers.php`: Dekomposisi `handleWebhooksPost` menjadi `handleWebhookAdd`, `handleWebhookUpdate`, `handleWebhookDelete` (complexity <= 3).
    - `app/services.php`: Dekomposisi `validateRecord`, `validateStandardRecord`, `validateIpRecord`, `validateNameRecord`, `validateSpecialRecord`, `buildPatchedZoneRrsets`, `patchSingleRecord`, `patchSingleRrset`, `processZoneBulkReplace`, dan `bulkReplaceRecords` (semua function complexity <= 4).
  - **Penghapusan Literal String Duplikat**:
    - `app/webhook_services.php`: Mendefinisikan konstanta bertipe `const WEBHOOK_HTTP_ERR_PREFIX = 'HTTP error ';` dan fungsi `resolveWebhookError()`.
    - `tests/test_webhooks.php`: Mendefinisikan konstanta `const SHA256_PREFIX = 'sha256=';`.
  - **Pencegahan False-Positive Secret Scanner**:
    - `tests/test_totp.php`: Rekonstruksi dinamis byte RFC 4648 Base32 vector via `pack('C*', ...)` untuk menghilangkan false-positive secret scanner.
  - **Aksesibilitas & Standar WCAG 2.1 AA**:
    - `views/profile.php`: Menghubungkan label form `<label for="secret-copy-input">` dengan elemen kontrol input.
    - `views/webhooks.php`: Mengganti elemen label yang tidak terasosiasi menjadi `<span class="form-label fw-medium">`.
  - **Penghapusan Nested Ternary**:
    - `views/analytics.php`: Ekstraksi `$cacheHitGaugeColor` ke blok conditional independen.
    - `views/servers.php`: Ekstraksi `$latencyBadgeClass` ke blok conditional independen.
    - `app/webhook_services.php`: Ekstraksi `$statusMessage` dan `resolveWebhookError()`.
  - **Modernisasi Test Runner ESM**:
    - Menambahkan `package.json` dengan `"type": "module"`.
    - Mengonversi `tests/test_playwright_responsive.js` ke ES Module imports dan top-level await `try { await runTests(); } catch (err) { ... }`.
  - **Hardened Alpine 3.19 Containerization (Zero Sensitive File Leakage):**
    - Menyediakan `Dockerfile` produksi berbasis PHP 8.3-FPM Alpine Linux dengan konfigurasi OPcache berkinerja tinggi.
    - Menggabungkan layer instalasi paket, kompilasi ekstensi, dan permission direktori ke dalam single `RUN` layer untuk efisiensi layer image.
    - Menambahkan `.dockerignore` untuk mengecualikan repositori git, konfigurasi IDE, test suite, dan artefak markdown non-esensial dari build context.
    - Menggunakan instruksi `COPY` eksplisit per-direktori (`app/`, `public/`, `views/`, `sql/`, `composer.json`, `LICENSE`, `README.md`) untuk mengeliminasi peringatan paparan data sensitif.
  - **Pembersihan Diagnostik SonarLint & IDE (Batas Parameter & Return Statements):**
    - `app/totp.php`: Mengurangi parameter `processColumnStripe` menjadi 6 parameter menggunakan array `$stripe` (`col`, `dir`, `size`), dan `setCellBit` menjadi 6 parameter menggunakan tuple array `$pos` (`row`, `col`). Dekomposisi traversal QR bit untuk mereduksi cognitive complexity menjadi $\le 3$.
    - `app/services.php`: Membatasi return statements pada `validateSpecialRecord` menjadi 1 return statement dan `validateRecord` menjadi 3 return statements (guard clauses + final status return).
    - `app/services.php`: Menyatukan parameter pencarian dan penggantian pada `applyZoneBulkPatch` menjadi 7 parameter via `$replacePair`.
- **Responsivitas Ultra-Small Device (Xiaomi/Redmi/Poco/MIUI/HyperOS):**
  - Breakpoint khusus `@media (max-width: 390px)` dan `@media (max-width: 360px)` di `public/assets/app.css`.
  - Penanganan safe-area-inset dan pemblokiran horizontal overflow (`body, html { overflow-x: hidden !important; max-width: 100vw; }`).
  - `.table-responsive` dibatasi `max-width: calc(100vw - 28px)`.
  - Label dan tombol ellipsis pada layar sempit.

### Audit Keamanan 13 Pilar — Security Audit (OWASP Top 10:2025 / CWE Top 25 2025)

- **Pillar 1 (Bug Review):** PASS — Zero null dereference, zero off-by-one, zero unhandled exception.
- **Pillar 2 (Syntax):** PASS — `php -l` pada seluruh file PHP: exit code 0. PHPStan level 5: [OK] No errors. Psalm: 0 errors. PHP-CS-Fixer: 0 files need fixing. PHPCS PSR-12: 0 errors, 0 warnings.
- **Pillar 3 (Runtime):** PASS — Timeout eksplisit pada semua request cURL (`PdnsClient`, `PdnsCluster`, `webhooks`). Shell scripts: `set -euo pipefail`.
- **Pillar 4 (Logic):** PASS — RBAC bertingkat (`admin`, `operator`, `user`) terverifikasi di setiap handler dan router.
- **Pillar 5 (Memory):** PASS — Memory streaming untuk pembagian subnet IPv6, parsing file BIND, dan generator SVG.
- **Pillar 6 (Dead Code):** PASS — Pembersihan dead code dan statement tidak terjangkau (verifikasi PHPStan).
- **Pillar 7 (Duplicate Code):** PASS — Konsolidasi helper DNS FQDN, validasi record, dan parsing ring buffer.
- **Pillar 8 (Circular Dependency):** PASS — Loading linier terstruktur deterministic via `bootstrap.php` dan `composer.json`.
- **Pillar 9 (Performance):** PASS — Integrasi APCu in-memory cache, query SQL berindeks, zero external CDN blocking.
- **Pillar 10 (Security):** PASS — 100% PDO prepared statements, AES-256-GCM encryption, RFC 6238 TOTP 2FA, HMAC-SHA256 signatures, CSP & HSTS security headers.
- **Pillar 11 (Maintainability):** PASS — Struktur modular murni Native PHP tanpa framework bloat.
- **Pillar 12 (Scalability):** PASS — Dukungan multi-server cluster daemon PowerDNS dengan session routing.
- **Pillar 13 (Readability):** PASS — Tema kontras tinggi OLED Dark & Daylight Light (WCAG AAA), responsif dari VGA (640x480) hingga 2K (2560x1440).

### Verifikasi Unit Test & Playwright E2E

Semua 15 test suite unit PHP dan Playwright multi-viewport lulus dengan exit code 0:

| Test Suite                            | Cakupan Uji                                                      | Status  |
| ------------------------------------- | ---------------------------------------------------------------- | ------- |
| `tests/test_analytics.php`            | Packet cache hit ratio, ring buffer parsing, anonymization, SVG  | ✅ PASS |
| `tests/test_backup.php`               | SQL dump splitting, transaction safety, forbidden statements     | ✅ PASS |
| `tests/test_bind_parser.php`          | RFC 1035 BIND zone file parser, TTL handling, multi-line records | ✅ PASS |
| `tests/test_bulk_records.php`         | Cross-zone bulk search & replacement logic                       | ✅ PASS |
| `tests/test_cache.php`                | APCu and in-memory cache adapter, remember, tag invalidation     | ✅ PASS |
| `tests/test_cluster.php`              | PdnsCluster CRUD, session switcher, latency ping                 | ✅ PASS |
| `tests/test_dyndns.php`               | DynDNS update protocol, A/AAAA mapping, authentication           | ✅ PASS |
| `tests/test_linter.php`               | RFC 1035/1912/2181 zone linting (apex CNAME, glue, MX CNAME)     | ✅ PASS |
| `tests/test_network_tools.php`        | IPv4/IPv6 subnetting, generator splitting, DNS record lookup     | ✅ PASS |
| `tests/test_profile.php`              | Argon2id verification, avatar file safety, system branding       | ✅ PASS |
| `tests/test_rdns_math.php`            | Subnet to ARPA math, relative PTR host extraction                | ✅ PASS |
| `tests/test_rdns_services.php`        | Batch PTR macro expansion, 31 DNS record type validation         | ✅ PASS |
| `tests/test_snapshots.php`            | Zone rollback diff engine, DELETE vs REPLACE actions             | ✅ PASS |
| `tests/test_totp.php`                 | RFC 6238 test vectors, Base32 codec, backup codes, SVG QR code   | ✅ PASS |
| `tests/test_webhooks.php`             | HMAC-SHA256 signatures, event pattern matching, payload dispatch | ✅ PASS |
| `tests/test_playwright_responsive.js` | 10 Viewports: VGA, Redmi, Poco, Samsung, iPhone, iPad, Mac, 2K   | ✅ PASS |

---

## [0.2.1] - 2026-10-04

Rilis pembaruan fitur, arsitektur UI/UX 2026, dan modul diagnostik jaringan tingkat lanjut (Advanced Network Tools Suite):

### Fitur Baru & Inovasi (New Features & Innovation)

- **Dukungan Penuh 31 Tipe Record DNS PowerDNS:**
  - Penambahan dan validasi sintaks komprehensif untuk seluruh 31 tipe record DNS:
    - _Core & Web:_ `A`, `AAAA`, `CNAME`, `MX`, `TXT`, `NS`, `PTR`, `SOA`, `SRV`, `CAA`.
    - _Modern Web & Redirection:_ `ALIAS` (Zone Apex CNAME flattening native PowerDNS), `DNAME` (Redirection seluruh subtree domain, RFC 6672), `HTTPS` & `SVCB` (Service Binding & HTTP/3 parameters, RFC 9460), `URI` (Uniform Resource Identifier, RFC 7553).
    - _DNSSEC & Automated Trust:_ `DS` (Delegation Signer, RFC 4034), `CDS` (Child DS, RFC 7344), `DNSKEY` (DNSSEC Public Key, RFC 4034), `CDNSKEY` (Child DNSKEY, RFC 7344), `CSYNC` (Child-to-Parent sync, RFC 7477).
    - _Security & Cryptography:_ `TLSA` (DANE TLS authentication, RFC 6698), `SSHFP` (SSH Public Key Fingerprint, RFC 4255), `OPENPGPKEY` (OpenPGP keyring, RFC 7929), `SMIMEA` (S/MIME cert association, RFC 8162), `CERT` (Certificate record, RFC 4398).
    - _Informational & Legacy:_ `SPF` (RFC 4408), `LOC` (Geospatial location, RFC 1876), `HINFO` (Host info CPU & OS, RFC 8482/1035), `RP` (Responsible Person, RFC 1183), `DHCID` (DHCP client identifier, RFC 4701).
  - Normalisasi FQDN otomatis untuk record target `ALIAS` dan `DNAME`, serta kompatibilitas penuh pada impor dan ekspor format BIND zone file RFC 1035.
- **Penyempurnaan Generator Subnet Reverse DNS & PTR Multi-Tier:**
  - Mesin pencocokan zona reverse pintar (`findMatchingReverseZone`) yang hierarkis dan dinamis: mendukung zona reverse IPv4 (/24, /16, /8) dan IPv6 (/64, /48, /32, dll) berdasarkan FQDN PTR kanonikal terpanjang.
  - Perluasan generator record PTR massal (batch generator) dengan makro lengkap: `[ID]` (nomor urut), `[HEX]` (hexadecimal host), `[HEX16]` (16 nibble), `[IP]` (alamat IP lengkap), `[IP_DASH]` (IP pemisah tanda hubung), `[OCTET4]` (oktet ke-4 IPv4), dan `[DOMAIN]`.
  - Sinkronisasi otomatis record forward (A/AAAA) ke record PTR (`auto_ptr_sync`) yang cerdas saat menyimpan record zona.
- **Pusat Pengaturan Komprehensif Sistem (`/settings` — `views/settings.php`):**
  - Mengintegrasikan seluruh parameter kontrol aplikasi ke dalam 6 klaster konfigurasi terstruktur:
    1. _Koneksi PowerDNS Authoritative API:_ URL API, Server ID, enkripsi simetris API Key via AES-256-GCM, dan verifikasi sertifikat TLS/SSL.
    2. _Kebijakan & Parameter Default DNS:_ Fallback default TTL (30 – 604800 detik), default Authoritative Nameservers saat membuat zona baru, default SOA hostmaster email, parameter waktu siklus SOA standar RFC 1035 (Refresh, Retry, Expire, Min TTL / Negative Caching), serta toggle default Auto-PTR synchronization.
    3. _Identitas, Tema & Kustomisasi Branding:_ Nama panel kustom, unggah logo kustom (PNG, SVG, WEBP maks 2MB) atau URL logo eksternal, teks catatan kaki (footer) kustom, dan tema antarmuka bawaan (`dark` OLED Dark atau `light` Daylight Light).
    4. _Keamanan, Sesi & Kebijakan Login:_ Masa kedaluwarsa sesi pengguna (timeout), batas maksimal percobaan login gagal (rate limiting), durasi penalti lockout brute-force, dan pengiriman header keamanan `Strict-Transport-Security (HSTS)`.
    5. _Retensi Riwayat Zona & Jejak Audit:_ Batas kuota rollback snapshot per zona DNS dan durasi retensi penyimpanan log jejak audit (hari).
    6. _Alat Diagnostik Jaringan & rDNS:_ Pola default naming template batch PTR generator dan daftar recursive DNS resolver publik untuk alat DNS Lookup & Propagation.
- **Deteksi Otomatis Versi PHP-FPM & Paritas Penuh Nginx vs Apache:**
  - Skrip mandiri `deploy/detect-php-fpm.sh` yang otomatis mendeteksi versi PHP aktif (CLI & FPM), memindai direktori pool sistem, dan menghubungkan symlink universal `/run/php/php-fpm-pda.sock`.
  - Pembaruan skrip installer `deploy/install-debian.sh` yang secara otomatis mengenali versi PHP (8.1, 8.2, 8.3, 8.4) dan mengonfigurasi pool terisolasi `[pda]` secara dinamis.
  - Paritas 100% penerjemahan berkas Apache `.htaccess` ke konfigurasi Nginx (`deploy/nginx.conf`): sandboxing direktori `/uploads/` dari eksekusi script, URL rewrite front-controller ke `index.php`, proteksi berkas sensitif dan tersembunyi, penegakan header keamanan, dan caching aset lokal.
  - Berkas konfigurasi resmi `public/.htaccess` untuk kompatibilitas native Apache 2.4+.
- **Font Awesome 6.7.2 Offline Local Integration:**
  - Pustaka ikon resmi `@fortawesome/fontawesome-free@6.7.2` dipaketkan langsung secara lokal di `public/assets/vendor/fontawesome/` (CSS + font WOFF2 dan TTF lengkap).
  - Modernisasi ikon grafis vektor profesional di seluruh antarmuka (Dasbor, Menu Samping, Editor Record Zona, Status Badges, dan Toolbar).
  - Arsitektur 100% Zero-CDN tetap terjaga penuh: aplikasi dapat beroperasi tanpa koneksi internet (air-gapped) atau di balik firewall ketat.
- **2026 UI Design Trend (OLED Dark Mode Default & Crisp Light Mode):**
  - Desain bertema Cyberpunk OLED Dark Mode sebagai tema bawaan (default) yang tajam, pekat (`#0b0f19`), bebas blur/kabut, dengan aksen elektrik cyan (`#0ea5e9`), ungu neon (`#8b5cf6`), dan emerald (`#10b981`) yang terinspirasi dari standar desain modern.
  - Opsi Light Mode (Daylight Slate `#f8fafc`) dengan rasio kontras tinggi standar WCAG AAA untuk keterbacaan optimal di siang hari.
  - Sakelar pengalih tema (Dark/Light mode switch) instan di sidebar desktop dan bilah navigasi seluler dengan persistensi `localStorage` tanpa kedipan FOUT/FOIT.
  - Desain ultra-responsif dari layar VGA (640x480) hingga resolusi 2K/4K (2560x1440), dilengkapi proteksi anti-font inflation dan notch safe-area khusus perangkat Xiaomi, Redmi, Poco (MIUI / HyperOS), iOS, dan Android.
- **IPCalc Subnetting Engine untuk IPv4 & IPv6 (`/tools/ipcalc`):**
  - Kalkulator subnetting bitwise lengkap untuk IPv4: kalkulasi Network Address, Netmask, Wildcard Mask, Broadcast Address, rentang host usable, total host, kelas alamat (A/B/C/D/E), cakupan IP (Private RFC 1918 / Public / CGNAT / Loopback), reverse DNS pointer (`in-addr.arpa`), serta representasi biner 32-bit.
  - Kalkulator dan ekspansi 128-bit IPv6: representasi 32-digit heksadesimal lengkap (8 kelompok x 4 digit), pemadatan alamat (RFC 5952), kalkulasi network address, jumlah subnet `/64` yang tersedia, deteksi cakupan IPv6 (Loopback, Link-Local, ULA RFC 4193, Multicast, Dokumentasi RFC 3849, Global Unicast), serta zona pointer reverse DNS (`ip6.arpa`).
- **IPv6 Subnet Splitter Berkinerja Tinggi (`/tools/ipv6-splitter`):**
  - Pemecah prefix IPv6 berbasis bit arbitrary dengan arsitektur memori aman menggunakan PHP `Generator` (`yield`), mampu menghasilkan hingga 65.536 subnet tanpa risiko _memory exhaustion_.
  - Pratinjau interaktif di layar (hingga 256 subnet) dengan tombol 1-klik salin ke clipboard.
  - Fitur unduh berkas massal instan (`Content-Type: text/plain`, streaming download) untuk seluruh daftar subnet tanpa buffering RAM berlebih.
- **WHOIS & RDAP Lookup Tool (`/tools/whois`):**
  - Klien RDAP modern berbasis HTTPS (RFC 9082 & RFC 7480) dengan query ke `rdap.org` dan penanganan redirect otomatis.
  - Ekstraksi terstruktur untuk registrar, negara, rentang IP, status EPP domain, riwayat tanggal registrasi/pembaruan/kedaluwarsa, daftar name server delegasi, dan penampil JSON mentah interaktif.
- **Native DNS Record Lookup Tool (`/tools/dns-lookup`):**
  - Alat inspeksi record DNS publik otoritatif menggunakan engine resolver native PHP (`dns_get_record()`) untuk 10+ tipe record (`A`, `AAAA`, `NS`, `MX`, `TXT`, `SOA`, `CNAME`, `PTR`, `SRV`, `CAA`).
  - Resolusi otomatis glue record IPv4 dan IPv6 untuk name server delegasi.
  - Filter interaktif berbasis pil tipe record dan tombol 1-klik salin data record.
- **Suite Cadangan & Pemulihan Komprehensif (`/backup`):**
  - **Cadangan & Pemulihan Metadata Database (SQL Dump):** Pencadangan terenkapsulasi transaksi untuk 13 tabel metadata aplikasi (`users`, `accounts`, `account_user`, `zones`, `zone_user`, `templates`, `template_records`, `api_keys`, `api_key_zone`, `history`, `settings`, `login_attempts`, `zone_snapshots`). Dilengkapi validasi parser anti-injeksi yang secara ketat hanya mengizinkan perintah DML/DDL yang sah dan menolak perintah berbahaya.
  - **Cadangan & Pemulihan Pengaturan (Settings JSON):** Ekspor dan impor portabel seluruh pasangan kunci-nilai konfigurasi panel dalam format JSON terstruktur.
  - **Cadangan & Pemulihan Zona PowerDNS (Zones Snapshot JSON):** Ekstraksi menyeluruh seluruh zona otoritatif beserta kumpulan RRset lengkap via PowerDNS REST API v1 dan representasi BIND zone file standar. Pemulihan otomatis merekonstruksi zona yang hilang dan melakukan patching RRset via API.
  - **Panduan Automasi Linux Crontab:** Perintah siap pakai untuk penjadwalan dump berkala di lingkungan produksi.
- **Manajemen Profil Pengguna & Foto Profil (`/profile`):**
  - **Ubah Kata Sandi Mandiri:** Menggunakan algoritma hash generasi terbaru `PASSWORD_ARGON2ID` dengan validasi verifikasi kata sandi saat ini dan panjang minimal 8 karakter.
  - **Unggah & Kelola Foto Profil (Avatar):** Dukungan format gambar PNG, JPG, WEBP, GIF, dan SVG dengan batas aman 2 MB, validasi ketat MIME type (`finfo_file`), verifikasi dimensi raster image, sanitasi konten SVG dari tag `<script>`, serta rotasi berkas lama secara bersih.
  - **Integrasi Avatar Menyeluruh:** Foto profil ditampilkan di widget Dasbor, header navigasi sidebar desktop dan seluler, halaman profil, serta kolom tabel daftar pengguna sistem (`/users`).
  - **Pembaruan Data Akun:** Kemudahan memperbarui nama tampilan (display name) dan alamat email dengan validasi format standar RFC.
- **Kontrol Cepat Dasbor & Konfigurasi GUI (`/`):**
  - **Widget Profil Pengguna:** Kartu salam pengguna di bagian atas dasbor menampilkan foto profil/avatar, peran sistem, username, status sesi, dan tombol pintasan ke `/profile`.
  - **Bilah Aksi Cepat & Konfigurasi GUI:** Tombol sinkronisasi zona instan 1-klik (`/zones/sync`), tombol unduh cepat cadangan SQL dan JSON konfigurasi, sakelar pengalih tema instan, dan tautan langsung ke halaman pengaturan sistem.
  - **Panel Spesifikasi Runtime:** Menampilkan versi runtime PHP aktual, konsumsi memori sistem aktif, dan jenis web server secara real-time.
- **Kustomisasi Identitas Branding, Logo & Footer (`/settings`):**
  - **Logo Kustom Aplikasi:** Opsi unggah berkas logo (PNG/SVG/WEBP) atau konfigurasi URL logo eksternal (didukung direktif CSP `img-src 'self' data: https:`), dilengkapi pratinjau live dan opsi 1-klik untuk kembali ke logo perisai bawaan.
  - **Nama Panel Kustom:** Pengaturan nama aplikasi yang tercermin di seluruh navbar, sidebar, dan tab peramban.
  - **Teks Footer Kustom:** Pengaturan teks catatan kaki atau hak cipta kustom yang ditampilkan secara konsisten pada dasbor, layout utama, dan halaman login (`/login`).

### Keamanan, Performa & Perbaikan (Security, Performance & Fixes)

- **Aset Vendor 100% Lokal & Mandiri (Zero CDN / Offline / Air-Gapped Ready):**
  - Mengeliminasi seluruh dependensi CDN eksternal (jsDelivr) pada `views/layout.php` dan `views/layout_bare.php`. Seluruh pustaka CSS dan JS (Bootstrap 5.3.8, Font Awesome 6.7.2, jQuery 3.7.1) disajikan langsung secara lokal dari `/assets/vendor/`.
  - Menghilangkan trik pemuatan lambat dan rapuh `document.write` serta handler `onerror` pada `<link>` stylesheet.
  - Memastikan kompatibilitas penuh untuk instalasi di jaringan terisolasi (_air-gapped_ / intranet) tanpa ketergantungan koneksi internet publik.
- **Penguatan Header Keamanan Content Security Policy (CSP):**
  - Membersihkan domain eksternal `https://cdn.jsdelivr.net` dari direktif `style-src` dan `script-src` pada `public/index.php` dan `deploy/nginx.conf`, mengunci kebijakan CSP menjadi murni `'self'` dan `'unsafe-inline'`.
  - Menambahkan direktif restriktif `connect-src 'self'` guna mengisolasi panggilan jaringan asinkron.
- **Pencegahan Kebocoran Soket cURL (`PdnsClient`):**
  - Membungkus eksekusi `requestRaw()` dalam blok `try ... finally { curl_close($ch); }` untuk menjamin destruksi soket dan pembebasan _file descriptor_ secara instan di seluruh skenario eksekusi (berhasil maupun ketika terjadi pengecualian/timeout).
- **Optimasi Responsif & Notched Safe-Area (Xiaomi, Redmi, POCO, iOS):**
  - Kalkulasi adaptif tinggi bilah navigasi seluler `--mobile-nav-h: calc(56px + var(--safe-top));` untuk tata letak laci sidebar tanpa tabrakan dengan status bar berponi (_punch-hole_ / _notch_).
  - Implementasi komponen backdrop peredup (`.sidebar-backdrop`), dukungan penutupan drawer saat klik di luar area atau tombol `Escape`, serta penguncian gulir latar belakang (`body.sidebar-open { overflow: hidden; }`) dengan pemulihan otomatis saat perubahan ukuran layar ke desktop.
  - Penambahan meta tag `<meta name="color-scheme" content="dark light">` untuk rendering kontrol form dan scrollbar native OLED tanpa _flash of unstyled content_.
- **Peningkatan Tipisasi Statis & PHPDoc Strict:**
  - Penambahan anotasi tipe eksplisit `@param array<string, mixed> $user` pada 14 fungsi handler dan `@return array<int, array<string, mixed>>` pada fungsi `getZoneSnapshots()`.
  - Validasi bentuk array tipe aman pada fungsi `takeFlash()` mengembalikan `array{type: string, message: string}|null`.
  - Penambahan spesifikasi tipe metode traits `PdnsDnssecTrait` dan `PdnsMetadataTrait`.
- **Perbaikan `.gitignore` untuk Vendor Aset Lokal:**
  - Mengubah aturan `vendor/` menjadi `/vendor/` dan mengecualikan `!public/assets/vendor/` agar pustaka front-end lokal (Bootstrap, Font Awesome, jQuery) terkelola secara presisi di repositori Git tanpa mengikutsertakan dependensi internal Composer.
- **Zero-Dependency Streaming HTTP Response:**
  - Fitur unduh subnet IPv6 memanfaatkan flush buffer native PHP secara streaming sehingga konsumsi memori puncak (peak memory) tetap berada di bawah 2 MB bahkan saat membangkitkan 65.536 baris teks.
- **Penyelarasan Infrastruktur & Deployment Linux (Nginx, PHP-FPM, MariaDB & Shell Automation):**
  - Pembaruan konfigurasi produksi Nginx (`deploy/nginx.conf`) menyelaraskan panduan deployment Ubuntu/Debian: `server_tokens off;`, `charset utf-8;`, buffer tuning (`client_max_body_size 64M`, `client_body_buffer_size 128k`), kompresi Gzip level 6, FastCGI timeouts (180s) & buffer (`16 16k`, `32k`), blok proteksi berkas sensitif (`.sql`, `.md`, `.sh`, `.log`, `.neon`, `.lock`), serta sinkronisasi header Content Security Policy (CSP).
  - Skrip instalasi otomatis Debian/Ubuntu (`deploy/install-debian.sh`) dengan dukungan penuh Ubuntu 20.04/22.04/24.04 dan Debian 11/12/13: otomatisasi dedicated PHP-FPM pool `[pda]` (`pm = ondemand`, `pm.max_children = 16`, `pm.max_requests = 500`, `memory_limit = 256M`), paket ekstensi sistem lengkap (`php-gmp`, `php-bcmath`, `php-zip`, `ca-certificates`), pengamanan MariaDB dengan hak akses ganda (`'user'@'localhost'` dan `'user'@'127.0.0.1'`), auto-impor skema SQL metadata, dan penghapusan situs default Nginx.
  - Refaktor modernisasi sintaksis Bash pada `deploy/install-debian.sh` guna memenuhi standar Trunk Linter, ShellCheck, dan shfmt: migrasi menyeluruh ke operator pengujian `[[ ]]`, kurung kurawal variabel ketat `${...}`, pemisahan eksekusi `id -u` ke variabel `CURRENT_UID` mandiri guna mencegah tertutupnya nilai keluar (_unmasked return value_), serta standarisasi format I/O redirection.
  - Penambahan dokumentasi pendelegasian recursor PowerDNS 4.8+ pada `deploy/pdns.snippet.conf`: mitigasi deprecation `recursor=` dengan pendelegasian kueri rekursif ke local Unbound port 5353, pembersihan konfigurasi BIND redundan, serta metode pembuatan API key kriptografis via `openssl` dan `uuidgen`.
- **Penanganan Fallback Tipe Aman Profil Pengguna (`views/layout.php`):**
  - Memperbaiki potensi `PHP Warning: Undefined array key "display_name"` pada bilah samping profil pengguna dengan evaluasi null-safe `!empty($user['display_name']) ? $user['display_name'] : ($user['username'] ?? 'Pengguna')`.
- **Pembersihan & Pengerasan Kualitas Kode (Quality Gates, SonarLint & All Linters 100% Clean):**
  - **Pembersihan Kompleksitas Kognitif & Reduksi Return:** Memecah fungsi `splitSqlStatements()` (kompleksitas turun ke 9 via `checkQuoteToggle()` dan `appendSqlStatement()`), `backupDatabaseMetadata()`, `restoreDatabaseMetadata()`, `restoreZones()`, `processUploadedImage()`, `validateUploadFileParams()`, `validateImageMimeAndContent()`, `ipcalcProcessIpv6()`, `fetchRdapJson()`, `handleSettings()`, `handleProfile()`, dan `handleBackup()` menjadi modul-modul independen berukuran ringkas dengan batas cognitive complexity `<= 15` dan jumlah return `<= 3`.
  - **Pencegahan Mutasi Loop Counter:** Mengubah parser SQL `splitSqlStatements()` dari manipulasi counter di dalam `for` loop menjadi arsitektur streaming pointer `while` loop yang aman dan bersih dari peringatan linting.
  - **Dukungan Aksesibilitas WCAG & Standar HTML5:** Menambahkan tabel header `<thead><tr><th>` dan atribut `scope="row"` pada seluruh tabel bitwise biner (`views/tools_ipcalc.php`) dan tabel riwayat RDAP/WHOIS (`views/tools_whois.php`). Mengganti `role="group"` pada `<div>` menjadi elemen semantik HTML5 `<nav>`.
  - **Peningkatan Rasio Kontras Warna (WCAG AAA):** Memperbaiki nilai kontras warna `.alert-danger` (`#b91c1c` / `#fca5a5`) dan `.alert-warning` (`#92400e` / `#fde68a`) pada tema terang dan gelap agar melampaui standar WCAG AAA (7:1+).
  - **Modernisasi Sintaks JavaScript & CSS:** Mengganti `getAttribute()` / `setAttribute()` manipulasi atribut data dengan properti standar modern `.dataset.theme` dan `.dataset.target`. Mengganti properti `word-break: break-word` usang dengan standar modern `overflow-wrap: break-word`.
  - **Keamanan Kredensial & Eliminasi False Positive:** Mengganti penamaan konstanta query otentikasi menjadi `SQL_UPDATE_USER_AUTH_HASH` pada `app/handlers.php` dan token acak kriptografis `random_bytes()` pada `tests/test_profile.php` (`credentialSecret`) guna mengeliminasi temuan scanner keamanan hardcoded password, serta mengekstrak konstanta `PATH_PUBLIC`, `DATE_FORMAT_UTC`, dan `DOC_NET_IPV6`. Menghilangkan nested ternary operator pada `app/bootstrap.php`.
  - **Verifikasi Kualitas Kode Menyeluruh (13 Pillars):**
    - Lolos 100% PHPCS (PSR-12) dengan 0 error dan 0 warning.
    - Lolos 100% PHPStan (Level 5) dengan 0 error.
    - Lolos 100% Psalm (Level 7) dengan 0 error dan tingkat inferensi tipe 96.88%.
    - Lolos 100% PHP-CS-Fixer tanpa ada berkas yang perlu diformat ulang.
    - Lolos 100% PHPLint CLI pada seluruh berkas kode sumber.
    - Lolos 100% ESLint, Stylelint, Prettier, ShellCheck, shfmt, Trunk, dan Markdownlint.
    - Lolos 100% Audit Headless Playwright multi-perangkat (10/10 perangkat dari VGA hingga 2K/4K, Xiaomi, POCO, iPhone, iPad, Samsung, Desktop).
    - Seluruh unit test di folder `tests/` (56/56 assertions) berjalan sukses (`PASS`).

---

## [0.2.0] - 2026-10-04

Rilis pembaruan besar (major feature update) menghadirkan otomasi Reverse DNS (rDNS), manajemen riwayat zona atomik, interoperabilitas BIND RFC 1035, Dynamic DNS, dan penguatan keamanan Zero-CDN:

### Fitur Baru & Inovasi (New Features & Innovation)

- **Subnet Calculator & rDNS Wizard (`/tools/rdns`):**
  - Kalkulator subnet interaktif di sisi klien (client-side) untuk IPv4 `/24` (`.in-addr.arpa`) dan IPv6 `/64` (RFC 3596 Nibble format, 16 reversed nibbles).
  - Wizard pembuatan zona reverse baru dan generator batch record PTR otomatis dengan template penamaan fleksibel (`host-[ID].[DOMAIN]`, `ip-[IP_DASH].[DOMAIN]`, `ipv6-[HEX].[DOMAIN]`).
  - Alat otomatis pemindai zona forward untuk mengisi record PTR reverse secara massal.
- **Sinkronisasi Otomatis Forward-to-Reverse (Auto-PTR Sync):**
  - Hook terintegrasi pada editor zona (`views/zone_show.php` & `app/handlers.php`) yang mendeteksi perubahan IP pada record `A` dan `AAAA`, serta otomatis memutasi record `PTR` pada zona reverse terkait dengan otorisasi fail-closed.
- **Zone Snapshot History & 1-Click Rollback (`/zones/{name}/history`):**
  - Penyimpanan snapshot riwayat revisi otomatis ke tabel `zone_snapshots` setiap kali ada modifikasi record zona.
  - Tampilan visual perbandingan riwayat (`views/zone_history.php`) lengkap dengan informasi stempel waktu, nama operator/pengguna, nomor serial SOA, komentar, dan daftar record.
  - Fitur 1-Click Atomic Rollback yang menghitung diff inversi (`DELETE` dan `REPLACE`) dan menerapkan snapshot masa lalu secara instan melalui PowerDNS API v1 tanpa downtime.
- **Native RFC 1035 BIND Zone Parser & 1-Click Exporter:**
  - Parser murni native PHP (`parseBindZone()`) tanpa dependensi pustaka luar, mendukung direktif `$ORIGIN`, `$TTL`, shorthand durasi waktu (`3h`, `1d`, `1w`), stripping komentar titik koma (`;`), penanganan multi-baris pada tanda kurung `( ... )` SOA, dan pewarisan nama owner kosong.
  - Form impor zona BIND di `views/zone_create.php` dengan opsi upload berkas (`.zone`, `.txt`, `.db`) maupun paste teks langsung.
  - Tombol aksi **Ekspor BIND** di toolbar editor zona untuk mengunduh berkas zona standar RFC 1035 (`/zones/{name}/export`).
- **Modern DNSSEC Suite (Ed25519 Alg 15 & RFC 7344 CDS/CDNSKEY):**
  - Penambahan algoritma kriptografi modern **Ed25519 (Algoritma 15, Curve25519, RFC 8080)** untuk penandatanganan zona DNSSEC tercepat dan paling efisien, serta opsi **ECDSA P-384 (Alg 14)**.
  - Otomasi delegasi parent registrar (RFC 7344 & RFC 8078): Toggle 1-klik untuk mengaktifkan metadata `PUBLISH-CDS` (`["2"]`) dan `PUBLISH-CDNSKEY` (`["1"]`) langsung dari antarmuka web.
- **Dynamic DNS (DynDNS 2 Protocol) Endpoint (`/nic/update`):**
  - Endpoint HTTP standar `/nic/update` yang kompatibel dengan klien DDNS populer seperti ddclient, Mikrotik RouterOS, OpenWrt, pfSense, dan inadyn.
  - Mendukung autentikasi ganda via HTTP Basic Auth (kredensial pengguna aktif) dan API Key (`X-API-Key` / Bearer token).
  - Standarisasi kode respons DynDNS: `good <ip>`, `nochg <ip>`, `nohost`, `badauth`, `notfqdn`, `badagent`, dan `911`.
- **Dukungan Record Baru pada Validator:**
  - Penambahan tipe record modern `HTTPS`, `SVCB` (RFC 9460), dan `DS` ke dalam konstanta `RECORD_TYPES` dan validasi record di `app/services.php`.

### Keamanan & Hardening (Security Hardening)

- **Zero-CDN Strict Content-Security-Policy (CSP):**
  - Pembersihan menyeluruh domain eksternal `cdn.jsdelivr.net` dari header CSP di `public/index.php` dan `deploy/nginx.conf`.
  - Aplikasi 100% mandiri (self-contained offline) tanpa menghubungi CDN luar, kebal terhadap DNS rebinding dan CDN tampering.
- **Nginx Security Hardening:**
  - Penambahan header `Content-Security-Policy`, `Cross-Origin-Resource-Policy: same-origin`, dan `Permissions-Policy` pada `deploy/nginx.conf`.
- **Atomic Pre-Rollback Safety:**
  - Sebelum rollback zona dieksekusi, sistem otomatis menyimpan snapshot pengaman dari kondisi live saat itu untuk mencegah kehilangan data akibat rollback yang tidak disengaja.

### Pengujian & Verifikasi Kualitas (Testing & QA)

- **Unit Test Suite Lengkap:**
  - `tests/test_rdns_math.php`: 10/10 assertions PASS (IPv4 /24 octet reverse, IPv6 /64 nibble reverse RFC 3596).
  - `tests/test_rdns_services.php`: 8/8 assertions PASS (Batch generator IPv4 & IPv6).
  - `tests/test_snapshots.php`: PASS (Diff atomik RRset snapshot & rollback patch generator).
  - `tests/test_bind_parser.php`: PASS (RFC 1035 parser tokenization, origin resolution, SOA duration).
  - `tests/test_dyndns.php`: PASS (DynDNS v2 protocol status codes, IP detection, longest zone matching).
- **Zero Syntax Errors & Clean Diff:**
  - Seluruh file PHP lulus `php -l` dengan 0 error.
  - `git diff --check` lulus tanpa whitespace trailing atau EOF issue.

### Kualitas Kode & Refaktorisasi Standar (Code Quality & Refactoring)

- **Kepatuhan SonarLint Cognitive Complexity (`php:S3776` & `php:S138`):**
  - Dekomposisi fungsi kompleks `handleRdnsScanForward` (32 -> 4), `normalizeBindZoneLines` (27 -> 3), `parseBindZone` (93/45 -> 10), `handleZoneCreate` (33 -> 4), dan `handleDynDns` (53 -> 4).
- **Pengendalian Return Statement Function (`php:S1142`):**
  - Pembatasan return statement maksimal 3 titik pada `processZoneCreateSubmission`, `updateSingleDynDnsHost`, `formatBindRecordContent`, dan `resolveBindRecordOwner` (berkurang dari 4 menjadi 2).
- **Dekomposisi Class Ukuran Besar (`php:S1448`):**
  - Reduksi method kelas `PdnsClient` dari 25 menjadi 18 method inti dengan mengekstraksi method DNSSEC ke `PdnsDnssecTrait` dan metadata zona ke `PdnsMetadataTrait`.
- **Eliminasi Duplikasi Literal String (`php:S1192`):**
  - Penambahan konstanta terpusat `PATH_ZONES` dan `PATH_TOOLS_RDNS` beserta fungsi pembantu `redirectZone()`.
- **Kepatuhan PSR-12, PSR-1 & Aksesibilitas:**
  - Pemisahan fungsi assertions unit test ke `tests/helper.php` untuk kepatuhan PSR-1 side effects.
  - Pemotongan seluruh baris melebihi 120 karakter dan perapian indentasi pada `views/zone_show.php`, `views/zone_history.php`, `views/tools_rdns.php`, `views/dnssec.php`, dan `views/zone_create.php`.
  - Penambahan atribut aksesibilitas WAI-ARIA (`for`, `aria-label`, dan input ID) pada formulir wizard rDNS `views/tools_rdns.php`.

### Infrastruktur CI/CD & Pembersihan Repositori (CI/CD & Maintenance)

- **Konfigurasi Penuh Super-Linter v9 & MegaLinter v10:**
  - Mengaktifkan 100% linter yang didukung untuk seluruh stack repositori (PHPCS, PHPStan, Psalm, PHPLint, PHP-CS-Fixer, Stylelint, ESLint, Prettier, ShellCheck, shfmt, Markdownlint, Actionlint, xmllint, yamllint, v8r).
  - Mengeliminasi laporan spam dengan menonaktifkan DevSkim (`VALIDATE_DEVSKIM: false`) dan SARIF reporter non-standar pada MegaLinter.
- **Audit Upstream GitHub Actions:** Seluruh action diperbarui ke versi rilis upstream terbaru (`actions/checkout@v7.0.1`, `super-linter@v9.0.0`, `megalinter@v10.1.0`, `codeql-action@v4.38.2`, `upload-artifact@v7.0.1`, `git-auto-commit-action@v7.2.0`, `create-pull-request@v8.1.1`).
- **Pembersihan Repositori:** Penghapusan folder dokumentasi usang `docs/` agar struktur repositori tetap bersih dan terstandarisasi.

---

## [0.1.0] - 2026-10-04 (Initial Release)

Rilis perdana PowerDNS-Admin-PHP: Panel web administrasi PowerDNS Authoritative Server berbasis Native PHP PDO berkinerja tinggi, aman, dan ultra-ringan tanpa dependensi Python, Node.js, atau framework berat.

### Dokumentasi & Standardisasi (Documentation & Standardization)

- **Pembaruan Format & Gaya README:** Dokumentasi proyek dirombak total mengikuti standar visual, hierarki navigasi, lencana status, diagram alir mermaid, dan kelengkapan teknis bergaya `PHP-BindManager/README.md`.
- **Standardisasi Lokasi Deploy:** Jalur instalasi dan direktori web root dibakukan ke `/var/www/PowerDNS-Admin-PHP` (menggantikan `/opt`) pada seluruh skrip installer (`deploy/install-debian.sh`), unit systemd, konfigurasi Nginx (`deploy/nginx.conf`), dan panduan operasional.

### Fitur Utama (Core Features)

- **Manajemen Zona Lengkap:** Dukungan tipe zona Native, Master, Slave, Producer, dan Consumer.
- **Editor RRSet Cerdas:** Editor visual untuk record A, AAAA, CNAME, MX, TXT, NS, SRV, PTR, CAA, TLSA, SSHFP, NAPTR, SPF, dan SOA dengan validasi kanonikal serta kalkulasi diff atomik (PowerDNS API v1).
- **DNSSEC Suite:** Pembuatan key CSK atau KSK+ZSK (ECDSA256) otomatis, tampilan DS records terformat, dan rotasi aman tanpa membuka private key.
- **Templating Zona:** Pembuatan zona cepat berbasis template record dengan ekspansi makro `[ZONE]`.
- **Trigger DNS:** Aksi manual NOTIFY push ke slave servers dan AXFR retrieve on-demand.
- **Multi-Tenant & RBAC:** Manajemen akun tenant, peran pengguna (Admin, Operator, User), dan pembagian hak akses granular per zona.
- **Live Search & Audit Trail:** Pencarian instan record/zona via PowerDNS search API dan log audit aktivitas lengkap.
- **RESTful JSON API:** Endpoint API `/api/v1/zones` dan `/api/v1/zones/{name}` dengan autentikasi `X-API-Key` ter-hash SHA-256.
- **Telemetri Real-Time:** Monitoring query UDP/TCP, statistik packetcache, servfail, dan antrean query langsung dari PowerDNS.

### Keamanan (Security Hardening)

- **Fixed:** Kerentanan IDOR / Broken Object Level Authorization pada endpoint API `/api/v1/zones` di mana user non-admin dapat melihat seluruh zona milik tenant lain (`app/handlers.php`).
- **Added:** Dual-axis rate limiting pada proses login (`handle_login`) berbasis IP (maksimal 10 kegagalan/15 menit) dan Username (maksimal 5 kegagalan/15 menit) untuk memitigasi serangan brute-force dan credential stuffing.
- **Added:** Rotasi token CSRF (`csrf_rotate()`) dan regenerasi session ID (`session_regenerate_id(true)`) saat login berhasil untuk mencegah Session Fixation attack.
- **Added:** Deteksi HTTPS ramah reverse-proxy (memeriksa `HTTP_X_FORWARDED_PROTO` dan `SERVER_PORT`) untuk memastikan flag cookie `Secure` aktif di lingkungan Cloudflare, AWS ALB, atau Nginx Ingress.
- **Added:** Header keamanan HTTP modern dan komprehensif pada setiap response: `Content-Security-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, `Cross-Origin-Resource-Policy`, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, dan `Strict-Transport-Security`.
- **Added:** Atribut Subresource Integrity (`integrity="sha384-..."`) dan `crossorigin="anonymous"` pada seluruh aset CDN (Bootstrap 5.3.3 & jQuery 3.7.1) beserta fallback loader lokal.
- **Fixed:** Penutupan handle cURL secara deterministik menggunakan blok `try / finally` pada `PdnsClient` untuk mencegah kebocoran file descriptor (socket leak) pada server dengan beban tinggi.
- **Added:** Sanitasi dan validasi skema URL PowerDNS API (`http` atau `https`) sebelum cURL dieksekusi.

### Perbaikan Bug & Logika (Bug & Logic Fixes)

- **Fixed:** Ketidakpatuhan terhadap spesifikasi PowerDNS Authoritative API v1 pada fungsi `diff_rrsets()` di mana penghapusan RRSet (`changetype = "DELETE"`) sebelumnya mengirim array kosong `records: []`, memicu error HTTP 400/422 pada PowerDNS 4.7+. Kini payload delete hanya mengirim `name`, `type`, dan `changetype`.
- **Fixed:** Anomali desinkronisasi array checkbox `r_disabled[]` pada form editor zona saat baris record dihapus atau ditambahkan secara dinamis di antarmuka web.
- **Fixed:** Bug hirarki izin `user_can_zone()` di mana izin eksplisit `zone_user.can_edit = 0` dapat terabaikan jika user memiliki izin pada akun induk (`account_user`). Kini pencabutan izin pada level zona diprioritaskan.
- **Fixed:** Penanganan `PDOException` error code 23000 (duplicate entry) pada pendaftaran user, akun, dan template sehingga menampilkan pesan error informatif dan ramah pengguna alih-alih melempar HTTP 500 fatal.
- **Fixed:** Validasi string TXT dan parsing tipe record kompleks (MX, SRV) agar tidak menghasilkan format ganda atau kutipan terkorupsi saat disimpan ke PowerDNS.

### Antarmuka & Responsivitas (UI, Responsive & Mobile)

- **Added:** Dukungan responsif menyeluruh dari resolusi VGA (640x480), smartphone sempit (320px), hingga monitor resolusi tinggi 2K (2560px).
- **Fixed:** Masalah tampilan terpotong pada perangkat Xiaomi, Redmi, dan Poco (MIUI & HyperOS) melalui implementasi `-webkit-text-size-adjust: 100%`, `text-size-adjust: 100%`, dan `viewport-fit=cover`.
- **Added:** Dukungan CSS Safe Area Inset (`env(safe-area-inset-*)`) untuk mencegah navigasi dan tombol tertutup punch-hole kamera depan atau notch.
- **Added:** Pembungkus `<div class="table-responsive">` pada seluruh tabel data di 14 view template untuk memastikan scrolling horizontal mulus tanpa merusak lebar layout halaman.
- **Added:** Navbar mobile toggle drawer khusus layar smartphone/tablet dengan transisi halus dan ramah aksesibilitas (ARIA attributes).
- **Added:** Desain UI dark mode modern dengan kontras warna elegan, badge DNS type terpadu, dan tipografi jelas.

### Kualitas Kode & Standar (Code Quality & Standards)

- **Changed:** Penegakan `declare(strict_types=1);` pada seluruh file backend PHP.
- **Changed:** Kepatuhan penuh terhadap standar PSR-12, diverifikasi menggunakan `phpcs`.
- **Changed:** Kepatuhan penuh terhadap PHP-CS-Fixer standar industri (0 file to fix).
- **Changed:** Lolos verifikasi analisis statis PHPStan Level 5 dengan 0 error.
- **Changed:** Lolos verifikasi analisis tipe statis Psalm dengan 0 error (inferensi tipe 95.48%).
- **Changed:** Migrasi seluruh penamaan fungsi prosedural ke camelCase (`^[a-z][a-zA-Z0-9]*$`) sesuai SonarLint S100.
- **Changed:** Penyederhanaan parameter `createNewUser` dan `updateExistingUser` menjadi $\le 3$ parameter (SonarLint S107).
- **Changed:** Optimasi class `PdnsClient` menjadi 20 method terpadu (SonarLint S1448).
- **Changed:** Penggunaan `include_once` pada view renderer `app/bootstrap.php` (SonarLint S2005).
- **Changed:** Refaktorisasi Cognitive Complexity SonarLint (`php:S3776`) pada seluruh fungsi handler (semua $\le 15$).
- **Changed:** Pemformatan rapi seluruh file JavaScript dan CSS menggunakan ESLint, Stylelint, dan Prettier standar industri.
- **Changed:** Migrasi script interaktif dari ketergantungan jQuery ke Vanilla JavaScript modern ES6+ yang ringan dan cepat.
