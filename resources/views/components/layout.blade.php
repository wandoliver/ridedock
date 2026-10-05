@props(['title' => 'RideDock'])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ str_ends_with($title, ' · RideDock') ? $title : $title.' · RideDock' }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=0.3.0" sizes="any">
    <link rel="icon" href="{{ asset('favicon.svg') }}?v=0.3.0" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=0.3.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <noscript><style>.rd-navigation-body { display: block; height: auto; }.rd-menu-toggle { display: none; }[data-coach-section][hidden] { display: block !important; }</style></noscript>
</head>
<body class="min-h-screen bg-surface text-ink antialiased">
    <a href="#page-content" class="rd-skip-link">{{ __('ux.skip') }}</a>
    @auth
        <x-sidebar />
        <div id="page-content" class="rd-app" tabindex="-1">{{ $slot }}</div>
    @else
        <div id="page-content" tabindex="-1">{{ $slot }}</div>
    @endauth
</body>
</html>
