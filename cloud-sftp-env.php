<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use Dotenv\Dotenv;

$variables = match ($argv[1] ?? null) {
    'server' => [
        'LARAVEL_CLOUD_DISK_CONFIG',
        'SFTP_USERNAME',
        'SFTP_PASSWORD',
        'SFTP_SSH_HOST_KEY_BASE64',
        'SFTP_CLOUD_DISK',
        'SFTP_PORT',
        'SFTP_KEY_PREFIX',
        'SFTP_PUBLIC_KEY',
        'SFTP_EVENT_WEBHOOK_URL',
        'SFTP_EVENT_WEBHOOK_TOKEN',
    ],
    'tunnel' => [
        'NGROK_AUTHTOKEN',
        'NGROK_TCP_ADDRESS',
        'SFTP_PORT',
    ],
    default => null,
};

if ($variables === null) {
    fwrite(STDERR, "Expected server or tunnel mode.\n");
    exit(1);
}

try {
    $fileVariables = Dotenv::createArrayBacked($argv[2] ?? __DIR__)->safeLoad();
} catch (Throwable $exception) {
    fwrite(STDERR, "Could not read the Cloud .env file: {$exception->getMessage()}\n");
    exit(1);
}

foreach ($variables as $variable) {
    $value = getenv($variable);

    if ($value === false) {
        $value = $fileVariables[$variable] ?? null;
    }

    if ($value === null) {
        continue;
    }

    echo 'export '.$variable.'='.escapeshellarg($value)."\n";
}
