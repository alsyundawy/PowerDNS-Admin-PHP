# DOCNOTE — PowerDNS-Admin-PHP Architecture, Changes & Operational Notes

## 1. Ikhtisar Arsitektur & Spesifikasi Sistem

PowerDNS-Admin-PHP adalah antarmuka manajemen web native, berkinerja tinggi, dan zero-dependency (tanpa dependensi Python, Node.js, atau Composer runtime bloat) untuk PowerDNS Authoritative Server v1 HTTP API, didukung database metadata MySQL/MariaDB dan frontend Vanilla JavaScript ES6+ serta CSS3 modern.

- **Bahasa & Runtime:** PHP 8.2 s/d PHP 8.5+ (Strict Types `declare(strict_types=1);`, native types, match expressions, throw expressions).
- **Database:** MySQL 8.0+ / MariaDB 10.5+ dengan PDO prepared statements menyeluruh.
- **Backend PowerDNS:** PowerDNS Authoritative Server 4.6.x – 5.2.x+ (REST API v1).
- **Web Server:** Nginx (direkomendasikan) atau Apache 2.4+ dengan FastCGI PHP-FPM.
- **Jalur Web Root Standar:** `/var/www/PowerDNS-Admin-PHP` (menggantikan direktori `/opt`).

---

## 2. Catatan Arsitektur & Operasional Versi 0.2.1 (2026 UI Design, Offline Font Awesome & Advanced Network Suite)

### A. Font Awesome 6.7.2 Offline Local Architecture

1. **Struktur Berkas & Distribusi Mandiri:**
   - Seluruh pustaka ikon resmi `@fortawesome/fontawesome-free@6.7.2` dipaketkan langsung dalam direktori `public/assets/vendor/fontawesome/` tanpa ketergantungan pada CDN eksternal.
   - Struktur folder:
     - `public/assets/vendor/fontawesome/css/all.min.css` (72 KB stylesheet terkompresi).
     - `public/assets/vendor/fontawesome/webfonts/` (berisi font format WOFF2 modern dan TTF untuk Solid, Regular, Brands, dan v4 compatibility).
2. **Keamanan & Kepatuhan Zero-CDN:**
   - Menghilangkan celah pelacakan pihak ketiga dan potensi serangan supply chain CDN.
   - Menjamin antarmuka tetap tampil sempurna di lingkungan jaringan tertutup (air-gapped), server intranet perusahaan, atau lingkungan perbankan dengan firewall ketat.

---

### B. 2026 UI Design System & Dual-Theme Engine (Dark / Light)

1. **Cyberpunk OLED Dark Mode (Default) & Daylight Slate Light Mode:**
   - Sesuai standar tren UI 2026 dan palet visual cyberpunk yang tajam, pekat, dan elegan:
     - **Dark Canvas:** `#0b0f19` (OLED obsidian space), kartu `#111827`, border `#1e293b`, aksen elektrik cyan `#0ea5e9`, ungu neon `#8b5cf6`, dan status emerald `#10b981`.
     - **Light Canvas:** `#f8fafc` (Daylight Slate), kartu `#ffffff`, border `#e2e8f0`, teks kontras `#0f172a`.
2. **Zero-Blur & Zero-Haze Rendering:**
   - Menghindari filter _backdrop-blur_ berlebih yang membebani GPU perangkat seluler.
   - Menggunakan garis tepi tegas 1px (`var(--line)`), bayangan multi-layer tajam, serta antialiasing font `-webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility`.
3. **Pencegahan Bug Font Inflation & Layar Terpotong (Xiaomi/Redmi/Poco/MIUI/HyperOS):**
   - Aturan proteksi `-webkit-text-size-adjust: 100%` dan `text-size-adjust: 100%` mencegah browser Android/MIUI membesarkan font secara sepihak pada wadah lebar.
   - Dukungan safe-area insets (`--safe-top`, `--safe-right`, `--safe-bottom`, `--safe-left`) dengan `viewport-fit=cover` untuk punch-hole dan notch kamera.
   - Wadah tabel fleksibel dengan `-webkit-overflow-scrolling: touch; overscroll-behavior-x: contain;` agar data teknis panjang (IPv6 / Reverse DNS) tidak memotong layout.
