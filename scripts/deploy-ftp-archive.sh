#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOCAL_DIR="${LOCAL_DIR:-$ROOT/.deploy/petsocial}"
WORK_DIR="${WORK_DIR:-$ROOT/.deploy/archive}"
REMOTE_DIR="${FTP_SERVER_DIR:-petsocial/}"
FTP_HOST="${FTP_HOST:-}"
FTP_USERNAME="${FTP_USERNAME:-}"
FTP_PASSWORD="${FTP_PASSWORD:-}"
FTP_PROTOCOL="${FTP_PROTOCOL:-ftp}"
FTP_PORT="${FTP_PORT:-21}"
FTP_SSL_VERIFY="${FTP_SSL_VERIFY:-true}"
FTP_ALLOW_ROOT_DEPLOY="${FTP_ALLOW_ROOT_DEPLOY:-false}"
APP_URL="${APP_URL:-https://petsocial.prus.dev}"
INCLUDE_SQLITE="${INCLUDE_SQLITE:-false}"
DEPLOY_HTTP_TIMEOUT="${DEPLOY_HTTP_TIMEOUT:-600}"
DEPLOYER_BASENAME="__ftp_deploy.php"
ARCHIVE_RELATIVE_PATH="laravel/storage/app/private/__ftp_deploy_package.zip"
ARCHIVE_BASENAME="${ARCHIVE_RELATIVE_PATH##*/}"
ARCHIVE_REMOTE_DIR="${ARCHIVE_RELATIVE_PATH%/*}"
ARCHIVE_PATH="$WORK_DIR/$ARCHIVE_BASENAME"
DEPLOYER_TEMPLATE="$ROOT/deploy/shared-hosting/ftp-archive-deployer.php"
DEPLOYER_PATH="$WORK_DIR/$DEPLOYER_BASENAME"

require_env() {
    local key="$1"
    local value="${!key:-}"

    if [[ -z "$value" ]]; then
        echo "$key is required." >&2
        exit 2
    fi
}

lftp_quote() {
    printf "'%s'" "$(printf '%s' "$1" | sed "s/'/'\\\\''/g")"
}

join_remote_path() {
    local base="${1%/}"
    local child="${2#/}"

    if [[ "$base" == "" || "$base" == "/" ]]; then
        printf '/%s' "$child"
        return
    fi

    printf '%s/%s' "$base" "$child"
}

require_env FTP_HOST
require_env FTP_USERNAME
require_env FTP_PASSWORD

if ! command -v lftp >/dev/null 2>&1; then
    echo "lftp is required. Install it before running this deploy script." >&2
    exit 2
fi

if ! command -v zip >/dev/null 2>&1; then
    echo "zip is required. Install it before running this deploy script." >&2
    exit 2
fi

if [[ ! -d "$LOCAL_DIR" ]]; then
    echo "Local deployment directory does not exist: $LOCAL_DIR" >&2
    exit 2
fi

case "$FTP_PROTOCOL" in
    ftp | ftps)
        ;;
    *)
        echo "FTP_PROTOCOL must be ftp or ftps." >&2
        exit 2
        ;;
esac

case "$FTP_SSL_VERIFY" in
    true | yes)
        LFTP_SSL_VERIFY="yes"
        ;;
    false | no)
        LFTP_SSL_VERIFY="no"
        ;;
    *)
        echo "FTP_SSL_VERIFY must be true/false or yes/no." >&2
        exit 2
        ;;
esac

if [[ "$REMOTE_DIR" != "/" ]]; then
    REMOTE_DIR="${REMOTE_DIR%/}"
fi

case "$REMOTE_DIR" in
    "" | "." | "/")
        if [[ "$FTP_ALLOW_ROOT_DEPLOY" != "true" ]]; then
            echo "Refusing to deploy to the FTP account root. Set FTP_ALLOW_ROOT_DEPLOY=true if this is intentional." >&2
            exit 2
        fi
        ;;
esac

case "$INCLUDE_SQLITE" in
    true)
        PRESERVE_SQLITE="false"
        ;;
    false)
        PRESERVE_SQLITE="true"
        ;;
    *)
        echo "INCLUDE_SQLITE must be true or false." >&2
        exit 2
        ;;
esac

rm -rf "$WORK_DIR"
mkdir -p "$WORK_DIR"

DEPLOY_TOKEN="$(openssl rand -hex 32)"
DEPLOY_TOKEN_SHA256="$(printf '%s' "$DEPLOY_TOKEN" | sha256sum | awk '{print $1}')"

sed \
    -e "s#__DEPLOY_TOKEN_SHA256__#$DEPLOY_TOKEN_SHA256#g" \
    -e "s#__DEPLOY_ARCHIVE_RELATIVE_PATH__#$ARCHIVE_RELATIVE_PATH#g" \
    -e "s#__DEPLOY_PRESERVE_SQLITE__#$PRESERVE_SQLITE#g" \
    "$DEPLOYER_TEMPLATE" > "$DEPLOYER_PATH"

(
    cd "$LOCAL_DIR"
    zip -qr "$ARCHIVE_PATH" .
)

commands_file="$(mktemp)"
trap 'rm -f "$commands_file"; rm -rf "$WORK_DIR"' EXIT

archive_remote_full_dir="$(join_remote_path "$REMOTE_DIR" "$ARCHIVE_REMOTE_DIR")"

{
    echo "set cmd:fail-exit yes"
    echo "set net:max-retries 2"
    echo "set net:timeout 30"
    echo "set net:reconnect-interval-base 5"
    echo "set xfer:clobber yes"
    echo "set ftp:list-options -a"
    echo "set ftp:use-mdtm no"
    echo "set ssl:verify-certificate $LFTP_SSL_VERIFY"

    if [[ "$FTP_PROTOCOL" == "ftps" ]]; then
        echo "set ftp:ssl-force yes"
        echo "set ftp:ssl-protect-data yes"
    else
        echo "set ftp:ssl-allow no"
    fi

    echo "open --user $(lftp_quote "$FTP_USERNAME") --env-password -p $(lftp_quote "$FTP_PORT") $(lftp_quote "${FTP_PROTOCOL}://${FTP_HOST}")"
    echo "mkdir -f -p $(lftp_quote "$REMOTE_DIR")"
    echo "mkdir -f -p $(lftp_quote "$archive_remote_full_dir")"
    echo "put -O $(lftp_quote "$archive_remote_full_dir") $(lftp_quote "$ARCHIVE_PATH") -o $(lftp_quote "$ARCHIVE_BASENAME")"
    echo "put -O $(lftp_quote "$REMOTE_DIR") $(lftp_quote "$DEPLOYER_PATH") -o $(lftp_quote "$DEPLOYER_BASENAME")"
    echo "bye"
} > "$commands_file"

echo "Uploading one deployment archive to ${FTP_PROTOCOL}://${FTP_HOST}/${REMOTE_DIR}."
echo "Remote application files will be cleaned by the server-side deployer before extraction."

if [[ "$PRESERVE_SQLITE" == "true" ]]; then
    echo "Preserving remote SQLite and runtime storage paths."
fi

LFTP_PASSWORD="$FTP_PASSWORD" lftp --norc -f "$commands_file"

deploy_url="${APP_URL%/}/$DEPLOYER_BASENAME"
echo "Triggering server-side archive deployment at ${APP_URL%/}/$DEPLOYER_BASENAME."

curl --fail-with-body --show-error --silent \
    --max-time "$DEPLOY_HTTP_TIMEOUT" \
    --request POST \
    --header "X-Deploy-Token: $DEPLOY_TOKEN" \
    "$deploy_url"
