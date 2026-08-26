<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ?? __('app.name') }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-surface-muted text-ink antialiased">
        <header class="border-b border-line bg-surface">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <span class="text-lg font-semibold text-brand-700">{{ __('app.name') }}</span>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-10">
            {{ $slot }}
        </main>

        <footer class="border-t border-line py-6 text-center text-sm text-ink-muted">
            &copy; {{ now()->year }} {{ __('app.name') }}. {{ __('app.footer_rights') }}
        </footer>

        @livewireScripts
    </body>
</html>