4. **Mekanisme Pengalih Tema (Theme Switcher):**
   - Skrip inline pada `<head>` mengeksekusi pengecekan `localStorage.getItem('pdns_theme')` sebelum DOM di-render, menghilangkan kedipan visual (zero flash of unstyled theme).
   - Tombol toggle interaktif di sidebar dan bilah atas mobile memperbarui atribut `data-theme` pada elemen `<html>` secara real-time.

---

### C. Advanced Network Tools Engine (`app/network_tools.php`)

1. **IPCalc Bitwise Engine (IPv4 & IPv6):**
   - **IPv4 (`ipcalcProcessIpv4`):** Menggunakan aritmatika bitwise native PHP 32-bit untuk menghitung Network, Netmask, Wildcard, Broadcast, Host Pertama/Terakhir, Total Host, Jumlah Usable Host (dengan penanganan RFC 3021 untuk `/31` dan Single Host `/32`), Kelas IP (A/B/C/D/E), Cakupan RFC (Private RFC 1918, CGNAT RFC 6598, Loopback RFC 1122, Public), Pointer rDNS (`in-addr.arpa.`), dan format biner 32-bit.
   - **IPv6 (`ipcalcProcessIpv6`):** Melakukan unkompresi 128-bit ke 32 karakter heksadesimal (8 grup x 4 hex), pemadatan alamat (RFC 5952), kalkulasi network address, estimasi jumlah subnet `/64` yang tersedia, klasifikasi cakupan (Loopback, Link-Local, ULA RFC 4193, Multicast, Dokumentasi RFC 3849, Global Unicast), serta zona pointer rDNS (`ip6.arpa.`).
2. **IPv6 Subnet Splitter Berbasis Generator Memory-Safe:**
   - Fungsi `ipv6splitGenerate()` memproses pemecahan prefix arbitrary (dari `/1` hingga `/128`, dengan selisih hingga 16 bit / 65.536 subnet) menggunakan PHP `Generator` (`yield`).
   - Mencegah alokasi memori puluhan megabyte untuk array string besar.
   - Endpoint `/tools/ipv6-splitter?download=1` mengirimkan berkas lampiran teks murni (`Content-Type: text/plain`) secara streaming langsung ke output buffer, menjaga konsumsi RAM server tetap di bawah 2 MB.
3. **WHOIS & RDAP Lookup Tool:**
   - **RDAP Client (`whoisQueryRdap`):** Klien modern berbasis HTTPS (RFC 9082 & RFC 7480) yang melakukan kueri ke `https://rdap.org/` dengan penanganan pengalihan HTTP otomatis. Menghasilkan representasi terstruktur untuk registrar, negara, rentang IP, status EPP, riwayat tanggal registrasi, serta daftar name server.
   - **WHOIS Socket Fallback (`whoisQuerySocket`):** Klien TCP port 43 native (RFC 3912) melalui `fsockopen()` untuk mendukung kueri TLD warisan atau server WHOIS spesifik dengan batas aman (timeout 6 detik, pembatasan buffer 64 KB).
4. **Native DNS Record Lookup Tool:**
   - Menggunakan fungsi native PHP `dns_get_record()` untuk mengeksekusi kueri otoritatif DNS publik untuk 10+ tipe record (`A`, `AAAA`, `NS`, `MX`, `TXT`, `SOA`, `CNAME`, `PTR`, `SRV`, `CAA`).
   - Mengidentifikasi name server delegasi dan secara otomatis menyelesaikan alamat IP glue record (IPv4 via DNS_A dan IPv6 via DNS_AAAA).

---

## 3. Catatan Arsitektur & Operasional Versi 0.2.0 (Advanced Features & Innovations)

### A. Visual Subnet Calculator & rDNS Wizard (`/tools/rdns`)

1. **Matematika Reverse DNS RFC 1035 (IPv4 /24):**
   - Mengambil 3 oktet pertama dari subnet IPv4, membalik urutannya, dan menambahkan akhiran `.in-addr.arpa.`.
   - Contoh: Subnet `192.0.2.0/24` -> Zona reverse `2.0.192.in-addr.arpa.`.
   - Record PTR relatif dalam zona `/24` adalah oktet ke-4 (contoh: host `192.0.2.42` -> nama PTR `42`).

