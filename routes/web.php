<?php

use App\Http\Controllers\FileBrowserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/files', FileBrowserController::class)->name('files.index');
