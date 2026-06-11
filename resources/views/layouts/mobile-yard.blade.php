<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#4f46e5">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">

        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="apple-touch-icon" href="{{ asset('icons/icon.svg') }}">

        <title>{{ config('app.name') }} — Pátio</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-100 min-h-screen">
        <header class="bg-indigo-700 text-white px-4 py-3 flex items-center justify-between sticky top-0 z-10 shadow">
            <div>
                <p class="text-xs text-indigo-200">Modo pátio</p>
                <p class="font-semibold text-sm">{{ config('app.name') }}</p>
            </div>
            <a href="{{ route('dashboard') }}" wire:navigate class="text-xs bg-white/15 px-3 py-1.5 rounded-md hover:bg-white/25">
                Sistema completo
            </a>
        </header>

        <main class="pb-8">
            <x-flash-message />
            {{ $slot }}
        </main>

        <script>
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(() => {});
            }
        </script>
    </body>
</html>
