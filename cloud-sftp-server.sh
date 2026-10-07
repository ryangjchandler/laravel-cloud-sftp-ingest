#!/bin/sh
set -eu

# Run as one custom background process on an always-awake Cloud worker.
project_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
runtime_exports=$(php "$project_dir/cloud-sftp-env.php" server) || exit 1
eval "$runtime_exports"
unset runtime_exports

for variable in LARAVEL_CLOUD_DISK_CONFIG SFTP_USERNAME SFTP_PASSWORD SFTP_SSH_HOST_KEY_BASE64; do
    case "$variable" in
        LARAVEL_CLOUD_DISK_CONFIG) value=${LARAVEL_CLOUD_DISK_CONFIG:-} ;;
        SFTP_USERNAME) value=${SFTP_USERNAME:-} ;;
        SFTP_PASSWORD) value=${SFTP_PASSWORD:-} ;;
        SFTP_SSH_HOST_KEY_BASE64) value=${SFTP_SSH_HOST_KEY_BASE64:-} ;;
    esac
    if [ -z "$value" ]; then
        echo "Missing runtime variable: $variable" >&2
        exit 1
    fi
done
unset value

sftpgo="$project_dir/vendor/bin/sftpgo"
if [ ! -x "$sftpgo" ]; then
    echo 'SFTPGo is missing. Run cloud-sftp-build.sh in the build command.' >&2
    exit 1
fi

sftp_port=${SFTP_PORT:-2222}
case "$sftp_port" in
    ''|*[!0-9]*) echo 'SFTP_PORT must be an unprivileged port number.' >&2; exit 1 ;;
esac
if [ "$sftp_port" -lt 1024 ] || [ "$sftp_port" -gt 65535 ]; then
    echo 'SFTP_PORT must be between 1024 and 65535.' >&2
    exit 1
fi

umask 077
runtime_dir=$(mktemp -d)
export SFTP_RUNTIME_DIR="$runtime_dir"
php -r '
    $diskName = getenv("SFTP_CLOUD_DISK") ?: "private";

    try {
        $disks = json_decode(getenv("LARAVEL_CLOUD_DISK_CONFIG"), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        fwrite(STDERR, "LARAVEL_CLOUD_DISK_CONFIG is invalid JSON.\n");
        exit(1);
    }

    if (! is_array($disks)) {
        fwrite(STDERR, "LARAVEL_CLOUD_DISK_CONFIG must contain a disk list.\n");
        exit(1);
    }

    $selectedDisk = null;

    foreach ($disks as $disk) {
        if (is_array($disk) && ($disk["disk"] ?? null) === $diskName) {
            $selectedDisk = $disk;
            break;
        }
    }

    if ($selectedDisk === null || ! empty($selectedDisk["scoped_disk"])) {
        fwrite(STDERR, "Requested Cloud disk is missing or scoped: {$diskName}\n");
        exit(1);
    }

    $values = [
        "access_key_id" => $selectedDisk["access_key_id"] ?? null,
        "access_key_secret" => $selectedDisk["access_key_secret"] ?? null,
        "bucket" => $selectedDisk["bucket"] ?? null,
        "endpoint" => $selectedDisk["endpoint"] ?? null,
        "region" => $selectedDisk["region"] ?? $selectedDisk["default_region"] ?? "auto",
    ];

    foreach ($values as $name => $value) {
        if (! is_string($value) || $value === "") {
            fwrite(STDERR, "Cloud disk {$diskName} is missing {$name}.\n");
            exit(1);
        }

        $path = getenv("SFTP_RUNTIME_DIR")."/".$name;

        if (file_put_contents($path, $value) === false || ! chmod($path, 0600)) {
            fwrite(STDERR, "Could not prepare Cloud disk configuration.\n");
            exit(1);
        }
    }
'

export AWS_ACCESS_KEY_ID="$(cat "$runtime_dir/access_key_id")"
export AWS_SECRET_ACCESS_KEY="$(cat "$runtime_dir/access_key_secret")"
aws_bucket=$(cat "$runtime_dir/bucket")
aws_endpoint=$(cat "$runtime_dir/endpoint")
aws_region=$(cat "$runtime_dir/region")
unset LARAVEL_CLOUD_DISK_CONFIG SFTP_RUNTIME_DIR

password_file="$runtime_dir/password"
host_key_file="$runtime_dir/ssh_host_ed25519_key"
printf '%s' "$SFTP_PASSWORD" > "$password_file"
if ! printf '%s' "$SFTP_SSH_HOST_KEY_BASE64" | base64 -d > "$host_key_file"; then
    echo 'SFTP_SSH_HOST_KEY_BASE64 is not valid base64.' >&2
    exit 1
fi

export SFTPGO_SFTPD__HOST_KEYS="$host_key_file"
export SFTPGO_SFTPD__BINDINGS__0__ADDRESS=127.0.0.1
export SFTPGO_LOG_FILE_PATH=

if [ -n "${SFTP_EVENT_WEBHOOK_URL:-}${SFTP_EVENT_WEBHOOK_TOKEN:-}" ]; then
    if [ -z "${SFTP_EVENT_WEBHOOK_URL:-}" ] || [ -z "${SFTP_EVENT_WEBHOOK_TOKEN:-}" ]; then
        echo 'SFTP_EVENT_WEBHOOK_URL and SFTP_EVENT_WEBHOOK_TOKEN must both be set to enable events.' >&2
        exit 1
    fi

    case "$SFTP_EVENT_WEBHOOK_URL" in
        https://*) ;;
        *) echo 'SFTP_EVENT_WEBHOOK_URL must be an HTTPS URL.' >&2; exit 1 ;;
    esac

    export SFTPGO_COMMON__ACTIONS__EXECUTE_ON=upload,delete,rename,mkdir,rmdir
    export SFTPGO_COMMON__ACTIONS__HOOK="$SFTP_EVENT_WEBHOOK_URL"
    export SFTPGO_HTTP__HEADERS__0__KEY=Authorization
    export SFTPGO_HTTP__HEADERS__0__VALUE="Bearer $SFTP_EVENT_WEBHOOK_TOKEN"
    export SFTPGO_HTTP__HEADERS__0__URL="$SFTP_EVENT_WEBHOOK_URL"
    export SFTPGO_HTTP__HEADERS__1__KEY=Content-Type
    export SFTPGO_HTTP__HEADERS__1__VALUE=application/json
    export SFTPGO_HTTP__HEADERS__1__URL="$SFTP_EVENT_WEBHOOK_URL"

    echo 'SFTP filesystem event notifications enabled.'
fi

set -- portable \
    --config-dir "$runtime_dir" \
    --directory "$runtime_dir" \
    --fs-provider s3fs \
    --sftpd-port "$sftp_port" \
    --username "$SFTP_USERNAME" \
    --password-file "$password_file" \
    --permissions '*' \
    --s3-bucket "$aws_bucket" \
    --s3-endpoint "$aws_endpoint" \
    --s3-region "$aws_region" \
    --s3-key-prefix "${SFTP_KEY_PREFIX:-}" \
    --log-file-path '' \
    --log-level info \
    --grace-time 30

if [ -n "${SFTP_PUBLIC_KEY:-}" ]; then
    set -- "$@" --public-key "$SFTP_PUBLIC_KEY"
fi

unset SFTP_PASSWORD SFTP_SSH_HOST_KEY_BASE64 SFTP_EVENT_WEBHOOK_TOKEN
echo "Starting SFTPGo for Cloud disk ${SFTP_CLOUD_DISK:-private} on 127.0.0.1:$sftp_port."
exec "$sftpgo" "$@"
