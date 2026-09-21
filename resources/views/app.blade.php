<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @php
            // Pages resolve from resources/js/Pages *or* resources/js/Features
            // (see the resolver in app.jsx). Preload whichever one holds this
            // component; hardcoding Pages/ made every Features/ page fail with
            // "Unable to locate file in Vite manifest" once assets were built.
            $pageEntrypoints = collect(['Pages', 'Features'])
                ->map(fn ($directory) => "resources/js/{$directory}/{$page['component']}.jsx")
                ->filter(fn ($path) => file_exists(base_path($path)))
                ->values()
                ->all();
        @endphp

        @routes
        @viteReactRefresh
        @vite(array_merge(['resources/js/app.jsx'], $pageEntrypoints))
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
