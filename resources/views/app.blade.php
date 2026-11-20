<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ $meta['title'] ?? config('app.name', 'Wiifarm') }}</title>
        @isset($meta)
            {{-- Read by search engines and link previews, which do not run the page's scripts. --}}
            @if (! empty($meta['description']))
                <meta name="description" content="{{ $meta['description'] }}">
                <meta property="og:description" content="{{ $meta['description'] }}">
            @endif
            <meta property="og:title" content="{{ $meta['title'] }}">
            <meta property="og:type" content="website">
            <meta property="og:url" content="{{ url()->current() }}">
            @if (! empty($meta['image']))
                <meta property="og:image" content="{{ $meta['image'] }}">
            @endif
        @endisset

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
