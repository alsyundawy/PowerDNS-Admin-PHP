#!/usr/bin/env bash
set -Eeuo pipefail

################################################################################
# PowerDNS-Admin-PHP - Production Debian/Ubuntu Auto-Installer (v0.4.0)
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
		echo -e "\n${RED}[ERROR] Installation aborted due to an error (Exit Code: ${exit_code}).${NC}" >&2
	fi
}
trap cleanup_on_error EXIT

CURRENT_UID="${EUID:-}"
if [[ -z ${CURRENT_UID} ]]; then
	CURRENT_UID="$(id -u)"
fi
if [[ ${CURRENT_UID} -ne 0 ]]; then
	echo -e "${RED}[ERROR] This script must be run as ROOT (sudo).${NC}" >&2
	exit 1
fi

APP="/var/www/PowerDNS-Admin-PHP"
DOMAIN="${DOMAIN:-}"
CERTBOT_EMAIL="${CERTBOT_EMAIL:-${EMAIL:-}}"
DB_ROOT_PASS="${DB_ROOT_PASS:-${MARIADB_ROOT_PASSWORD:-}}"

# Parse command line flags
while [[ $# -gt 0 ]]; do
	case "$1" in
	-d | --domain)
		DOMAIN="$2"
		shift 2
		;;
	-m | --email)
		CERTBOT_EMAIL="$2"
		shift 2
		;;
	--db-root-pass)
		DB_ROOT_PASS="$2"
		shift 2
		;;
	-h | --help)
		echo "Usage: $0 [OPTIONS]"
		echo "Options:"
		echo "  -d, --domain <domain>     Domain name for Nginx server_name & Let's Encrypt (e.g. domain.tld)"
		echo "  -m, --email <email>       Email address for Let's Encrypt certificate alerts"
		echo "      --db-root-pass <pass> MariaDB root password if required"
		echo "  -h, --help                Show this help message"
		exit 0
		;;
	*)
		shift
		;;
	esac
done

echo -e "${CYAN}================================================================================${NC}"
echo -e "${GREEN} PowerDNS-Admin-PHP v0.4.0 — Nginx, PHP-FPM & MariaDB Auto Installer ${NC}"
echo -e "${CYAN}================================================================================${NC}"
if [[ -n ${DOMAIN} ]]; then
	echo -e "${CYAN}Target Domain : ${YELLOW}${DOMAIN}${NC}"
fi

# 1. Update packages & install core dependencies
echo -e "\n${BLUE}[1/7] Updating package repositories and installing system packages...${NC}"
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
echo -e "${GREEN}PHP detected: version ${PHP_VER}${NC}"

# 2. Setup directory structure & file permissions
echo -e "\n${BLUE}[2/7] Preparing application directory and www-data permissions...${NC}"
install -d -o www-data -g www-data "${APP}"
install -d -o www-data -g www-data /etc/pda
chmod 750 /etc/pda

# If script is run inside repo clone, sync to ${APP} if different
CURRENT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
if [[ ${CURRENT_DIR} != "${APP}" && -f "${CURRENT_DIR}/public/index.php" ]]; then
	echo -e "${YELLOW}Copying files from ${CURRENT_DIR} to ${APP}...${NC}"
	cp -r "${CURRENT_DIR}/." "${APP}/"
fi

if [[ ! -f "${APP}/public/index.php" ]]; then
	echo -e "${RED}[ERROR] File ${APP}/public/index.php not found. Ensure repository is installed at ${APP}.${NC}" >&2
	exit 1
fi

chown -R www-data:www-data "${APP}"
find "${APP}" -type d -exec chmod 750 {} +
find "${APP}" -type f -exec chmod 640 {} +

# Ensure deploy scripts remain executable
chmod 755 "${APP}/deploy/"*.sh 2>/dev/null || true

# 3. Configure dedicated PHP-FPM pool [pda]
echo -e "\n${BLUE}[3/7] Configuring isolated PHP-FPM pool (/etc/php/${PHP_VER}/fpm/pool.d/pda.conf)...${NC}"
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

# 4. Configure Nginx Web Server
echo -e "\n${BLUE}[4/7] Configuring Nginx Virtual Host...${NC}"
TARGET_SERVER_NAME="${DOMAIN:-_}"

