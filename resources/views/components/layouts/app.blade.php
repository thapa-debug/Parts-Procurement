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
                <div class="flex items-center gap-8">
                    <span class="text-lg font-semibold text-brand-700">{{ __('app.name') }}</span>

                    @auth
                        <nav class="flex items-center gap-6 text-sm text-ink-muted">
                            {{-- Role-specific nav links (admin/buyer/vendor portals) are added here as each is built. --}}
                        </nav>
                    @endauth
                </div>

                @auth
                    <div class="flex items-center gap-4 text-sm">
                        <span class="text-ink-muted">{{ auth()->user()->name }}</span>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-ink-muted underline hover:text-ink">
                                {{ __('app.logout') }}
                            </button>
                        </form>
                    </div>
                @endauth
            </div>
        </header>

        @auth
            @unless (auth()->user()->hasVerifiedEmail())
                <div class="border-b border-amber-200 bg-amber-50">
                    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-6 py-3 text-sm text-amber-800">
                        <span>{{ __('auth.verification.banner') }}</span>

                        <form method="POST" action="{{ route('verification.send') }}">
                            @csrf
                            <button type="submit" class="font-medium underline hover:text-amber-900">
                                {{ __('auth.verification.resend_button') }}
                            </button>
                        </form>
                    </div>
                </div>
            @endunless
        @endauth

        <main class="mx-auto max-w-6xl px-6 py-10">
            @if (session('status'))
                <div class="mb-6 rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </main>

        <footer class="border-t border-line py-6 text-center text-sm text-ink-muted">
            &copy; {{ now()->year }} {{ __('app.name') }}. {{ __('app.footer_rights') }}
        </footer>

        @livewireScripts
    </body>
</html>
