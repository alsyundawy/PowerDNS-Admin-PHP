#!/bin/bash
set -euo pipefail
APP=/var/www/PowerDNS-Admin-PHP
if [[ ${EUID} -ne 0 ]]; then
    echo "Jalankan sebagai root."
    exit 1
fi
apt-get update
apt-get install -y nginx mariadb-server php-fpm php-mysql php-curl php-mbstring php-xml php-intl
PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
install -d -o www-data -g www-data "${APP}"
if [[ ! -f "${APP}/public/index.php" ]]; then
    echo "Salin isi repo ke ${APP} dulu."
    exit 1
fi
chown -R www-data:www-data "${APP}"
find "${APP}" -type d -exec chmod 750 {} \;
find "${APP}" -type f -exec chmod 640 {} \;
install -d -o www-data -g www-data /etc/pda
if [[ ! -f "/etc/php/${PHP_VER}/fpm/pool.d/pda.conf" ]]; then
    cat >"/etc/php/${PHP_VER}/fpm/pool.d/pda.conf" <<EOF
[pda]
user = www-data
group = www-data
listen = /run/php/php${PHP_VER}-fpm-pda.sock
listen.owner = www-data
listen.group = www-data
pm = ondemand
pm.max_children = 8
php_admin_value[expose_php] = Off
EOF
fi
sed -i "s#php8.3-fpm-pda.sock#php${PHP_VER}-fpm-pda.sock#" "${APP}/deploy/nginx.conf" || true
cp "${APP}/deploy/nginx.conf" /etc/nginx/sites-available/pda.conf
ln -sfn /etc/nginx/sites-available/pda.conf /etc/nginx/sites-enabled/pda.conf
nginx -t
systemctl enable --now "php${PHP_VER}-fpm" nginx mariadb
echo "Buat database pda dan user MySQL, lalu buka http://server/install"
