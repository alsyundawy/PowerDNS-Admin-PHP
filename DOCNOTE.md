# DOCNOTE — PowerDNS-Admin-PHP Architecture, Changes & Operational Notes

## 1. Ikhtisar Arsitektur & Spesifikasi Sistem

PowerDNS-Admin-PHP adalah antarmuka manajemen web native, berkinerja tinggi, dan zero-dependency (tanpa dependensi Python, Node.js, atau Composer runtime bloat) untuk PowerDNS Authoritative Server v1 HTTP API, didukung database metadata MySQL/MariaDB dan frontend Vanilla JavaScript ES6+ serta CSS3 modern.

- **Bahasa & Runtime:** PHP 8.2 s/d PHP 8.5+ (Strict Types `declare(strict_types=1);`, native types, match expressions, throw expressions).
- **Database:** MySQL 8.0+ / MariaDB 10.5+ dengan PDO prepared statements menyeluruh.
- **Backend PowerDNS:** PowerDNS Authoritative Server 4.6.x – 5.2.x+ (REST API v1).
- **Web Server:** Nginx (direkomendasikan) atau Apache 2.4+ dengan FastCGI PHP-FPM.
- **Jalur Web Root Standar:** `/var/www/PowerDNS-Admin-PHP` (menggantikan direktori `/opt`).

---

## 2. Catatan Arsitektur & Operasional Versi 0.1.0 (Initial Modernization)

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

## 3. Panduan Verifikasi & Quality Gates

Proyek dilengkapi dengan pengujian mandiri dan linter standar:

```bash
# 1. Jalankan linter sintaksis PHP
find . -name "*.php" -not -path "*/vendor/*" -exec php -l {} +

# 2. Jalankan pemeriksaan PSR-12
/usr/local/bin/phpcs --standard=PSR12 app/ views/ public/

# 3. Jalankan pemeriksaan whitespace dan format Git
git diff --check
```
