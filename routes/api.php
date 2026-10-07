<?php

use App\Http\Controllers\SftpFilesystemWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/sftp/events', SftpFilesystemWebhookController::class)->name('sftp.events');
