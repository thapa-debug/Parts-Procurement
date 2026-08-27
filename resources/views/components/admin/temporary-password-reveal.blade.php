@props(['password', 'forCompany', 'heading', 'warning'])

<div
    x-data="{ acknowledged: false, copied: false }"
    class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4"
>
    <div class="w-full max-w-md rounded-lg border border-line bg-surface p-6 shadow-lg">
        <h2 class="text-lg font-semibold text-ink">{{ $heading }}</h2>
        <p class="mt-1 text-sm text-ink-muted">
            {{ __('admin.reveal.for', ['company' => $forCompany]) }}
        </p>

        <div class="mt-4 flex items-center gap-2 rounded-md border border-line bg-surface-muted px-3 py-2">
            <code x-ref="pwd" class="flex-1 select-all break-all font-mono text-sm text-ink">{{ $password }}</code>
            <button
                type="button"
                @click="navigator.clipboard.writeText($refs.pwd.textContent.trim()); copied = true; setTimeout(() => (copied = false), 2000)"
                class="shrink-0 rounded-md border border-line px-2 py-1 text-xs font-medium text-ink-muted hover:text-ink"
            >
                <span x-show="!copied">{{ __('admin.reveal.copy_button') }}</span>
                <span x-show="copied" style="display: none">{{ __('admin.reveal.copied') }}</span>
            </button>
        </div>

        <p class="mt-3 text-sm text-amber-700">{{ $warning }}</p>

        <label class="mt-4 flex items-start gap-2 text-sm text-ink">
            <input
                type="checkbox"
                x-model="acknowledged"
                class="mt-0.5 rounded border-line text-brand-600 focus:ring-1 focus:ring-brand-500"
            >
            {{ __('admin.reveal.acknowledge_label') }}
        </label>

        <button
            type="button"
            wire:click="dismissReveal"
            :disabled="!acknowledged"
            class="mt-5 w-full rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{ __('admin.reveal.dismiss_button') }}
        </button>
    </div>
</div>
