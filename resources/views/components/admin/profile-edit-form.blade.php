@props(['title', 'backRoute', 'backLabel', 'accountFields', 'fields', 'justSaved'])

<div class="max-w-2xl">
    <a href="{{ $backRoute }}" class="text-sm text-ink-muted hover:text-ink">&larr; {{ $backLabel }}</a>

    <h1 class="mt-2 text-2xl font-semibold text-ink">{{ $title }}</h1>

    <div class="mt-6 rounded-lg border border-line bg-surface p-6 shadow-sm">
        <h2 class="text-base font-semibold text-ink">{{ __('admin.profile_edit.account_section') }}</h2>

        <dl class="mt-3 grid grid-cols-2 gap-4 text-sm">
            @foreach ($accountFields as $accountField)
                <div>
                    <dt class="text-ink-muted">{{ $accountField['label'] }}</dt>
                    <dd class="mt-0.5 font-medium text-ink">{{ $accountField['value'] }}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    <form wire:submit="save" class="mt-6 space-y-4">
        <div class="space-y-4 rounded-lg border border-line bg-surface p-6 shadow-sm">
            @foreach ($fields as $field)
                <div>
                    <label for="{{ $field['name'] }}" class="block text-sm font-medium text-ink">
                        {{ $field['label'] }}
                        @if ($field['required'] ?? false)
                            <x-required-mark />
                        @endif
                    </label>
                    @if (($field['type'] ?? 'text') === 'select')
                        <select
                            id="{{ $field['name'] }}"
                            wire:model="{{ $field['name'] }}"
                            class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        >
                            <option value="">{{ $field['placeholderOption'] ?? '' }}</option>
                            @foreach ($field['options'] ?? [] as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @else
                        <input
                            id="{{ $field['name'] }}"
                            type="{{ $field['type'] ?? 'text' }}"
                            wire:model="{{ $field['name'] }}"
                            @if (isset($field['placeholder']))
                                placeholder="{{ $field['placeholder'] }}"
                            @endif
                            class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        >
                    @endif
                    @if (isset($field['help']))
                        <p class="mt-1 text-xs text-ink-muted">{{ $field['help'] }}</p>
                    @endif
                    @error($field['name'])
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>

        <div class="flex items-center gap-4">
            <button
                type="submit"
                class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
                wire:loading.attr="disabled"
                wire:target="save"
            >
                {{ __('admin.profile_edit.save_button') }}
            </button>

            @if ($justSaved)
                <span class="text-sm font-medium text-green-700">{{ __('admin.profile_edit.saved') }}</span>
            @endif
        </div>
    </form>
</div>
