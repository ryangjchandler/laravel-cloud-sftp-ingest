<?php

use App\Events\SftpFilesystemEvent;
use App\Models\SftpActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.sftp_webhook.token', 'shared-secret');

    Event::fake([SftpFilesystemEvent::class]);
});

test('the webhook rejects missing and invalid tokens with 401', function () {
    $payload = [
        'action' => 'delete',
        'username' => 'uploader',
        'virtual_path' => '/report.txt',
    ];

    $this->postJson(route('sftp.events'), $payload)->assertUnauthorized();
    $this->withHeader('Authorization', 'Bearer wrong-secret')
        ->postJson(route('sftp.events'), $payload)
        ->assertUnauthorized();

    Event::assertNotDispatched(SftpFilesystemEvent::class);
});

test('the webhook returns 503 when its token is not configured', function () {
    config()->set('services.sftp_webhook.token', null);

    $this->postJson(route('sftp.events'), [])->assertServiceUnavailable();

    Event::assertNotDispatched(SftpFilesystemEvent::class);
});

test('the webhook dispatches a completed upload event', function () {
    $this->withHeader('Authorization', 'Bearer shared-secret')
        ->postJson(route('sftp.events'), [
            'action' => 'upload',
            'username' => 'uploader',
            'virtual_path' => '/incoming/report.txt',
            'file_size' => 123,
            'protocol' => 'SFTP',
            'status' => 1,
            'timestamp' => 1780764572000000000,
        ])
        ->assertOk();

    Event::assertDispatched(SftpFilesystemEvent::class, fn (SftpFilesystemEvent $event): bool => $event->action === 'upload'
        && $event->path === '/incoming/report.txt'
        && $event->username === 'uploader'
        && $event->size === 123
        && $event->protocol === 'SFTP'
    );

    expect(SftpActivity::query()->sole()->path)->toBe('/incoming/report.txt');
});

test('the webhook dispatches delete and rename events', function () {
    $this->withHeader('Authorization', 'Bearer shared-secret')
        ->postJson(route('sftp.events'), [
            'action' => 'delete',
            'username' => 'uploader',
            'virtual_path' => '/old.txt',
            'timestamp' => 1780764572000000000,
        ])
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer shared-secret')
        ->postJson(route('sftp.events'), [
            'action' => 'rename',
            'username' => 'uploader',
            'virtual_path' => '/old.txt',
            'virtual_target_path' => '/new.txt',
            'timestamp' => 1780764573000000000,
        ])
        ->assertOk();

    Event::assertDispatched(SftpFilesystemEvent::class, fn (SftpFilesystemEvent $event): bool => $event->action === 'delete');
    Event::assertDispatched(SftpFilesystemEvent::class, fn (SftpFilesystemEvent $event): bool => $event->action === 'rename' && $event->targetPath === '/new.txt');
    expect(SftpActivity::query()->count())->toBe(2);
});

test('the webhook ignores failed uploads', function () {
    $this->withHeader('Authorization', 'Bearer shared-secret')
        ->postJson(route('sftp.events'), [
            'action' => 'upload',
            'username' => 'uploader',
            'virtual_path' => '/report.txt',
            'status' => 2,
            'timestamp' => 1780764572000000000,
        ])
        ->assertOk();

    Event::assertNotDispatched(SftpFilesystemEvent::class);
    expect(SftpActivity::query()->count())->toBe(0);
});

test('the webhook rejects malformed events with 422', function () {
    $this->withHeader('Authorization', 'Bearer shared-secret')
        ->postJson(route('sftp.events'), [
            'action' => 'rename',
            'username' => 'uploader',
            'virtual_path' => '/old.txt',
            'timestamp' => 1780764572000000000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('virtual_target_path');

    Event::assertNotDispatched(SftpFilesystemEvent::class);
});

test('repeated delivery records and dispatches an event only once', function () {
    $payload = [
        'action' => 'delete',
        'username' => 'uploader',
        'virtual_path' => '/report.txt',
        'timestamp' => 1780764572000000000,
    ];

    $this->withHeader('Authorization', 'Bearer shared-secret')->postJson(route('sftp.events'), $payload)->assertOk();
    $this->withHeader('Authorization', 'Bearer shared-secret')->postJson(route('sftp.events'), $payload)->assertOk();

    expect(SftpActivity::query()->count())->toBe(1);
    Event::assertDispatchedTimes(SftpFilesystemEvent::class, 1);
});
