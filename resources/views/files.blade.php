<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Uploaded files</title>
</head>
<body>
    <main>
        <h1>Uploaded files</h1>

        <nav aria-label="Current folder">
            <a href="{{ route('files.index') }}">Bucket</a>
            @foreach ($breadcrumbs as $breadcrumb)
                / <a href="{{ route('files.index', ['directory' => $breadcrumb['path']]) }}">{{ $breadcrumb['name'] }}</a>
            @endforeach
        </nav>

        @if ($directories === [] && $files === [])
            <p>This folder is empty.</p>
        @else
            <ul>
                @foreach ($directories as $subdirectory)
                    <li>
                        <a href="{{ route('files.index', ['directory' => $subdirectory]) }}">{{ basename($subdirectory) }}/</a>
                    </li>
                @endforeach
                @foreach ($files as $file)
                    <li>{{ basename($file) }}</li>
                @endforeach
            </ul>
        @endif

        <section aria-labelledby="sftp-activity-heading">
            <h2 id="sftp-activity-heading">Recent SFTP activity</h2>

            @if ($activities->isEmpty())
                <p>No SFTP activity recorded yet.</p>
            @else
                <ul>
                    @foreach ($activities as $activity)
                        <li>
                            <time datetime="{{ $activity->occurred_at->toIso8601String() }}">{{ $activity->occurred_at->utc()->format('Y-m-d H:i:s') }} UTC</time>
                            &mdash; {{ $activity->username }} {{ $activity->action }} {{ $activity->path }}
                            @if ($activity->target_path)
                                &rarr; {{ $activity->target_path }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </main>
</body>
</html>
