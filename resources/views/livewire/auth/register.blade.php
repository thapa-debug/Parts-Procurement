<div class="flex min-h-[70vh] items-center justify-center py-10">
    <div class="w-full max-w-lg rounded-lg border border-line bg-surface p-8 shadow-sm">
        <div class="text-center">
            <h1 class="text-2xl font-semibold text-ink">{{ __('auth.register.heading') }}</h1>
            <p class="mt-2 text-sm text-ink-muted">{{ __('auth.register.subheading') }}</p>
        </div>

        <form wire:submit="register" class="mt-8 space-y-5">
            <div>
                <label for="name" class="block text-sm font-medium text-ink">
                    {{ __('auth.register.name_label') }} <x-required-mark />
                </label>
                <input
                    id="name"
                    type="text"
                    wire:model="name"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="{{ __('auth.register.name_placeholder') }}"
                    class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
                @error('name')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-ink">
                    {{ __('auth.register.email_label') }} <x-required-mark />
                </label>
                <input
                    id="email"
                    type="email"
                    wire:model="email"
                    required
                    autocomplete="username"
                    placeholder="{{ __('auth.register.email_placeholder') }}"
                    class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
                @error('email')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-sm font-medium text-ink">
                        {{ __('auth.register.password_label') }} <x-required-mark />
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
                        {{ __('auth.register.confirm_password_label') }} <x-required-mark />
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
            </div>

            <div>
                <label for="company_name" class="block text-sm font-medium text-ink">
                    {{ __('auth.register.company_name_label') }} <x-required-mark />
                </label>
                <input
                    id="company_name"
                    type="text"
                    wire:model="company_name"
                    required
                    placeholder="{{ __('auth.register.company_name_placeholder') }}"
                    class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
                @error('company_name')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-ink">
                    {{ __('auth.register.phone_label') }} <x-required-mark />
                </label>
                <input
                    id="phone"
                    type="text"
                    wire:model="phone"
                    required
                    autocomplete="tel"
                    placeholder="{{ __('auth.register.phone_placeholder') }}"
                    class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                >
                @error('phone')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="country_id" class="block text-sm font-medium text-ink">
                        {{ __('auth.register.country_label') }} <x-required-mark />
                    </label>
                    <select
                        id="country_id"
                        wire:model="country_id"
                        required
                        class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                    >
                        <option value="">{{ __('auth.register.country_placeholder_option') }}</option>
                        @foreach ($activeCountries as $country)
                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-ink-muted">{{ __('auth.register.country_help') }}</p>
                    @error('country_id')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="default_yard" class="block text-sm font-medium text-ink">
                        {{ __('auth.register.default_yard_label') }} <x-required-mark />
                    </label>
                    <input
                        id="default_yard"
                        type="text"
                        wire:model="default_yard"
                        required
                        placeholder="{{ __('auth.register.default_yard_placeholder') }}"
                        class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                    >
                    <p class="mt-1 text-xs text-ink-muted">{{ __('auth.register.default_yard_help') }}</p>
                    @error('default_yard')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <button
                type="submit"
                class="w-full rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
                wire:loading.attr="disabled"
                wire:target="register"
            >
                {{ __('auth.register.submit') }}
            </button>

            <p class="text-center text-sm text-ink-muted">
                {{ __('auth.register.already_have_account') }}
                <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:text-brand-800">
                    {{ __('auth.register.login_link') }}
                </a>
            </p>
        </form>
    </div>
</div>
