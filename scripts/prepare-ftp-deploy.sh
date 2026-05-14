#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="${BUILD_DIR:-$ROOT/.deploy/petsocial}"
APP_DIR="$BUILD_DIR/laravel"
APP_URL="${APP_URL:-https://petsocial.prus.dev}"
REMOTE_BASE="${REMOTE_BASE:-/home/anjdiiaev/domains/prus.dev/public_html/petsocial}"
DB_DATABASE="${DB_DATABASE:-$REMOTE_BASE/laravel/database/database.sqlite}"
PUBLIC_DISK_ROOT="${PUBLIC_DISK_ROOT:-$REMOTE_BASE/storage}"
PUBLIC_DISK_URL="${PUBLIC_DISK_URL:-$APP_URL/storage}"
INCLUDE_SQLITE="${INCLUDE_SQLITE:-false}"
APP_KEY="${APP_KEY:-}"

if [[ -z "$APP_KEY" && -f "$ROOT/.env" ]]; then
    APP_KEY="$(sed -n 's/^APP_KEY=//p' "$ROOT/.env" | tail -n 1)"
    APP_KEY="${APP_KEY%\"}"
    APP_KEY="${APP_KEY#\"}"
fi

if [[ -z "$APP_KEY" ]]; then
    echo "APP_KEY is required. Set APP_KEY or create a local .env before preparing a deployment." >&2
    exit 2
fi

if [[ ! -d "$ROOT/vendor" ]]; then
    echo "vendor/ is missing. Run composer install --no-dev --optimize-autoloader first." >&2
    exit 2
fi

if [[ ! -f "$ROOT/public/build/manifest.json" ]]; then
    echo "public/build/manifest.json is missing. Run npm run build first." >&2
    exit 2
fi

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"

rsync -a --delete "$ROOT/public/" "$BUILD_DIR/" \
    --exclude 'storage' \
    --exclude 'hot'

cp "$ROOT/deploy/shared-hosting/index.php" "$BUILD_DIR/index.php"
cp "$ROOT/deploy/shared-hosting/.htaccess" "$BUILD_DIR/.htaccess"

mkdir -p "$BUILD_DIR/storage"
cp "$ROOT/deploy/shared-hosting/public-storage.htaccess" "$BUILD_DIR/storage/.htaccess"

mkdir -p "$APP_DIR"

for path in app bootstrap config lang resources routes vendor; do
    rsync -a --delete "$ROOT/$path/" "$APP_DIR/$path/"
done

rsync -a --delete "$ROOT/database/" "$APP_DIR/database/" \
    --exclude '*.sqlite' \
    --exclude '*.sqlite-*'

cp "$ROOT/artisan" "$APP_DIR/artisan"
cp "$ROOT/composer.json" "$APP_DIR/composer.json"
cp "$ROOT/composer.lock" "$APP_DIR/composer.lock"
cp "$ROOT/deploy/shared-hosting/laravel.htaccess" "$APP_DIR/.htaccess"

mkdir -p \
    "$APP_DIR/storage/app/private" \
    "$APP_DIR/storage/app/public" \
    "$APP_DIR/storage/framework/cache/data" \
    "$APP_DIR/storage/framework/sessions" \
    "$APP_DIR/storage/framework/views" \
    "$APP_DIR/storage/logs" \
    "$APP_DIR/storage/media-library/temp"

if [[ "$INCLUDE_SQLITE" == "true" ]]; then
    if [[ ! -f "$ROOT/database/database.sqlite" ]]; then
        echo "database/database.sqlite is missing. Run migrations before INCLUDE_SQLITE=true." >&2
        exit 2
    fi

    cp "$ROOT/database/database.sqlite" "$APP_DIR/database/database.sqlite"
fi

cat > "$APP_DIR/.env" <<ENV
APP_NAME="PetSocial"
APP_ENV=production
APP_KEY=$APP_KEY
APP_DEBUG=false
APP_URL=$APP_URL

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=sqlite
DB_DATABASE=$DB_DATABASE

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=public
PUBLIC_DISK_ROOT=$PUBLIC_DISK_ROOT
PUBLIC_DISK_URL=$PUBLIC_DISK_URL
MEDIA_DISK=public
QUEUE_CONNECTION=sync

CACHE_STORE=file

MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="\${APP_NAME}"
MAIL_MARKDOWN_THEME=default
MAIL_MARKDOWN_EXTENSIONS=

QUEUE_MONITOR_QUEUES=
QUEUE_MONITOR_MAX=100
QUEUE_MONITOR_ALERT_EMAIL=

VITE_APP_NAME="\${APP_NAME}"
ENV

echo "Prepared FTP deployment in $BUILD_DIR"
