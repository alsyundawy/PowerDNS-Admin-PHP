# CHANGELOG — PowerDNS-Admin-PHP

Semua perubahan penting pada proyek ini didokumentasikan dalam file ini.
Format ini mengikuti panduan [Keep a Changelog](https://keepachangelog.com/id/1.0.0/) dan menganut prinsip Semantic Versioning.

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