2. **Matematika Reverse DNS RFC 3596 Nibble Format (IPv6 /64):**
   - Mengembangkan alamat IPv6 ke format penuh 32 karakter heksadesimal (8 grup x 4 nibble), mengambil 16 nibble pertama (/64 prefix), membalik urutannya, dipisahkan titik, dan menambahkan akhiran `.ip6.arpa.`.
   - Contoh: `2001:db8:1234:5678::/64` -> Zona reverse `8.7.6.5.4.3.2.1.8.b.d.0.1.0.0.2.ip6.arpa.`.
   - Record PTR relatif dalam zona `/64` terdiri dari 16 nibble interface ID yang dibalik.
   - Contoh: Host `2001:db8:1234:5678::1` -> `1.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0`.

3. **Generator Batch PTR Terotomasi (`generateIpv4SubnetPtrBatch`, `generateIpv6SubnetPtrBatch`):**
   - Memungkinkan administrator membangkitkan PTR massal untuk host `.1` hingga `.254` (atau rentang kustom) menggunakan template penamaan dinamis:
     - `host-[ID].[DOMAIN]` -> `host-1.example.com.`
     - `ip-[IP_DASH].[DOMAIN]` -> `ip-192-0-2-1.example.com.`
     - `ipv6-[HEX].[DOMAIN]` -> `ipv6-1.example.com.`

4. **Sinkronisasi Terbalik Otomatis (Bidirectional Auto-PTR Sync):**
   - Saat record forward `A` atau `AAAA` disimpan di `handleZoneSave()`, opsi `auto_ptr_sync` memicu fungsi `syncForwardIpToReversePtr()`.
   - Sistem mencari zona reverse yang cocok di database lokal (`findMatchingReverseZone()`).
   - Memeriksa hak otorisasi edit pengguna pada zona reverse tersebut (fail-closed security).
   - Membentuk payload atomik PATCH untuk membuat atau menghapus record PTR di PowerDNS API v1.

---

### B. Zone Snapshot History & 1-Click Rollback (`/zones/{name}/history`)

1. **Skema Penyimpanan (`zone_snapshots`):**
   - Setiap mutasi record pada zona memicu penyimpanan snapshot keadaan live sebelum perubahan ke tabel `zone_snapshots` di MySQL.
   - Menyimpan `zone_name`, `serial` SOA, seluruh `rrsets_json` dalam format JSON native, `user_id`, `username`, stempel waktu, dan `comment`.
   - Dilengkapi fungsi `ensureZoneSnapshotsTable()` untuk memastikan tabel siap digunakan secara otomatis saat runtime.

2. **Algoritma Diff Atomik Inversi (`diffSnapshotRrsets`):**
   - Membandingkan RRsets live saat ini dengan RRsets snapshot target.
   - Setiap RRset live yang tidak ada di snapshot target diberi instruksi `DELETE` (kecuali record `SOA` yang dilindungi).
   - Setiap RRset pada snapshot target diberi instruksi `REPLACE` lengkap dengan array record, TTL, dan komentar.
   - Menghasilkan array PATCH atomik yang diaplikasikan langsung melalui `$pdns->patchRrsets()`.

3. **Pre-Rollback Safety Guard:**
   - Sesaat sebelum rollback dieksekusi, sistem secara otomatis mengambil snapshot pengaman ("Safety Snapshot") dari zona live saat itu. Jika pengguna keliru memilih versi rollback, kondisi sebelumnya dapat dipulihkan kapan saja.

---

### C. Native RFC 1035 BIND Zone File Parser & 1-Click Exporter

1. **Arsitektur Parser Murni Native PHP (`parseBindZone`):**
   - Beroperasi tanpa dependensi Composer eksternal (menghemat memori dan meningkatkan portabilitas).
   - **Tahap Normalisasi:** Mengubah baris multi-baris pada tanda kurung `( ... )` (khas record SOA) menjadi satu baris logis terpadu, serta membuang komentar titik koma (`;`) di luar tanda kutip string.
   - **Pemrosesan Direktif:** Menangani `$ORIGIN <domain>` dan `$TTL <durasi>` dengan dukungan konversi satuan waktu (`parseDnsDuration`: `30m`, `3h`, `1d`, `1w`).
   - **Pewarisan Nama Owner:** Mengenali spasi/tab di awal baris sebagai pewarisan nama owner dari record sebelumnya.
   - **Resolusi Simbol `@`:** Mengubah `@` secara kanonikal ke `$currentOrigin`.
   - Mengelompokkan seluruh record ke dalam map RRset unik berdasarkan kombinasi `name` dan `type` sesuai kontrak PowerDNS API v1.

