@props(['code', 'heading', 'message'])

{{--
    Deliberately its own minimal layout, not <x-layouts.app> -- a 404 can be
    reached by a guest on a mistyped URL before the 'web' group's session
    middleware is guaranteed to have run for an unmatched route, so this
    avoids depending on an authenticated session at all. auth()->check()
    below is wrapped defensively for the same reason: the one page that
    exists because something already went wrong must never itself error.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $code }} -- {{ __('app.name') }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-surface-muted text-ink antialiased">
        <header class="border-b border-line bg-surface">
            <div class="mx-auto max-w-6xl px-6 py-4">
                <span class="text-lg font-semibold text-brand-700">{{ __('app.name') }}</span>
            </div>
        </header>

        <main class="mx-auto flex max-w-6xl flex-col items-center px-6 py-24 text-center">
            <p class="text-sm font-semibold uppercase tracking-wide text-brand-600">{{ $code }}</p>
            <h1 class="mt-2 text-2xl font-semibold text-ink">{{ $heading }}</h1>
            <p class="mt-2 max-w-md text-sm text-ink-muted">{{ $message }}</p>

            @php
                $homeUrl = '/';

                try {
                    if (auth()->check()) {
                        $user = auth()->user();

                        $homeUrl = match (true) {
                            $user->isAdmin() => route('admin.requests.index'),
                            $user->isBuyer() => route('buyer.requests.index'),
                            $user->isVendor() => route('vendor.inbox'),
                            default => '/',
                        };
                    }
                } catch (\Throwable) {
                    $homeUrl = '/';
                }
            @endphp

            <a href="{{ $homeUrl }}" class="mt-6 rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                {{ __('app.errors.back_home') }}
            </a>
        </main>
    </body>
</html>
