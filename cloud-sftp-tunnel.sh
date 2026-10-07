#!/bin/sh
set -eu

# Run as a second custom background process on the same Cloud worker instance.
project_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
runtime_exports=$(php "$project_dir/cloud-sftp-env.php" tunnel) || exit 1
eval "$runtime_exports"
unset runtime_exports

if [ -z "${NGROK_AUTHTOKEN:-}" ]; then
    echo 'Missing runtime variable: NGROK_AUTHTOKEN' >&2
    exit 1
fi

ngrok="$project_dir/vendor/bin/ngrok"
if [ ! -x "$ngrok" ]; then
    echo 'ngrok is missing. Run cloud-sftp-build.sh in the build command.' >&2
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

if [ -n "${NGROK_TCP_ADDRESS:-}" ]; then
    echo "Starting ngrok TCP tunnel to 127.0.0.1:$sftp_port."
    exec "$ngrok" tcp "127.0.0.1:$sftp_port" --url "tcp://$NGROK_TCP_ADDRESS" --log stdout
fi

echo "Starting ngrok TCP tunnel to 127.0.0.1:$sftp_port."
exec "$ngrok" tcp "127.0.0.1:$sftp_port" --log stdout
