#!/usr/bin/env bash
#
# Sets up the project on a clean Ubuntu 22.04 / 24.04 server:
# PHP 8.3, MySQL, Node.js 22, nginx, cron for the daily sync.
# Run from the project directory:   sudo ./setup.sh
#
# Can be configured with environment variables:
#   APP_URL      default http://localhost
#   DB_NAME      default sheets_sync
#   DB_USER      default sheets_sync
#   DB_PASS      default random
#   SEED=0       skip the example configuration
#   NGINX=0      don't configure nginx (use `php artisan serve` instead)

set -euo pipefail

PHP_VERSION=8.3
APP_DIR="$(cd "$(dirname "$0")" && pwd)"
APP_URL="${APP_URL:-http://localhost}"
DB_NAME="${DB_NAME:-sheets_sync}"
DB_USER="${DB_USER:-sheets_sync}"
DB_PASS="${DB_PASS:-$(head -c 32 /dev/urandom | base64 | tr -dc 'a-zA-Z0-9' | head -c 20)}"
SEED="${SEED:-1}"
NGINX="${NGINX:-1}"

step() { echo -e "\n\033[1;34m==> $*\033[0m"; }

if [ "$(id -u)" -ne 0 ]; then
    echo "Please run as root: sudo $0"
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive

step "Installing system packages"
apt-get update -q
apt-get install -yq software-properties-common ca-certificates curl unzip git cron
add-apt-repository -y ppa:ondrej/php
apt-get update -q
apt-get install -yq mysql-server \
    php${PHP_VERSION}-cli php${PHP_VERSION}-fpm php${PHP_VERSION}-mysql php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-xml php${PHP_VERSION}-curl php${PHP_VERSION}-zip php${PHP_VERSION}-bcmath php${PHP_VERSION}-intl
[ "$NGINX" = "1" ] && apt-get install -yq nginx

if ! command -v composer > /dev/null; then
    step "Installing composer"
    EXPECTED="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    ACTUAL="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    if [ "$EXPECTED" != "$ACTUAL" ]; then
        echo "Composer installer checksum mismatch"
        exit 1
    fi
    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm /tmp/composer-setup.php
fi

# Vite needs Node 20.19+, Ubuntu's own package is older
if ! command -v node > /dev/null || [ "$(node -p 'process.versions.node.split(".")[0]')" -lt 22 ]; then
    step "Installing Node.js 22"
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    apt-get install -yq nodejs
fi

step "Starting services"
service mysql start
service cron start
service php${PHP_VERSION}-fpm start

step "Creating database"
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

step "Configuring application"
cd "$APP_DIR"
[ -f .env ] || cp .env.example .env

set_env() {
    if grep -q "^$1=" .env; then
        sed -i "s|^$1=.*|$1=$2|" .env
    else
        echo "$1=$2" >> .env
    fi
}

set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_URL "$APP_URL"
set_env DB_CONNECTION mysql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env DB_DATABASE "$DB_NAME"
set_env DB_USERNAME "$DB_USER"
set_env DB_PASSWORD "$DB_PASS"

export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction

step "Building frontend"
npm ci --no-audit --no-fund
npm run build

grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
php artisan migrate --force
[ "$SEED" = "1" ] && php artisan db:seed --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

mkdir -p storage/app/private/google
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

step "Scheduling daily sync"
CRON_LINE="* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1"
( crontab -u www-data -l 2>/dev/null | grep -v 'artisan schedule:run' || true; echo "$CRON_LINE" ) | crontab -u www-data -

if [ "$NGINX" = "1" ]; then
    step "Configuring nginx"
    cat > /etc/nginx/sites-available/sheets-sync <<NGINX
server {
    listen 80 default_server;
    server_name _;
    root ${APP_DIR}/public;
    index index.php;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
NGINX
    ln -sf /etc/nginx/sites-available/sheets-sync /etc/nginx/sites-enabled/sheets-sync
    rm -f /etc/nginx/sites-enabled/default
    nginx -t
    service nginx restart
fi

step "Done"
echo "App:           ${APP_URL}"
echo "Database:      ${DB_NAME} (user ${DB_USER}, password ${DB_PASS})"
echo "Credentials:   put the service account key to ${APP_DIR}/storage/app/private/google/service-account.json"
echo "               and run: sudo -u www-data php artisan sheets:sync"
