<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | {{ system_name() }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    {{-- Prebuilt assets so the app works on WAMP offline and without a Node build --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <script defer src="{{ asset('js/alpine.min.js') }}"></script>
</head>
<body class="h-full font-sans text-slate-900 antialiased">
    @yield('body')
</body>
</html>
