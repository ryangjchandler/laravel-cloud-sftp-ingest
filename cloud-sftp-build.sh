#!/bin/sh
set -eu

# Run after composer install in the Laravel Cloud build command.
case "$(uname -s):$(uname -m)" in
    Linux:aarch64|Linux:arm64) ;;
    *)
        echo 'SFTP build currently supports Laravel Cloud Linux ARM64 only.' >&2
        exit 1
    ;;
esac

for command in curl tar unzip sha256sum mktemp; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "Missing build command: $command" >&2
        exit 1
    fi
done

project_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
binary_dir="$project_dir/vendor/bin"
temporary_dir=$(mktemp -d)
trap 'rm -rf "$temporary_dir"' EXIT HUP INT TERM

mkdir -p "$binary_dir"

sftpgo_archive="$temporary_dir/sftpgo.tar.xz"
curl -fLSs --retry 3 --max-time 120 \
    'https://github.com/drakkan/sftpgo/releases/download/v2.7.6/sftpgo_v2.7.6_linux_arm64.tar.xz' \
    -o "$sftpgo_archive"
printf '%s  %s\n' \
    '0d5c54a266515ae86a6a534e76c94807453353a1913ba3a26e81b9c20e548937' \
    "$sftpgo_archive" | sha256sum -c -
tar -xJf "$sftpgo_archive" -C "$binary_dir" sftpgo

ngrok_archive="$temporary_dir/ngrok.zip"
curl -fLSs --retry 3 --max-time 120 \
    'https://bin.ngrok.com/a/hSnuMbAc636/ngrok-v3-3.39.11-linux-arm64.zip' \
    -o "$ngrok_archive"
printf '%s  %s\n' \
    '8b1bd50c3f3d0eb7b863cfa04d48a97554647788930467bcbe04111f9df0ab35' \
    "$ngrok_archive" | sha256sum -c -
unzip -q -o "$ngrok_archive" ngrok -d "$binary_dir"

chmod 755 "$binary_dir/sftpgo" "$binary_dir/ngrok"
echo 'SFTPGo and ngrok installed for Cloud background processes.'