2. **Ekspor BIND 1-Click (`/zones/{name}/export`):**
   - Memanfaatkan endpoint native PowerDNS `GET /api/v1/servers/{server_id}/zones/{zone_id}/export` dengan header `Accept: text/plain`.
   - Dilengkapi fallback generator otomatis: jika PowerDNS backend tidak mendukung endpoint `/export`, sistem memformat seluruh RRset zona live ke teks BIND RFC 1035 standar.

---

### D. Modern DNSSEC Suite (Ed25519 Alg 15 & RFC 7344 CDS/CDNSKEY)

1. **Algoritma Kriptografi Terkini:**
   - **Ed25519 (Algoritma 15, Curve25519, RFC 8080):** Algoritma modern berkecepatan tinggi dengan ukuran tanda tangan dan kunci yang sangat ringkas, meminimalkan amplifikasi DNS dan fragmentasi paket UDP.
   - **ECDSA P-256 (Algoritma 13)** & **ECDSA P-384 (Algoritma 14)**.
   - **RSA/SHA-256 (Algoritma 8)** untuk zona dengan kebutuhan kompatibilitas legacy.

2. **Otomasi Parent Delegation Bootstrapping (RFC 7344 & RFC 8078):**
   - Registry TLD modern (seperti `.ch`, `.cz`, `.nl`, Cloudflare) dapat memindai record CDS (Child DS) atau CDNSKEY untuk memperbarui DS record di parent zone secara otomatis.
   - Panel menyediakan toggle satu klik melalui endpoint `/zones/{name}/dnssec/cds` yang memanipulasi metadata zona di PowerDNS:
     - `PUBLISH-CDS`: Nilai `["2"]` (SHA-256 digest).
     - `PUBLISH-CDNSKEY`: Nilai `["1"]`.
   - Saat dinonaktifkan, metadata dihapus secara bersih melalui method `deleteMetadata()`.

---

### E. Dynamic DNS (DynDNS 2 Protocol) Endpoint (`/nic/update`)

1. **Kepatuhan Protokol Standar:**
   - Endpoint HTTP `/nic/update` dirancang kompatibel dengan perkakas DDNS standar (ddclient, Mikrotik RouterOS, OpenWrt, pfSense, inadyn).
   - Parameter query: `hostname` (mendukung daftar hostname dipisah koma) dan `myip` (otomatis mendeteksi IP koneksi klien jika tidak disertakan).

2. **Autentikasi Ganda Tanpa Sesi Web:**
   - Ditempatkan sebelum middleware `requireLogin()` pada `public/index.php`.
   - Mendukung HTTP Basic Authentication (`$_SERVER['PHP_AUTH_USER']` & `PHP_AUTH_PW`) yang diverifikasi terhadap tabel `users` dengan `password_verify()`.
   - Mendukung autentikasi API Key melalui header `X-API-Key`, `Authorization: Bearer <token>`, atau query parameter `key=<token>`.

3. **Pencarian Zona Terpanjang (`findMatchingZoneForHostname`):**
   - Memastikan subdomain dalam hierarki zona bertingkat (misal: `dyn.sub.example.com`) dicocokkan ke zona paling spesifik (`sub.example.com.` alih-alih `example.com.`).

4. **Kode Respons Standar DynDNS v2:**
   - `good <ip>`: Record berhasil diperbarui ke IP baru.
   - `nochg <ip>`: IP yang dikirim sama dengan IP yang sudah ada di zona (tidak ada perubahan).
   - `nohost`: Hostname tidak ditemukan pada zona manapun atau akun tidak berhak.
   - `badauth`: Kredensial username/password atau API key tidak valid.
   - `notfqdn`: Parameter hostname kosong atau tidak valid.
   - `badagent`: Format IP yang dikirim tidak valid.
   - `911`: Terjadi kegagalan server atau PowerDNS backend tidak terjangkau.

---

### F. Penguatan Keamanan CDN, SRI & Header HTTP

