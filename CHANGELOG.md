# CHANGELOG — PowerDNS-Admin-PHP

Semua perubahan penting pada proyek ini didokumentasikan dalam file ini.
Format ini mengikuti panduan [Keep a Changelog](https://keepachangelog.com/id/1.0.0/) dan menganut prinsip Semantic Versioning.

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
