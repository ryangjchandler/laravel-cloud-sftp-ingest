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
    </main>
</body>
</html>