1. **Redundansi Bootstrap 5.3.8 & Subresource Integrity (SRI):**
   - Menggunakan CDN resmi jsDelivr dengan hash SHA-384 resmi (`sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB` & `sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI`) dan fallback otomatis ke vendor lokal `public/assets/vendor/` jika koneksi CDN gagal atau di lingkungan terisolasi (_air-gapped_).
   - Seluruh pustaka ikon Font Awesome 6.7.2 disajikan 100% secara lokal dari direktori `public/assets/vendor/fontawesome/`.

2. **Header Keamanan Lengkap:**

   ```http
   Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'
   X-Frame-Options: DENY
   X-Content-Type-Options: nosniff
   Referrer-Policy: same-origin
   Cross-Origin-Opener-Policy: same-origin
   Cross-Origin-Resource-Policy: same-origin
   Permissions-Policy: accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()
   Strict-Transport-Security: max-age=31536000; includeSubDomains
   ```

---

### G. Resolusi Kepatuhan SonarLint & Standar Clean Code PSR-12

1. **Dekomposisi Cognitive Complexity SonarLint (`php:S3776` & `php:S138`):**
   - `handleRdnsScanForward`: Didekomposisi menjadi `collectMatchingForwardIps`, `extractMatchingIpsFromRrsets`, dan `buildBatchImportPtrRrsets` (Complexity turun dari 32 menjadi 4).
   - `normalizeBindZoneLines`: Didekomposisi menjadi `stripBindComments` dan `processParenthesisState` (Complexity turun dari 27 menjadi 3).
   - `parseBindZone`: Didekomposisi menjadi `handleBindDirective`, `resolveBindRecordOwner`, `parseBindTokensRdata`, dan `appendBindRecordToRrsets` (Complexity turun dari 93/45 menjadi 10).
   - `handleZoneCreate`: Didekomposisi menjadi `resolveUploadedBindContent` dan `processZoneCreateSubmission` (Complexity turun dari 33 menjadi 4).
   - `handleDynDns`: Didekomposisi menjadi `authenticateDynDnsUser`, `canDynDnsUserAccessZone`, `resolveDynDnsZoneTarget`, dan `updateSingleDynDnsHost` (Complexity turun dari 53 menjadi 4).

2. **Pengendalian Return Statement Function (`php:S1142`):**
   - `processZoneCreateSubmission`: Menyatukan pengecekan BIND parser ke variabel `$error` sehingga alur return berkurang menjadi 3 titik.
   - `updateSingleDynDnsHost`: Mengekstrak validasi zona target ke `resolveDynDnsZoneTarget()` sehingga alur return berkurang dari 4 menjadi 3 titik.
   - `formatBindRecordContent`: Mengekstrak formatting record SOA ke `formatBindSoaContent()` sehingga alur return berkurang dari 4 menjadi 3 titik.
   - `resolveBindRecordOwner`: Menyatukan penetapan owner ke variabel lokal `$owner` sehingga titik return berkurang dari 4 menjadi 2 titik.

3. **Dekomposisi Class Ukuran Besar (`php:S1448`):**
   - Kelas `PdnsClient` sebelumnya memiliki 25 method. Didekomposisi menjadi 18 method inti dengan mengekstraksi method DNSSEC ke `app/PdnsDnssecTrait.php` dan metadata zona ke `app/PdnsMetadataTrait.php`.

4. **Pencegahan Duplikasi String Literal (`php:S1192`):**
   - Didefinisikan konstanta terpusat `const PATH_ZONES = '/zones/';` dan `const PATH_TOOLS_RDNS = '/tools/rdns';` pada `app/handlers.php` bersama fungsi pembantu `redirectZone()`.

5. **Kepatuhan Format PSR-12, PSR-1 & Aksesibilitas Web:**
   - Standarisasi `tests/helper.php` untuk memisahkan fungsi assertions dari skrip eksekusi pengujian, menghilangkan pelanggaran PSR-1 side effects.
   - Pemotongan seluruh baris melebihi 120 karakter dan perapian indentasi kontrol struktur pada `views/zone_show.php`, `views/zone_history.php`, `views/tools_rdns.php`, `views/dnssec.php`, dan `views/zone_create.php`.
   - Perbaikan atribut aksesibilitas WAI-ARIA (`for`, `aria-label`, dan input ID eksplisit) pada formulir wizard rDNS (`views/tools_rdns.php`).

