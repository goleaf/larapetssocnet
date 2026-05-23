#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOCAL_DIR="${LOCAL_DIR:-$ROOT/.deploy/petsocial}"
REMOTE_DIR="${FTP_SERVER_DIR:-petsocial/}"
FTP_HOST="${FTP_HOST:-}"
FTP_USERNAME="${FTP_USERNAME:-}"
FTP_PASSWORD="${FTP_PASSWORD:-}"
FTP_PROTOCOL="${FTP_PROTOCOL:-ftp}"
FTP_PORT="${FTP_PORT:-21}"
FTP_PARALLEL="${FTP_PARALLEL:-4}"
FTP_SSL_VERIFY="${FTP_SSL_VERIFY:-true}"
FTP_ALLOW_ROOT_DEPLOY="${FTP_ALLOW_ROOT_DEPLOY:-false}"
INCLUDE_SQLITE="${INCLUDE_SQLITE:-false}"

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

require_env FTP_HOST
require_env FTP_USERNAME
require_env FTP_PASSWORD

if ! command -v lftp >/dev/null 2>&1; then
    echo "lftp is required. Install it before running this deploy script." >&2
    exit 2
fi

if [[ ! -d "$LOCAL_DIR" ]]; then
    echo "Local deployment directory does not exist: $LOCAL_DIR" >&2
    exit 2
fi

if [[ ! "$FTP_PARALLEL" =~ ^[1-9][0-9]*$ ]]; then
    echo "FTP_PARALLEL must be a positive integer." >&2
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

exclude_globs=(
    "**/.git*"
    "**/.git*/**"
    "**/node_modules/**"
    "laravel/storage/logs/**"
    "laravel/storage/framework/cache/**"
    "laravel/storage/framework/sessions/**"
    "laravel/storage/framework/views/**"
    "laravel/storage/app/public/**"
)

if [[ "$INCLUDE_SQLITE" != "true" ]]; then
    exclude_globs+=(
        "laravel/database/*.sqlite*"
        "storage/**"
    )
fi

commands_file="$(mktemp)"
trap 'rm -f "$commands_file"' EXIT

{
    echo "set cmd:fail-exit yes"
    echo "set net:max-retries 2"
    echo "set net:timeout 30"
    echo "set net:reconnect-interval-base 5"
    echo "set ftp:list-options -a"
    echo "set ftp:use-mdtm no"
    echo "set mirror:parallel-directories yes"
    echo "set ssl:verify-certificate $LFTP_SSL_VERIFY"

    if [[ "$FTP_PROTOCOL" == "ftps" ]]; then
        echo "set ftp:ssl-force yes"
        echo "set ftp:ssl-protect-data yes"
    else
        echo "set ftp:ssl-allow no"
    fi

    echo "open --user $(lftp_quote "$FTP_USERNAME") --env-password -p $(lftp_quote "$FTP_PORT") $(lftp_quote "${FTP_PROTOCOL}://${FTP_HOST}")"
    echo "mkdir -f -p $(lftp_quote "$REMOTE_DIR")"

    printf 'mirror --reverse --delete --delete-first --ignore-time --transfer-all --no-perms --parallel=%s --verbose=1' "$FTP_PARALLEL"

    for pattern in "${exclude_globs[@]}"; do
        printf ' --exclude-glob %s' "$(lftp_quote "$pattern")"
    done

    printf ' %s %s\n' "$(lftp_quote "$LOCAL_DIR")" "$(lftp_quote "$REMOTE_DIR")"
    echo "bye"
} > "$commands_file"

echo "Deploying $LOCAL_DIR to ${FTP_PROTOCOL}://${FTP_HOST}/${REMOTE_DIR} with lftp mirror."
echo "Remote cleanup runs before upload with --delete-first; parallel transfers: $FTP_PARALLEL."

if [[ "$INCLUDE_SQLITE" != "true" ]]; then
    echo "Preserving remote SQLite and runtime storage paths."
fi

LFTP_PASSWORD="$FTP_PASSWORD" lftp --norc -f "$commands_file"
