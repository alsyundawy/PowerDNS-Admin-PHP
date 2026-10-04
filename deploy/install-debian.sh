#!/usr/bin/env bash
set -Eeuo pipefail

################################################################################
# PowerDNS-Admin-PHP - Production Debian/Ubuntu Auto-Installer
# ==============================================================================
# Stack       : Nginx + PHP-FPM (8.2+) + MariaDB (10.5+) + PowerDNS REST API
# Support     : Ubuntu 20.04 (Focal), 22.04 (Jammy), 24.04 (Noble)
#               Debian 11 (Bullseye), 12 (Bookworm), 13 (Trixie)
# Web Root    : /var/www/PowerDNS-Admin-PHP
# License     : MIT
################################################################################

export DEBIAN_FRONTEND=noninteractive

RED='\033[1;31m'
GREEN='\033[1;32m'
YELLOW='\033[1;33m'
BLUE='\033[1;34m'
CYAN='\033[1;36m'
NC='\033[0m'

cleanup_on_error() {
	local exit_code=$?
	if [[ ${exit_code} -ne 0 ]]; then
		echo -e "\n${RED}[ERROR] Instalasi terhenti karena terjadi kesalahan (Exit Code: ${exit_code}).${NC}" >&2
	fi
}
trap cleanup_on_error EXIT

CURRENT_UID="${EUID:-}"
if [[ -z ${CURRENT_UID} ]]; then
	CURRENT_UID="$(id -u)"
fi
if [[ ${CURRENT_UID} -ne 0 ]]; then
	echo -e "${RED}[ERROR] Skrip ini harus dijalankan sebagai ROOT (sudo).${NC}" >&2
	exit 1
fi

APP="/var/www/PowerDNS-Admin-PHP"

echo -e "${CYAN}================================================================================${NC}"
echo -e "${GREEN} PowerDNS-Admin-PHP - Nginx, PHP-FPM & MariaDB Auto Installer ${NC}"
echo -e "${CYAN}================================================================================${NC}"

# 1. Update paket & instalasi dependensi core
echo -e "\n${BLUE}[1/6] Memperbarui repositori dan menginstal paket sistem...${NC}"
apt-get update -y
apt-get install -y --no-install-recommends \
	nginx-full \
	mariadb-server \
	mariadb-client \
	curl \
	git \
	unzip \
	ca-certificates \
	php-fpm \
	php-mysql \
	php-curl \
	php-mbstring \
	php-xml \
	php-intl \
	php-gmp \
	php-bcmath \
	php-zip

PHP_VER=""
if command -v php &>/dev/null; then
	PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)"
fi
if [[ -z ${PHP_VER:-} && -d "/etc/php" ]]; then
	LATEST_FPM="$(find /etc/php -maxdepth 2 -type d -name "fpm" 2>/dev/null | sort -V | tail -n1)"
	if [[ -n ${LATEST_FPM} ]]; then
		PHP_VER="$(basename "$(dirname "${LATEST_FPM}")")"
	fi
fi
if [[ -z ${PHP_VER:-} ]]; then
	PHP_VER="8.2"
fi
echo -e "${GREEN}PHP terdeteksi: versi ${PHP_VER}${NC}"

# 2. Setup struktur direktori & hak akses berkas
echo -e "\n${BLUE}[2/6] Mempersiapkan direktori aplikasi dan izin akses www-data...${NC}"
install -d -o www-data -g www-data "${APP}"
install -d -o www-data -g www-data /etc/pda
chmod 750 /etc/pda

# Jika script dijalankan dari dalam clone repo, sinkronkan ke ${APP} jika berbeda
CURRENT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
if [[ ${CURRENT_DIR} != "${APP}" && -f "${CURRENT_DIR}/public/index.php" ]]; then
	echo -e "${YELLOW}Menyalin berkas dari ${CURRENT_DIR} ke ${APP}...${NC}"
	cp -r "${CURRENT_DIR}/." "${APP}/"
fi

if [[ ! -f "${APP}/public/index.php" ]]; then
	echo -e "${RED}[ERROR] Berkas ${APP}/public/index.php tidak ditemukan. Pastikan repo terpasang di ${APP}.${NC}" >&2
	exit 1
fi

chown -R www-data:www-data "${APP}"
find "${APP}" -type d -exec chmod 750 {} +
find "${APP}" -type f -exec chmod 640 {} +

# Pastikan script deploy tetap executable
chmod 755 "${APP}/deploy/"*.sh 2>/dev/null || true

# 3. Konfigurasi dedicated PHP-FPM pool [pda]
echo -e "\n${BLUE}[3/6] Mengonfigurasi PHP-FPM pool isolated (/etc/php/${PHP_VER}/fpm/pool.d/pda.conf)...${NC}"
cat >"/etc/php/${PHP_VER}/fpm/pool.d/pda.conf" <<EOF
[pda]
user = www-data
group = www-data
listen = /run/php/php${PHP_VER}-fpm-pda.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = ondemand
pm.max_children = 16
pm.process_idle_timeout = 10s
pm.max_requests = 500

