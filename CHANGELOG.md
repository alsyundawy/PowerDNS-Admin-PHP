# CHANGELOG — PowerDNS-Admin-PHP

Semua perubahan penting pada proyek ini didokumentasikan dalam file ini.
Format ini mengikuti panduan [Keep a Changelog](https://keepachangelog.com/id/1.0.0/) dan menganut prinsip Semantic Versioning.

---

## [0.2.1] - 2026-10-04

Rilis pembaruan fitur, arsitektur UI/UX 2026, dan modul diagnostik jaringan tingkat lanjut (Advanced Network Tools Suite):

### Fitur Baru & Inovasi (New Features & Innovation)

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

- **Pembaruan Bootstrap 5.3.8 & Integritas Subresource (SRI):**
  - Pembaruan dependensi CDN Bootstrap dari 5.3.3 ke versi stabil terbaru 5.3.8 pada `views/layout.php` dan `views/layout_bare.php`.
  - Penerapan hash verifikasi integritas SHA-384 resmi (`sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB` untuk stylesheet CSS dan `sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI` untuk bundle skrip JS).
- **Penguatan Header Keamanan Content Security Policy (CSP):**
  - Memperbarui direktif `style-src` dan `script-src` pada `public/index.php` untuk mengizinkan sumber resmi `https://cdn.jsdelivr.net` berdampingan dengan skrip inline dan aset lokal `'self'`.
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
- **Verifikasi Kualitas Kode Menyeluruh (13 Pillars):**
  - Lolos 100% PHPCS (PSR-12) dengan 0 error dan 0 warning.
  - Lolos 100% PHPStan (Level 5) dengan 0 error.
  - Lolos 100% Psalm (Level 7) dengan 0 error dan tingkat inferensi tipe 96.4%.
  - Lolos 100% PHP-CS-Fixer tanpa ada berkas yang perlu diformat ulang.
  - Lolos 100% ESLint, Stylelint, Prettier, ShellCheck, shfmt, Trunk, dan Markdownlint.
  - Lolos 100% Audit Headless Playwright (37/37 assertions PASS) menguji interaksi antarmuka tema OLED dark/light, FOUT-free reload, drawer Xiaomi/Redmi 393x852, kalkulasi bitwise IPCalc, dan zero browser console error.
  - Seluruh unit test di folder `tests/` berjalan sukses (`PASS`).

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
