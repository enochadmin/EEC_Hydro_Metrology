<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#1F6076">
        <meta name="description" content="EEC Basin & Hydrological Data Management System">

        <title inertia>{{ config('app.name', 'EEC-BHDMS') }}</title>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        {{-- Apply the stored theme before first paint to avoid a flash of the wrong palette. --}}
        <script>
            (function () {
                try {
                    var stored = localStorage.getItem('eec-theme') || 'system';
                    var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    var isDark = stored === 'dark' || (stored === 'system' && prefersDark);

                    document.documentElement.classList.toggle('dark', isDark);
                    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
                } catch (error) {
                    // Theme persistence is a progressive enhancement only.
                }
            })();
        </script>

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite('resources/js/app.jsx')
        @inertiaHead
    </head>
    <body class="min-h-full font-sans antialiased">
        @inertia
    </body>
</html>