php_admin_value[expose_php] = Off
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 64M
php_admin_value[post_max_size] = 64M
php_admin_value[max_execution_time] = 180
php_admin_value[max_input_time] = 180
php_admin_flag[display_errors] = Off
php_admin_flag[log_errors] = On
EOF

mkdir -p /run/php
ln -sfn "/run/php/php${PHP_VER}-fpm-pda.sock" /run/php/php-fpm-pda.sock

# 4. Konfigurasi Nginx Web Server
echo -e "\n${BLUE}[4/6] Mengonfigurasi Nginx Reverse Proxy...${NC}"
# Sesuaikan versi socket PHP-FPM pada deploy/nginx.conf jika ada
sed -i -E "s#php[0-9.]+-fpm-pda\.sock#php${PHP_VER}-fpm-pda.sock#g" "${APP}/deploy/nginx.conf"

cp "${APP}/deploy/nginx.conf" /etc/nginx/sites-available/pda.conf
ln -sfn /etc/nginx/sites-available/pda.conf /etc/nginx/sites-enabled/pda.conf

# Hapus default site untuk menghindari konflik port 80
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true

nginx -t

# 5. Setup & Hardening MariaDB Database
echo -e "\n${BLUE}[5/6] Menginisialisasi MariaDB Database & Privileges...${NC}"
systemctl enable --now mariadb

DB_NAME="${DB_NAME:-pdns_admin}"
DB_USER="${DB_USER:-pdns_admin_user}"

if [[ -z ${DB_PASS:-} ]]; then
	DB_PASS="$(head -c 256 /dev/urandom | tr -dc '2-9a-hj-km-np-zA-HJ-NP-Z' | cut -c1-20)"
fi

mariadb -u root <<EOF || { echo -e "${YELLOW}[WARN] Tidak dapat menjalankan inisialisasi root MariaDB otomatis (mungkin root ber-password). Silakan buat database manual.${NC}"; }
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\_%';
FLUSH PRIVILEGES;
EOF

# Impor skema tabel metadata jika database baru dan skema belum ada
if mariadb -u "${DB_USER}" -p"${DB_PASS}" -h 127.0.0.1 "${DB_NAME}" -e "DESCRIBE users;" &>/dev/null; then
	echo -e "${GREEN}Tabel metadata PowerDNS-Admin-PHP sudah ada di database ${DB_NAME}.${NC}"
elif [[ -f "${APP}/sql/schema.sql" ]]; then
	echo -e "${GREEN}Mengimpor skema metadata PowerDNS-Admin-PHP (${APP}/sql/schema.sql)...${NC}"
	mariadb -u "${DB_USER}" -p"${DB_PASS}" -h 127.0.0.1 "${DB_NAME}" <"${APP}/sql/schema.sql" || true
fi

# 6. Mengaktifkan dan merestart seluruh service
echo -e "\n${BLUE}[6/6] Memulai ulang layanan Nginx dan PHP-FPM...${NC}"
systemctl daemon-reload
systemctl enable --now "php${PHP_VER}-fpm" nginx mariadb
systemctl restart "php${PHP_VER}-fpm" nginx

trap - EXIT

echo ""
echo -e "${GREEN}================================================================================${NC}"
echo -e "${GREEN} INSTALASI POWERDNS-ADMIN-PHP BERHASIL SELESAI! ${NC}"
echo -e "${GREEN}================================================================================${NC}"
echo ""
echo -e "${CYAN}Informasi Konfigurasi Database:${NC}"
echo -e "  - DB Host     : ${YELLOW}127.0.0.1${NC}"
echo -e "  - DB Port     : ${YELLOW}3306${NC}"
echo -e "  - DB Name     : ${YELLOW}${DB_NAME}${NC}"
echo -e "  - DB User     : ${YELLOW}${DB_USER}${NC}"
echo -e "  - DB Password : ${YELLOW}${DB_PASS}${NC}"
echo ""
echo -e "${CYAN}Status Socket & Web Server:${NC}"
echo -e "  - Nginx Config: ${YELLOW}/etc/nginx/sites-available/pda.conf${NC}"
echo -e "  - PHP-FPM Sock: ${YELLOW}/run/php/php${PHP_VER}-fpm-pda.sock${NC}"
echo -e "  - Web Directory: ${YELLOW}${APP}/public${NC}"
echo ""
echo -e "${CYAN}Langkah Selanjutnya:${NC}"
echo -e "  1. Arahkan browser Anda ke IP server atau Domain Anda untuk menyelesaikan setup:"
echo -e "     ${GREEN}http://<IP_SERVER_ANDA>/install${NC}"
echo -e "  2. Masukkan parameter kredensial database di atas pada wizard instalasi web."
echo -e "  3. Buat akun Administrator pertama Anda."
echo ""