# Copy template and customize PHP socket & server_name
cp "${APP}/deploy/nginx.conf" /etc/nginx/sites-available/pda.conf
sed -i -E "s#php[0-9.]+-fpm-pda\.sock#php${PHP_VER}-fpm-pda.sock#g" /etc/nginx/sites-available/pda.conf
sed -i "s#__SERVER_NAME__#${TARGET_SERVER_NAME}#g" /etc/nginx/sites-available/pda.conf

ln -sfn /etc/nginx/sites-available/pda.conf /etc/nginx/sites-enabled/pda.conf

# Remove default site to prevent port 80 conflict
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true

nginx -t

# 5. Setup & Hardening MariaDB Database
echo -e "\n${BLUE}[5/7] Initializing MariaDB Database & Privileges...${NC}"
systemctl enable --now mariadb

DB_NAME="${DB_NAME:-pdns_admin}"
DB_USER="${DB_USER:-pdns_admin_user}"

if [[ -z ${DB_PASS:-} ]]; then
	DB_PASS="$(head -c 256 /dev/urandom | tr -dc '2-9a-hj-km-np-zA-HJ-NP-Z' | cut -c1-20)"
fi

# Detect MariaDB root connectivity (unix socket or password)
ROOT_AUTH_CMD=()
if mariadb -u root -e "SELECT 1;" &>/dev/null; then
	ROOT_AUTH_CMD=(mariadb -u root)
elif [[ -n ${DB_ROOT_PASS} ]] && mariadb -u root -p"${DB_ROOT_PASS}" -e "SELECT 1;" &>/dev/null; then
	ROOT_AUTH_CMD=(mariadb -u root "-p${DB_ROOT_PASS}")
elif [[ -t 0 ]]; then
	echo -e "${YELLOW}[!] MariaDB root requires a password.${NC}"
	read -r -s -p "Enter MariaDB root password: " DB_ROOT_PASS
	echo ""
	if mariadb -u root -p"${DB_ROOT_PASS}" -e "SELECT 1;" &>/dev/null; then
		ROOT_AUTH_CMD=(mariadb -u root "-p${DB_ROOT_PASS}")
	fi
fi

