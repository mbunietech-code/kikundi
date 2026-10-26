<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | {{ system_name() }}</title>
    {{-- Tailwind via CDN until the Vite build is set up --}}
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
    <header class="bg-emerald-700 text-white">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3">
            <span class="font-semibold">{{ system_name() }}</span>
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-sm underline">Toka</button>
                </form>
            @endauth
        </div>
    </header>
    <main class="mx-auto max-w-3xl px-4 py-8">
        @yield('content')
    </main>
</body>
</html>
