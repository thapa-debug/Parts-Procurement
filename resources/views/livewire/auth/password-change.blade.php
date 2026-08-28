<div class="flex min-h-[70vh] items-center justify-center">
    <div class="w-full max-w-md rounded-lg border border-line bg-surface p-8 shadow-sm">
        <div class="text-center">
            <h1 class="text-2xl font-semibold text-ink">{{ __('auth.password_change.heading') }}</h1>
            <p class="mt-2 text-sm text-ink-muted">{{ __('auth.password_change.subheading') }}</p>
        </div>

        <form wire:submit="update" class="mt-8 space-y-5">
            <div>
                <label for="current_password" class="block text-sm font-medium text-ink">
                    {{ __('auth.password_change.current_password_label') }} <x-required-mark />
                </label>
                <input
                    id="current_password"
                    type="password"
                    wire:model="current_password"
                    required
                    autofocus
                    autocomplete="current-password"
                    class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
                @error('current_password')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-ink">
                    {{ __('auth.password_change.new_password_label') }} <x-required-mark />
                </label>
                <input
                    id="password"
                    type="password"
                    wire:model="password"
                    required
                    autocomplete="new-password"
                    class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
                @error('password')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-ink">
                    {{ __('auth.password_change.confirm_password_label') }} <x-required-mark />
                </label>
                <input
                    id="password_confirmation"
                    type="password"
                    wire:model="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
            </div>

            <button
                type="submit"
                class="w-full rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
                wire:loading.attr="disabled"
                wire:target="update"
            >
                <span wire:loading.remove wire:target="update">{{ __('auth.password_change.submit') }}</span>
                <span wire:loading wire:target="update">{{ __('auth.password_change.submit') }}&hellip;</span>
            </button>
        </form>
    </div>
</div>