if [[ ${#ROOT_AUTH_CMD[@]} -gt 0 ]]; then
	"${ROOT_AUTH_CMD[@]}" <<EOF
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
	echo -e "${GREEN}MariaDB user '${DB_USER}' and database '${DB_NAME}' created successfully.${NC}"
else
	echo -e "${YELLOW}[WARN] Could not authenticate automatically as MariaDB root.${NC}"
	echo -e "${YELLOW}Please create database and user manually using:${NC}"
	echo -e "  mariadb -u root -p -e \"CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}'; GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;\""
fi

# Import metadata table schema if new database and schema does not exist
if mariadb -u "${DB_USER}" -p"${DB_PASS}" -h 127.0.0.1 "${DB_NAME}" -e "DESCRIBE users;" &>/dev/null; then
	echo -e "${GREEN}PowerDNS-Admin-PHP metadata tables already exist in database ${DB_NAME}.${NC}"
elif [[ -f "${APP}/sql/schema.sql" ]] && mariadb -u "${DB_USER}" -p"${DB_PASS}" -h 127.0.0.1 "${DB_NAME}" -e "SELECT 1;" &>/dev/null; then
	echo -e "${GREEN}Importing PowerDNS-Admin-PHP metadata schema (${APP}/sql/schema.sql)...${NC}"
	mariadb -u "${DB_USER}" -p"${DB_PASS}" -h 127.0.0.1 "${DB_NAME}" <"${APP}/sql/schema.sql" || true
fi

# Run v0.4.0 migration if migration file exists
if [[ -f "${APP}/sql/migrations/0.4.0_hardening_expansion.sql" ]] && mariadb -u "${DB_USER}" -p"${DB_PASS}" -h 127.0.0.1 "${DB_NAME}" -e "SELECT 1;" &>/dev/null; then
	mariadb -u "${DB_USER}" -p"${DB_PASS}" -h 127.0.0.1 "${DB_NAME}" <"${APP}/sql/migrations/0.4.0_hardening_expansion.sql" 2>/dev/null || true
fi

# 6. Optional Automated Let's Encrypt (Certbot) TLS Certificate
echo -e "\n${BLUE}[6/7] Automated HTTPS / SSL Setup (Let's Encrypt)...${NC}"
CERTBOT_SUCCESS=false
if [[ -n ${DOMAIN} && ${DOMAIN} != "_" && ${DOMAIN} != "localhost" && ! ${DOMAIN} =~ ^[0-9.]+$ ]]; then
	echo -e "${GREEN}Domain '${DOMAIN}' supplied. Installing Certbot and configuring SSL...${NC}"
	apt-get install -y --no-install-recommends certbot python3-certbot-nginx

	CERTBOT_CMD=("certbot" "--nginx" "-d" "${DOMAIN}" "--non-interactive" "--agree-tos" "--redirect")
	if [[ -n ${CERTBOT_EMAIL} ]]; then
		CERTBOT_CMD+=("-m" "${CERTBOT_EMAIL}")
	else
		CERTBOT_CMD+=("--register-unsafely-without-email")
	fi

	if "${CERTBOT_CMD[@]}"; then
		CERTBOT_SUCCESS=true
		echo -e "${GREEN}Let's Encrypt TLS certificate installed successfully for ${DOMAIN}!${NC}"
	else
		echo -e "${YELLOW}[WARN] Certbot automated verification was unable to complete (DNS may not have propagated yet).${NC}"
		echo -e "${YELLOW}You can obtain the certificate later once DNS resolves by running:${NC}"
		echo -e "  certbot --nginx -d ${DOMAIN}"
	fi
else
	echo -e "${CYAN}No external domain provided (or IP address used). Skipping automated Let's Encrypt.${NC}"
	echo -e "To configure SSL later with a domain, run: ${YELLOW}certbot --nginx -d <your-domain>${NC}"
fi

# 7. Enable, restart and execute healthcheck
echo -e "\n${BLUE}[7/7] Restarting services and performing post-installation health check...${NC}"
systemctl daemon-reload
systemctl enable --now "php${PHP_VER}-fpm" nginx mariadb
systemctl restart "php${PHP_VER}-fpm" nginx

# Execute health-check suite
if [[ -x "${APP}/deploy/healthcheck.sh" ]]; then
	echo -e "\n${CYAN}Running PowerDNS-Admin-PHP Health Check Suite...${NC}"
	DB_USER="${DB_USER}" DB_PASS="${DB_PASS}" DB_NAME="${DB_NAME}" DB_HOST="127.0.0.1" "${APP}/deploy/healthcheck.sh" || true
fi

trap - EXIT

echo ""
echo -e "${GREEN}================================================================================${NC}"
echo -e "${GREEN} POWERDNS-ADMIN-PHP v0.4.0 INSTALLATION COMPLETED SUCCESSFULLY! ${NC}"
echo -e "${GREEN}================================================================================${NC}"
echo ""
echo -e "${CYAN}Database Configuration Information:${NC}"
echo -e "  - DB Host     : ${YELLOW}127.0.0.1${NC}"
echo -e "  - DB Port     : ${YELLOW}3306${NC}"
echo -e "  - DB Name     : ${YELLOW}${DB_NAME}${NC}"
echo -e "  - DB User     : ${YELLOW}${DB_USER}${NC}"
echo -e "  - DB Password : ${YELLOW}${DB_PASS}${NC}"
echo ""
echo -e "${CYAN}Socket & Web Server Status:${NC}"
echo -e "  - Nginx Config: ${YELLOW}/etc/nginx/sites-available/pda.conf${NC}"
echo -e "  - PHP-FPM Sock: ${YELLOW}/run/php/php${PHP_VER}-fpm-pda.sock${NC}"
echo -e "  - Web Directory: ${YELLOW}${APP}/public${NC}"
echo ""
echo -e "${CYAN}Access URL:${NC}"
if [[ ${CERTBOT_SUCCESS} == "true" ]]; then
	echo -e "  - HTTPS URL   : ${GREEN}https://${DOMAIN}/install${NC}"
elif [[ -n ${DOMAIN} && ${DOMAIN} != "_" ]]; then
	echo -e "  - HTTP URL    : ${GREEN}http://${DOMAIN}/install${NC}"
else
	echo -e "  - HTTP URL    : ${GREEN}http://<YOUR_SERVER_IP>/install${NC}"
fi
echo ""
echo -e "${CYAN}Next Steps:${NC}"
echo -e "  1. Point your web browser to the URL above to complete the initial setup."
echo -e "  2. Enter the database credentials above in the web installation wizard."
echo -e "  3. Create your first Administrator account."
echo ""
