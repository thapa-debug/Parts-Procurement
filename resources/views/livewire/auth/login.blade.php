<div class="flex min-h-[70vh] items-center justify-center">
    <div class="w-full max-w-md rounded-lg border border-line bg-surface p-8 shadow-sm">
        <div class="text-center">
            <h1 class="text-2xl font-semibold text-ink">{{ __('auth.login.heading') }}</h1>
            <p class="mt-2 text-sm text-ink-muted">{{ __('auth.login.subheading') }}</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-ink">
                    {{ __('auth.login.email_label') }}
                </label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
                @error('email')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-ink">
                    {{ __('auth.login.password_label') }}
                </label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
            </div>

            <label class="flex items-center gap-2 text-sm text-ink-muted">
                <input
                    type="checkbox"
                    name="remember"
                    class="rounded border-line text-brand-600 focus:ring-1 focus:ring-brand-500"
                >
                {{ __('auth.login.remember_label') }}
            </label>

            <button
                type="submit"
                class="w-full rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
            >
                {{ __('auth.login.submit') }}
            </button>

            <p class="text-center text-sm text-ink-muted">
                {{ __('auth.login.no_account') }}
                <a href="{{ route('register') }}" class="font-medium text-brand-700 hover:text-brand-800">
                    {{ __('auth.login.register_link') }}
                </a>
            </p>
        </form>
    </div>
</div>
