<?php

use Symfony\Component\Process\Process;

test('SFTP settings are loaded from a Laravel environment file', function () {
    $directory = sys_get_temp_dir().'/cloud-sftp-env-'.bin2hex(random_bytes(8));
    mkdir($directory);
    file_put_contents($directory.'/.env', "NGROK_AUTHTOKEN=from-file\nSFTP_PORT=4321\n");

    try {
        $process = new Process(
            [PHP_BINARY, base_path('cloud-sftp-env.php'), 'tunnel', $directory],
            env: ['NGROK_AUTHTOKEN' => false, 'SFTP_PORT' => false],
        );
        $process->run();

        expect($process->isSuccessful())->toBeTrue();
        expect($process->getOutput())->toContain("export NGROK_AUTHTOKEN='from-file'");
        expect($process->getOutput())->toContain("export SFTP_PORT='4321'");
    } finally {
        unlink($directory.'/.env');
        rmdir($directory);
    }
});
