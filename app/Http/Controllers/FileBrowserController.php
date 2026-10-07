<?php

namespace App\Http\Controllers;

use App\Models\SftpActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class FileBrowserController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $username = config('services.sftp_browser.username');
        $password = config('services.sftp_browser.password');

        if (! is_string($username) || $username === '' || ! is_string($password) || $password === '') {
            return response('File browser credentials are not configured.', 503)
                ->header('Cache-Control', 'no-store');
        }

        if (! hash_equals($username, $request->getUser() ?? '') || ! hash_equals($password, $request->getPassword() ?? '')) {
            return response('Authentication required.', 401)
                ->header('WWW-Authenticate', 'Basic realm="Uploaded files"')
                ->header('Cache-Control', 'no-store');
        }

        $directory = $request->query('directory', '');

        abort_unless(is_string($directory) && $this->isValidDirectory($directory), 404);

        $disk = Storage::disk(config('services.sftp_browser.disk'));
        $directories = $disk->directories($directory);
        $files = $disk->files($directory);

        sort($directories, SORT_NATURAL | SORT_FLAG_CASE);
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        $breadcrumbs = [];
        $path = '';

        foreach (explode('/', $directory) as $segment) {
            if ($segment === '') {
                continue;
            }

            $path = $path === '' ? $segment : $path.'/'.$segment;
            $breadcrumbs[] = ['name' => $segment, 'path' => $path];
        }

        $activities = SftpActivity::query()->orderByDesc('occurred_at')->orderByDesc('id')->limit(30)->get();

        return response()->view('files', compact('directory', 'directories', 'files', 'breadcrumbs', 'activities'))
            ->header('Cache-Control', 'no-store');
    }

    private function isValidDirectory(string $directory): bool
    {
        if (str_contains($directory, '\\') || preg_match('/[\x00-\x1f\x7f]/', $directory)) {
            return false;
        }

        foreach (explode('/', $directory) as $segment) {
            if ($segment === '.' || $segment === '..' || ($segment === '' && $directory !== '')) {
                return false;
            }
        }

        return true;
    }
}
