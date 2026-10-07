<?php

namespace App\Http\Controllers;

use App\Events\SftpFilesystemEvent;
use App\Models\SftpActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SftpFilesystemWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $token = config('services.sftp_webhook.token');

        if (! is_string($token) || $token === '') {
            return response('SFTP webhook is not configured.', 503);
        }

        if (! hash_equals($token, $request->bearerToken() ?? '')) {
            return response('Unauthorized.', 401);
        }

        $payload = $request->validate([
            'action' => ['required', 'in:upload,delete,rename,mkdir,rmdir'],
            'username' => ['required', 'string', 'max:255'],
            'virtual_path' => ['required', 'string', 'max:4096'],
            'virtual_target_path' => ['required_if:action,rename', 'nullable', 'string', 'max:4096'],
            'file_size' => ['nullable', 'integer', 'min:0'],
            'protocol' => ['nullable', 'string', 'max:32'],
            'status' => ['required_if:action,upload', 'nullable', 'integer', 'in:1,2,3'],
            'timestamp' => ['required', 'integer', 'min:1'],
        ]);

        if ($payload['action'] === 'upload' && (int) $payload['status'] !== 1) {
            return response('OK');
        }

        $eventKey = hash('sha256', json_encode([
            $payload['timestamp'],
            $payload['action'],
            $payload['username'],
            $payload['virtual_path'],
            $payload['virtual_target_path'] ?? null,
        ], JSON_THROW_ON_ERROR));

        $activity = SftpActivity::query()->createOrFirst(
            ['event_key' => $eventKey],
            [
                'action' => $payload['action'],
                'username' => $payload['username'],
                'path' => $payload['virtual_path'],
                'target_path' => $payload['virtual_target_path'] ?? null,
                'size' => $payload['file_size'] ?? null,
                'protocol' => $payload['protocol'] ?? null,
                'occurred_at' => Carbon::createFromTimestampUTC(intdiv($payload['timestamp'], 1_000_000_000)),
            ],
        );

        if (! $activity->wasRecentlyCreated) {
            return response('OK');
        }

        SftpFilesystemEvent::dispatch(
            action: $payload['action'],
            username: $payload['username'],
            path: $payload['virtual_path'],
            targetPath: $payload['virtual_target_path'] ?? null,
            size: $payload['file_size'] ?? null,
            protocol: $payload['protocol'] ?? null,
            timestamp: $payload['timestamp'] ?? null,
        );

        Log::info('SFTP filesystem event received.', [
            'action' => $payload['action'],
            'path' => $payload['virtual_path'],
            'username' => $payload['username'],
        ]);

        return response('OK');
    }
}
