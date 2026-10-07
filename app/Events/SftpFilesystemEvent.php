<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SftpFilesystemEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $action,
        public readonly string $username,
        public readonly string $path,
        public readonly ?string $targetPath,
        public readonly ?int $size,
        public readonly ?string $protocol,
        public readonly ?int $timestamp,
    ) {}
}
