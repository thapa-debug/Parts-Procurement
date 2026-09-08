{{--
    Shared action-feedback toast, mounted once in the layout for the whole
    app. Fire one from any Livewire component with:

        $this->dispatch('toast', message: '...', type: 'success' | 'error');

    Deliberately for ACTION-level feedback only ("Request submitted",
    "Failed to save") -- field-level validation errors stay inline under
    their field, never routed through this. Several toasts stack (newest
    nearest the bottom-right anchor, pushing older ones up); each carries
    its own auto-dismiss timer and can also be closed by hand.
--}}
<div
    x-data="{
        toasts: [],
        add(message, type) {
            const id = `${Date.now()}-${Math.random()}`;
            this.toasts.push({ id, message, type: type ?? 'success' });
            setTimeout(() => this.remove(id), 4500);
        },
        remove(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
    }"
    x-on:toast.window="add($event.detail.message, $event.detail.type)"
    role="status"
    aria-live="polite"
    class="pointer-events-none fixed bottom-6 right-6 z-50 flex w-full max-w-sm flex-col gap-2"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-2"
            class="pointer-events-auto flex items-start gap-3 rounded-lg border-l-4 px-4 py-3.5 text-sm font-medium shadow-xl"
            :class="{
                'border-green-500 bg-green-50 text-green-800': toast.type === 'success',
                'border-red-500 bg-red-50 text-red-800': toast.type === 'error',
            }"
        >
            <svg x-show="toast.type === 'success'" class="h-5 w-5 shrink-0 text-green-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <svg x-show="toast.type === 'error'" class="h-5 w-5 shrink-0 text-red-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
            <span x-text="toast.message" class="flex-1"></span>
            <button
                type="button"
                x-on:click="remove(toast.id)"
                aria-label="{{ __('app.close') }}"
                class="shrink-0 text-current opacity-60 hover:opacity-100"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                </svg>
            </button>
        </div>
    </template>
</div>