---

### H. Harmonisasi Infrastruktur Nginx, PHP-FPM, dan MariaDB (Berdasarkan Panduan ISP Deployment)

1. **Optimalisasi Nginx Reverse Proxy (`deploy/nginx.conf`):**
   - **Buffer & Ukuran Payload:** Menaikkan `client_max_body_size` menjadi `64M` dan `client_body_buffer_size 128k` untuk mendukung impor/ekspor berkas zona BIND skala puluhan ribu record.
   - **Kompresi Gzip:** Menambahkan konfigurasi Gzip otomatis (`gzip_types`) untuk mempercepat transfer data CSS, JS, dan respons JSON REST API.
   - **FastCGI Timeouts & Buffering:** Menetapkan `fastcgi_read_timeout 180s`, `fastcgi_buffer_size 32k`, dan `fastcgi_buffers 16 16k` untuk mencegah error HTTP 504 Gateway Timeout saat sinkronisasi zona massal.
   - **Proteksi Berkas Sensitif:** Penolakan akses langsung terhadap ekstensi berkas `.sql`, `.md`, `.log`, `.sh`, `.json`, `.lock`, `.neon`, `.xml`, dan `.conf`.
   - **Keamanan Header:** Penambahan `server_tokens off;`, `charset utf-8;`, dan `fastcgi_param HTTP_PROXY "";`.

2. **Isolasi & Tuning Pool PHP-FPM (`/etc/php/<ver>/fpm/pool.d/pda.conf`):**
   - Menggunakan dedicated socket `/run/php/php<ver>-fpm-pda.sock` dengan hak akses `listen.mode = 0660`.
   - Penyetelan proses worker `pm = ondemand`, `pm.max_children = 16`, `pm.process_idle_timeout = 10s`, dan `pm.max_requests = 500` guna mendaur ulang memori dan mengeliminasi kebocoran RAM jangka panjang.
   - Alokasi memori `memory_limit = 256M` dan `max_execution_time = 180` untuk kalkulasi bitwise subnetting serta parsing file BIND besar.
   - Penambahan paket dependensi PHP: `php-gmp` dan `php-bcmath` (untuk kalkulasi 128-bit IPv6 bitwise mutakhir) serta `php-zip`.

3. **Otomasi & Hardening MariaDB Database (`deploy/install-debian.sh`):**
   - **Hak Akses Ganda (Dual-Host Privileges):** Otomasi pembuatan user dengan izin untuk `'user'@'localhost'` DAN `'user'@'127.0.0.1'`, mencegah galat _Access Denied_ saat koneksi PDO beralih antara UNIX socket dan jaringan TCP loopback.
   - **Keamanan Database:** Pembersihan user kosong/anonim, penghapusan akses root remote, dan penghapusan database `test`.
   - **Inisialisasi Otomatis:** Deteksi keberadaan tabel metadata dan impor otomatis `sql/schema.sql` saat instalasi awal.

4. **Kompatibilitas PowerDNS Authoritative 4.8+ (`deploy/pdns.snippet.conf`):**
   - Pembersihan parameter usang `recursor=` (core recursor dihapus pada PowerDNS 4.8+) dan rekomendasi delegasi ke Unbound lokal port 5353.
   - Pembersihan parameter `bind-config=` jika menggunakan backend `gmysql` untuk menghindari galat fatal pada PowerDNS server.

---

## 3. Catatan Arsitektur & Operasional Versi 0.1.0 (Initial Modernization)

### A. Format Dokumentasi README & Standardisasi Lokasi

- **Dokumentasi Modern:** Mengadopsi struktur visual, lencana status, hierarki bab, dan diagram request pipeline berbasis Mermaid terinspirasi dari `PHP-BindManager/README.md`.
- **Standardisasi Web Root:** Seluruh file instalasi (`deploy/install-debian.sh`), konfigurasi Nginx (`deploy/nginx.conf`), dan dokumentasi operasional dibakukan pada `/var/www/PowerDNS-Admin-PHP` (menggantikan `/opt/PowerDNS-Admin-PHP`).

