<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('services.sftp_browser.disk', 'private');
    config()->set('services.sftp_browser.username', 'uploader');
    config()->set('services.sftp_browser.password', 'test-password');

    Storage::fake('private');
});

test('the file browser requires the SFTP credentials', function () {
    $this->get(route('files.index'))
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Basic realm="Uploaded files"');

    $this->withBasicAuth('uploader', 'wrong-password')
        ->get(route('files.index'))
        ->assertUnauthorized();
});

test('the file browser lists files and folders one level at a time', function () {
    Storage::disk('private')->put('receipt.txt', 'root file');
    Storage::disk('private')->put('incoming/nested.txt', 'nested file');

    $this->withBasicAuth('uploader', 'test-password')
        ->get(route('files.index'))
        ->assertOk()
        ->assertSee('receipt.txt')
        ->assertSee('incoming/')
        ->assertDontSee('nested.txt')
        ->assertHeader('Cache-Control', 'no-store, private');

    $this->withBasicAuth('uploader', 'test-password')
        ->get(route('files.index', ['directory' => 'incoming']))
        ->assertOk()
        ->assertSee('nested.txt')
        ->assertDontSee('receipt.txt');
});

test('the file browser rejects traversal paths', function () {
    $this->withBasicAuth('uploader', 'test-password')
        ->get(route('files.index', ['directory' => '../private']))
        ->assertNotFound();
});

test('the file browser escapes filenames', function () {
    Storage::disk('private')->put('file<img src=x>.txt', 'contents');

    $this->withBasicAuth('uploader', 'test-password')
        ->get(route('files.index'))
        ->assertOk()
        ->assertSee('file&lt;img src=x&gt;.txt', false)
        ->assertDontSee('<img src=x>', false);
});
