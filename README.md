# PowerDNS-Admin-PHP

Panel native PHP untuk PowerDNS Authoritative. Tanpa Python, tanpa Node, tanpa framework.

Record tidak disimpan di database panel. PowerDNS tetap sumber kebenaran, diubah lewat HTTP API resmi.

## Dasar yang diverifikasi

- Zona API: https://doc.powerdns.com/authoritative/http-api/zone.html
- Cryptokey API: https://doc.powerdns.com/authoritative/http-api/cryptokey.html
- Pencarian: https://doc.powerdns.com/authoritative/http-api/search.html
- Statistik: https://doc.powerdns.com/authoritative/http-api/statistics.html
- Metadata SOA-EDIT-API: https://doc.powerdns.com/authoritative/domainmetadata.html dan https://doc.powerdns.com/authoritative/dnsupdate.html
- Setting API: https://doc.powerdns.com/authoritative/settings.html
- Bootstrap 5.3.8 di-vendor dari jsDelivr rilis resmi. jQuery 3.7.1 di-vendor.
- Jenis zona resmi: Native, Master, Slave, Producer, Consumer.
- Nama zona wajib canonical, berakhiran titik. Klien tidak mengirim `serial` atau `notified_serial`.
- `DELETE` RRset tidak mengirim TTL. `REPLACE` mengirim TTL.
- Isu upstream #842: enable DNSSEC yang hanya mengirim `keytype=ksk` bisa menghasilkan CSK. Panel ini meminta pilihan CSK atau KSK+ZSK secara eksplisit, algoritma `ecdsa256`.

## Yang ada di build ini

- Instalasi web pertama, login Argon2id, rate limit 8 gagal per IP / 15 menit.
- CSRF, cookie HttpOnly dan SameSite Strict, CSP tanpa CDN.
- Dasbor statistik PowerDNS.
- Sinkron cache zona, buat, hapus, editor RRset, template `[ZONE]`.
- NOTIFY, AXFR retrieve, DNSSEC tanpa menampilkan kunci privat.
- Akun, pengguna, pemberian akses zona, API key panel ter-hash, audit, pencarian `search-data`.
- API key PowerDNS dienkripsi AES-256-GCM.

## Pasang di Debian atau Ubuntu

```bash
sudo apt-get install nginx mariadb-server php-fpm php-mysql php-curl php-mbstring php-xml php-intl
sudo mysql -e "CREATE DATABASE pda CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER 'pda'@'127.0.0.1' IDENTIFIED BY 'sandi-panjang'; GRANT ALL ON pda.* TO 'pda'@'127.0.0.1';"
sudo rsync -a ./ /opt/PowerDNS-Admin-PHP/
sudo chown -R www-data:www-data /opt/PowerDNS-Admin-PHP
```

Arahkan Nginx `root` ke `/opt/PowerDNS-Admin-PHP/public`. Contoh ada di `deploy/nginx.conf`. Cuplikan PowerDNS ada di `deploy/pdns.snippet.conf`.

Buka `/install`, isi database panel dan URL API `http://127.0.0.1:8081`. Setelah itu masuk lewat `/login`.

## API panel

Header `X-API-Key` adalah key panel, bukan key PowerDNS.

- `GET /api/v1/zones`
- `GET /api/v1/zones/{nama}`

## Batas sadar

LDAP, SAML, OAuth, dan DynDNS 2 belum masuk. Editor memuat RRset zona sekaligus, jadi zona sangat besar perlu filter server-side pada iterasi berikutnya.
