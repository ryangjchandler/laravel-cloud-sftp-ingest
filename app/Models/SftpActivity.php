<?php

namespace App\Models;

use Database\Factories\SftpActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SftpActivity extends Model
{
    /** @use HasFactory<SftpActivityFactory> */
    use HasFactory;

    protected $fillable = [
        'event_key',
        'action',
        'username',
        'path',
        'target_path',
        'size',
        'protocol',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
