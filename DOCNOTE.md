# DOCNOTE — PowerDNS-Admin-PHP Architecture, Changes & Operational Notes

## 1. Ikhtisar Arsitektur & Spesifikasi Sistem

PowerDNS-Admin-PHP adalah antarmuka manajemen web native, berkinerja tinggi, dan zero-dependency (tanpa dependensi Python, Node.js, atau Composer runtime bloat) untuk PowerDNS Authoritative Server v1 HTTP API, didukung database metadata MySQL/MariaDB dan frontend Vanilla JavaScript ES6+ serta CSS3 modern.

- **Bahasa & Runtime:** PHP 8.2 s/d PHP 8.5+ (Strict Types `declare(strict_types=1);`, native types, match expressions, throw expressions).
- **Database:** MySQL 8.0+ / MariaDB 10.5+ dengan PDO prepared statements menyeluruh.
- **Backend PowerDNS:** PowerDNS Authoritative Server 4.6.x – 5.2.x+ (REST API v1).
- **Web Server:** Nginx (direkomendasikan) atau Apache 2.4+ dengan FastCGI PHP-FPM.
- **Jalur Web Root Standar:** `/var/www/PowerDNS-Admin-PHP` (menggantikan direktori `/opt`).

---

## 2. Catatan Arsitektur & Operasional Versi 0.2.0 (Advanced Features & Innovations)

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

### F. Penguatan Keamanan Zero-CDN & Header HTTP

1. **Eliminasi Total Ketergantungan CDN:**
   - Header `Content-Security-Policy` di `public/index.php` dan `deploy/nginx.conf` telah membersihkan domain pihak ketiga (`cdn.jsdelivr.net`).
   - Seluruh pustaka JavaScript (jQuery 3.7.1) dan CSS (Bootstrap 5.3.3) disajikan secara lokal dari direktori `public/assets/vendor/`.

2. **Header Keamanan Lengkap:**

   ```http
   Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'
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
