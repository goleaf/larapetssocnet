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

read_local_env_var() {
    local key="$1"

    if [[ -f "$ROOT/.env" ]]; then
        local line
        line="$(sed -n "s/^${key}=//p" "$ROOT/.env" | tail -n 1)"

        if [[ -n "$line" ]]; then
            line="${line%$'\r'}"
            line="${line%\"}"
            line="${line#\"}"
            line="${line%\'}"
            line="${line#\'}"

            if [[ -n "$line" ]]; then
                printf '%s' "$line"
                return
            fi
        fi
    fi

    printf ''
}

env_var_or_default() {
    local key="$1"
    local default="${2:-}"
    local current="${!key:-}"

    if [[ -n "$current" ]]; then
        printf '%s' "$current"
        return
    fi

    printf '%s' "$default"
}

dotenv_quote() {
    local value="$1"

    value="${value//$'\r'/}"
    value="${value//$'\n'/}"
    value="${value//\\/\\\\}"
    value="${value//\"/\\\"}"

    printf '"%s"' "$value"
}

if [[ -z "$APP_KEY" && -f "$ROOT/.env" ]]; then
    APP_KEY="$(read_local_env_var APP_KEY)"
fi

if [[ -z "$APP_KEY" ]]; then
    echo "APP_KEY is required. Set APP_KEY or create a local .env before preparing a deployment." >&2
    exit 2
fi

MAIL_MAILER="$(env_var_or_default MAIL_MAILER smtp)"
MAIL_SCHEME="$(env_var_or_default MAIL_SCHEME null)"
MAIL_HOST="$(env_var_or_default MAIL_HOST obojus.serveriai.lt)"
MAIL_PORT="$(env_var_or_default MAIL_PORT 465)"
MAIL_USERNAME="$(env_var_or_default MAIL_USERNAME robot@prus.dev)"
MAIL_PASSWORD="$(env_var_or_default MAIL_PASSWORD "$(read_local_env_var MAIL_PASSWORD)")"
MAIL_FROM_ADDRESS="$(env_var_or_default MAIL_FROM_ADDRESS robot@prus.dev)"
MAIL_FROM_NAME="$(env_var_or_default MAIL_FROM_NAME PetSocial)"

if [[ "$MAIL_MAILER" == "smtp" && -z "$MAIL_PASSWORD" ]]; then
    echo "MAIL_PASSWORD is required when MAIL_MAILER=smtp. Set it locally or as a GitHub Actions secret." >&2
    exit 2
fi

MAIL_USERNAME_ENV="$(dotenv_quote "$MAIL_USERNAME")"
MAIL_PASSWORD_ENV="$(dotenv_quote "$MAIL_PASSWORD")"
MAIL_FROM_ADDRESS_ENV="$(dotenv_quote "$MAIL_FROM_ADDRESS")"
MAIL_FROM_NAME_ENV="$(dotenv_quote "$MAIL_FROM_NAME")"

if [[ ! -d "$ROOT/vendor" ]]; then
    echo "vendor/ is missing. Run composer install --no-dev --optimize-autoloader first." >&2
    exit 2
fi

if [[ ! -f "$ROOT/build/manifest.json" ]]; then
    echo "build/manifest.json is missing. Run npm run build first." >&2
    exit 2
fi

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"

rsync -a --delete "$ROOT/build/" "$BUILD_DIR/build/"
rsync -a --delete "$ROOT/images/" "$BUILD_DIR/images/"

cp "$ROOT/favicon.ico" "$BUILD_DIR/favicon.ico"
cp "$ROOT/robots.txt" "$BUILD_DIR/robots.txt"

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

MAIL_MAILER=$MAIL_MAILER
MAIL_SCHEME=$MAIL_SCHEME
MAIL_HOST=$MAIL_HOST
MAIL_PORT=$MAIL_PORT
MAIL_USERNAME=$MAIL_USERNAME_ENV
MAIL_PASSWORD=$MAIL_PASSWORD_ENV
MAIL_FROM_ADDRESS=$MAIL_FROM_ADDRESS_ENV
MAIL_FROM_NAME=$MAIL_FROM_NAME_ENV
MAIL_MARKDOWN_THEME=default
MAIL_MARKDOWN_EXTENSIONS=

QUEUE_MONITOR_QUEUES=
QUEUE_MONITOR_MAX=100
QUEUE_MONITOR_ALERT_EMAIL=

VITE_APP_NAME="\${APP_NAME}"
ENV

echo "Prepared FTP deployment in $BUILD_DIR"