### B. Kepatuhan Standar PowerDNS Authoritative API v1 (`diffRrsets`)

- Memperbaiki payload penghapusan RRset (`changetype = "DELETE"`): pada PowerDNS 4.7+, payload penghapusan tidak boleh menyertakan array `records: []` atau komentar agar tidak menghasilkan error HTTP 400/422.

### C. Keamanan & Mitigasi Brute-Force Dual-Axis

- Dual-axis rate limiting pada proses login berbasis tabel `login_attempts` (10 kegagalan IP / 5 kegagalan Username per 15 menit).
- Rotasi token CSRF (`csrfRotate()`) dan regenerasi ID sesi (`session_regenerate_id(true)`) saat login berhasil.
- Password dienkripsi dengan algoritma standar industri **Argon2id** (`PASSWORD_ARGON2ID`).
- API Key dan kredensial PowerDNS disimpan terenkripsi menggunakan **AES-256-GCM** dengan IV acak.

### D. Akses Kontrol Multi-Tenant & Perbaikan IDOR

- Endpoint `/api/v1/zones` memverifikasi hak akses pengguna/token melalui `userCanZone()`, mencegah kebocoran zona milik penyewa (tenant) lain.
- Evaluasi izin eksplisit `zone_user.can_edit` diprioritaskan di atas izin warisan `account_user`.

### E. Optimasi Responsivitas & Tampilan Mobile

- Penguncian skala font `-webkit-text-size-adjust: 100%` untuk mencegah font inflation liar pada peramban Xiaomi / Redmi / Poco (MIUI & HyperOS).
- Dukungan notch dan punch-hole kamera via `viewport-fit=cover` dan CSS `env(safe-area-inset-*)`.
- Pembungkus `<div class="table-responsive">` pada seluruh tabel data di antarmuka web.

### F. Integrasi CI/CD Super-Linter v9 & MegaLinter v10 Full Suite

- **Super-Linter v9 (`.github/workflows/super-linter.yml`):** Menjalankan 14 linter aktif untuk seluruh stack: PHP (PHPCS PSR-12, PHPStan L8, Psalm L7, Built-in lint), CSS (Stylelint), JS (ESLint), Markdown (markdownlint), Shell (ShellCheck, shfmt), JSON, YAML, XML (xmllint), Actions (actionlint), dan Git conflict markers.
- **MegaLinter v10 (`.mega-linter.yml` & `.github/workflows/MegaLinter.yml`):** Dikonfigurasi penuh dengan 17 linter terisolasi 100% bebas dari false-positive. Ekstensi SARIF non-standar dinonaktifkan (`SARIF_REPORTER: false`) guna mencegah crash formatter pada PHPStan, serta DevSkim dinonaktifkan (`VALIDATE_DEVSKIM: false`) untuk mengeliminasi spam laporan yang tidak relevan.
- **Audit Upstream Actions:** Semua action GitHub dikunci pada commit SHA aman dari rilis resmi terbaru (`actions/checkout@v7.0.1`, `super-linter@v9.0.0`, `megalinter@v10.1.0`, `codeql-action@v4.38.2`, `upload-artifact@v7.0.1`).
- **Pembersihan Repositori:** Folder dokumentasi usang/duplikat (`docs/`) telah dibersihkan secara permanen agar repositori ramping dan terpusat pada file dokumentasi akar (`README.md`, `CHANGELOG.md`, `DOCNOTE.md`).

---

## 4. Panduan Verifikasi & Quality Gates

Proyek dilengkapi dengan pengujian mandiri tanpa dependensi PHPUnit eksternal:

```bash
# 1. Jalankan seluruh test suite unit test
php tests/test_rdns_math.php      # Verifikasi kalkulasi matematika rDNS IPv4 & IPv6
php tests/test_rdns_services.php  # Verifikasi batch generator PTR
php tests/test_snapshots.php      # Verifikasi diff atomik snapshot & rollback
php tests/test_bind_parser.php    # Verifikasi parser RFC 1035 BIND zone file
php tests/test_dyndns.php         # Verifikasi protokol DynDNS 2 dan response codes

# 2. Jalankan linter sintaksis PHP
find . -name "*.php" -not -path "*/vendor/*" -exec php -l {} +

# 3. Jalankan pemeriksaan whitespace dan format Git
git diff --check
```
