# Rencana Implementasi: Generator Reverse DNS (rDNS) IPv4 /24 & IPv6 /64 Serta Sinkronisasi Otomatis Auto-PTR

> **Untuk Pekerja Agentik:** REQUIRED SUB-SKILL: Gunakan `superpowers:subagent-driven-development` atau `superpowers:executing-plans` untuk mengeksekusi rencana ini tugas demi tugas. Setiap langkah menggunakan sintaks checkbox (`- [ ]`) untuk pelacakan progres.

**Goal:** Membangun modul manajemen Reverse DNS (rDNS) lengkap dan presisi untuk subnet IPv4 `/24` dan IPv6 `/64` (RFC 1035 & RFC 3596 nibble format), mencakup pembuatan zona reverse otomatis, generator record PTR massal/manual berbasis template pola, sinkronisasi otomatis dua arah (Forward `A`/`AAAA` $\rightarrow$ Reverse `PTR`), serta wizard web mandiri bertema Visual Subnet Calculator.

**Arsitektur:** Mengintegrasikan logika kanonikal IPv4/IPv6 langsung ke basis fungsional native PHP ([`app/dns_name.php`](file:///Users/alsyundawy/Downloads/GitHub/PowerDNS-Admin-PHP/app/dns_name.php) & [`app/services.php`](file:///Users/alsyundawy/Downloads/GitHub/PowerDNS-Admin-PHP/app/services.php)) tanpa pustaka eksternal pihak ketiga (0 Composer runtime bloat). Menghubungkan mutasi record forward pada [`handleZoneSave()`](file:///Users/alsyundawy/Downloads/GitHub/PowerDNS-Admin-PHP/app/handlers.php) untuk secara otomatis mendeteksi keberadaan zona reverse lokal dan memperbarui RRSet PTR secara atomik melalui [`PdnsClient::patchRrsets()`](file:///Users/alsyundawy/Downloads/GitHub/PowerDNS-Admin-PHP/app/PdnsClient.php). Menyediakan antarmuka visual mandiri di rute `/tools/rdns`.

**Tech Stack:** Native PHP 8.2+ (ekstensi `filter`, `openssl`), MySQL/MariaDB PDO, PowerDNS Authoritative HTTP API v1, Vanilla JavaScript ES6+, Vanilla CSS (Dark/Light responsive theme).

---

## 📐 Spesifikasi & Dasar Matematis RFC

### 1. Reverse IPv4 Subnet `/24` (RFC 1035)

- **Format Zona**: `$C.$B.$A.in-addr.arpa.`
  - Contoh Subnet: `192.0.2.0/24` $\rightarrow$ Zona: `2.0.192.in-addr.arpa.`
- **Nama Record PTR Relatif**: `$D` (Oktet ke-4)
  - Contoh Host: `192.0.2.15`
  - Nama Relatif: `15` (atau FQDN: `15.2.0.192.in-addr.arpa.`)
  - Target Konten: `mail.example.com.`
- **Rentang Host Otomatis**: `.1` s/d `.254` (atau `.0` s/d `.255`).

### 2. Reverse IPv6 Subnet `/64` (RFC 3596 Nibble Format)

- **Format Zona**: 16 nibble pertama dari 64-bit prefix dibalik dan dipisahkan titik (`.ip6.arpa.`).
  - Contoh Subnet: `2001:db8:1234:5678::/64`
  - Ekspansi 64-bit Hex: `20010db812345678`
  - Dibalik per nibble: `8.7.6.5.4.3.2.1.8.b.d.0.1.0.0.2.ip6.arpa.`
- **Nama Record PTR Relatif**: 16 nibble host (64-bit interface identifier) dibalik.
  - Contoh Host: `2001:db8:1234:5678::1`
  - Host 64-bit: `0000000000000001`
  - Dibalik: `1.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0`
  - Full FQDN: `1.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.8.7.6.5.4.3.2.1.8.b.d.0.1.0.0.2.ip6.arpa.`
- **Rentang Host Otomatis**: Sekuensial desimal/heksadesimal (misal `::1` s/d `::64` atau `::100`).

---

## 📋 Batasan Global (Invariants)

1. **Zero Framework & Zero Composer Runtime**: Seluruh perhitungan IP, subnet parsing, dan kalkulasi nibble IPv6 wajib murni menggunakan fungsi bawaan PHP (`inet_pton`, `inet_ntop`, `filter_var`, `pack`, `unpack`).
2. **Atomic PowerDNS Mutex**: Semua mutasi record PTR massal harus memanfaatkan single atomic payload `PATCH /zones/{zone}` via `diffRrsets()` / `patchRrsets()`.
3. **Fail-Safe Authorization**: Auto-PTR hanya boleh mengeksekusi mutasi pada zona reverse jika user yang sedang login memiliki hak otorisasi edit (`userCanZone($user, $revZone, true)`).
4. **Purity of Single Source of Truth**: Data record PTR tidak disimpan di tabel MySQL panel; PowerDNS daemon adalah satu-satunya penyimpan kebenaran record.

---

## 🗂️ Rincian Tugas (Task Breakdown)

### Tugas 1: Algoritma Matematis IPv4 /24 & IPv6 /64 di `app/dns_name.php`

**Berkas yang Disentuh:**

- Ubah: [`app/dns_name.php`](file:///Users/alsyundawy/Downloads/GitHub/PowerDNS-Admin-PHP/app/dns_name.php)
- Uji: `tests/test_rdns_math.php` (skrip verifikasi unit standalone)

**Fungsi yang Dihasilkan:**

- `ipv4ToReverseZone(string $ipOrSubnet): ?string`
- `ipv4ToPtrName(string $ip): ?string`
- `ipv6Expand(string $ipv6): ?string`
- `ipv6ToReverseZone64(string $ipv6OrPrefix): ?string`
- `ipv6ToPtrName(string $ipv6): ?string`
- `ipv6RelativePtr64(string $ipv6): ?string`

- [x] **Langkah 1: Tulis skrip verifikasi unit yang gagal (Red Phase)**
  Buat berkas `tests/test_rdns_math.php` untuk memvalidasi:
  - `192.0.2.0/24` $\rightarrow$ `2.0.192.in-addr.arpa.`
  - `192.0.2.15` $\rightarrow$ `15.2.0.192.in-addr.arpa.` (relatif: `15`)
  - `2001:db8:1234:5678::/64` $\rightarrow$ `8.7.6.5.4.3.2.1.8.b.d.0.1.0.0.2.ip6.arpa.`
  - `2001:db8:1234:5678::1` $\rightarrow$ relative: `1.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0`
- [x] **Langkah 2: Jalankan skrip uji dan pastikan error (Red)**

  ```bash
  php tests/test_rdns_math.php
  ```

- [x] **Langkah 3: Terapkan implementasi minimal di `app/dns_name.php`**
  Implementasikan logika parsing IPv4 dan ekspansi 32-nibble IPv6 via `inet_pton` + `unpack('H*', ...)`.
- [x] **Langkah 4: Jalankan kembali skrip uji dan pastikan lolos (Green)**

  ```bash
  php tests/test_rdns_math.php
  ```

---

### Tugas 2: Service Layer Sinkronisasi PTR & Deteksi Zona di `app/services.php`

**Berkas yang Disentuh:**

- Ubah: [`app/services.php`](file:///Users/alsyundawy/Downloads/GitHub/PowerDNS-Admin-PHP/app/services.php)
- Uji: `tests/test_rdns_services.php`

**Fungsi yang Dihasilkan:**

- `findMatchingReverseZone(string $ip): ?string`
  Mencari apakah zona reverse untuk IP tersebut terdaftar dalam database panel `zones`.
- `buildPtrRrset(string $ip, string $targetHostname, int $ttl = 3600): ?array`
  Menghasilkan struktur array RRSet PowerDNS v1 siap `PATCH`.
- `generateSubnetPtrBatch(string $subnet, string $pattern, int $ttl = 3600, int $start = 1, int $end = 254): array`
  Membangun daftar record PTR sekuensial berdasarkan makro template (misal `host-[ID].domain.com.` atau `ip-[IP].domain.com.`).

- [x] **Langkah 1: Tulis skrip verifikasi fungsi service**
- [x] **Langkah 2: Terapkan fungsi pembantu di `app/services.php`**
- [x] **Langkah 3: Jalankan verifikasi dan pastikan sukses tanpa error sintaks**

---

### Tugas 3: Hook Mutasi Auto-PTR Dua Arah di `app/handlers.php`

**Berkas yang Disentuh:**

- Ubah: [`app/handlers.php`](file:///Users/alsyundawy/Downloads/GitHub/PowerDNS-Admin-PHP/app/handlers.php) (di dalam `handleZoneSave` & `executeZoneCreation`)

**Mekanisme Kerja:**

1. Saat user menyimpan zona forward di [`handleZoneSave()`](file:///Users/alsyundawy/Downloads/GitHub/PowerDNS-Admin-PHP/app/handlers.php#L460):
   - Deteksi baris yang bertipe `A` atau `AAAA`.
   - Periksa apakah checkbox `auto_ptr_sync` aktif (default `1`).
   - Identifikasi apakah zona reverse bersangkutan (`*.in-addr.arpa.` atau `*.ip6.arpa.`) ada di PowerDNS.
   - Jika ada dan user memiliki izin edit, otomatis kirim mutasi `PTR` ke zona reverse tersebut.
   - Buat log audit: `audit($user, 'auto-ptr-sync', $revZone, "$ip -> $fqdn")`.

- [x] **Langkah 1: Tambahkan opsi hook `syncAutoPtrForRows()` di `app/handlers.php`**
- [x] **Langkah 2: Uji keamanan otorisasi (memastikan user non-admin tidak dapat mengubah zona reverse milik orang lain)**

---

### Tugas 4: Controller & Rute Wizard Mandiri `/tools/rdns`

**Berkas yang Disentuh:**

- Ubah: [`public/index.php`](file:///Users/alsyundawy/Downloads/GitHub/PowerDNS-Admin-PHP/public/index.php) (tambahkan rute `/tools/rdns`)
- Ubah: [`app/handlers.php`](file:///Users/alsyundawy/Downloads/GitHub/PowerDNS-Admin-PHP/app/handlers.php) (buat handler `handleRdnsTool(array $user)`)

**Endpoint & Aksi:**

1. `GET /tools/rdns`: Menampilkan dasbor wizard subnet generator.
2. `POST /tools/rdns/create-zone`: Membuat zona reverse baru (`/24` IPv4 atau `/64` IPv6) di PowerDNS lengkap dengan SOA & NS.
3. `POST /tools/rdns/generate-ptr`: Menghasilkan dan menyuntikkan record PTR sekuensial massal ke zona reverse terpilih.
4. `POST /tools/rdns/scan-forward`: Memindai seluruh zona forward lokal untuk mendeteksi `A`/`AAAA` yang berada di subnet target dan mengisi record PTR secara otomatis.

- [x] **Langkah 1: Tambahkan handler dan rute dengan proteksi CSRF dan otorisasi role (`admin` / `operator`)**
- [x] **Langkah 2: Uji endpoint cURL dan validasi response**

---

### Tugas 5: Tampilan Antarmuka Visual Subnet Calculator di `views/tools_rdns.php`

**Berkas yang Dibuat:**

- Buat: [`views/tools_rdns.php`](file:///Users/alsyundawy/Downloads/GitHub/PowerDNS-Admin-PHP/views/tools_rdns.php)
- Ubah: [`views/layout.php`](file:///Users/alsyundawy/Downloads/GitHub/PowerDNS-Admin-PHP/views/layout.php) (tambahkan tautan menu "Subnet rDNS" di navbar)

**Komponen Antarmuka:**

1. **Interactive Subnet Calculator Input Card**:
   - Tab pemilih: **IPv4 (/24)** vs **IPv6 (/64)**.
   - Input subnet interaktif (misal `192.0.2.0/24` atau `2001:db8:1234:5678::/64`).
   - Kartu telemetri live preview kalkulasi kanonikal zona reverse:
     - Output: `2.0.192.in-addr.arpa.` / `8.7.6.5.4.3.2.1.8.b.d.0.1.0.0.2.ip6.arpa.`
2. **Tab 1: Buat Zona Reverse Baru**:
   - Pilihan Jenis Zona (`Native` / `Master`), Nameserver default, dan Akun Tenant.
   - Tombol: **"Buat Zona Reverse di PowerDNS"**.
3. **Tab 2: Generator PTR Massal (Batch Template)**:
   - Pola Penamaan:
     - `ip-[IP_DASH].[DOMAIN]` (misal `ip-192-0-2-10.example.com.`)
     - `host-[ID].[DOMAIN]` (misal `host-10.example.com.`)
     - Custom format makro.
   - Rentang Start $\rightarrow$ End.
   - Tombol: **"Tinjau & Terapkan PTR ke PowerDNS"**.
4. **Tab 3: Sinkronisasi dari Zona Forward (Auto-Populate)**:
   - Tombol: **"Pindai Record A/AAAA Aktif"**. Menampilkan tabel preview mapping sebelum disimpan.

- [x] **Langkah 1: Buat template `views/tools_rdns.php` dengan estetika dark mode dan responsif notch**
- [x] **Langkah 2: Tambahkan item menu di navigasi header `views/layout.php`**

---

### Tugas 6: Verifikasi Kualitas 13 Pilar, Linters & Uji End-to-End

**Langkah Verifikasi:**

- [x] **Langkah 1: Jalankan `php -l` di seluruh berkas proyek**
- [x] **Langkah 2: Jalankan PHPStan Level 5 & Psalm Type Inference**
- [x] **Langkah 3: Jalankan PHP-CS-Fixer dry-run (memastikan PSR-12 ketat)**
- [x] **Langkah 4: Jalankan verifikasi ShellCheck dan Git whitespace**

---

## 🚀 Konfirmasi & Pilihan Eksekusi

Rencana di atas telah disusun secara presisi dan siap diimplementasikan. Tersedia dua metode eksekusi:

1. **Subagent-Driven (Direkomendasikan)**: Menugaskan subagent mandiri per tugas secara bertahap, melakukan review di setiap checkpoint dengan iterasi cepat.
2. **Inline Execution**: Menjalankan setiap tugas langsung dalam sesi ini secara terstruktur dengan verifikasi red-green di setiap langkah.

Pendekatan mana yang Anda kehendaki untuk mulai dieksekusi?
