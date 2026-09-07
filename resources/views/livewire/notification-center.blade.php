<div
    x-data="{ open: false }"
    x-on:click.outside="open = false"
    x-on:keydown.escape.window="open = false"
    class="relative"
>
    <button
        type="button"
        x-on:click="open = !open"
        :aria-expanded="open"
        aria-haspopup="true"
        aria-label="{{ __('notifications.center.bell_label') }}"
        class="relative rounded-full p-2 text-ink-muted hover:bg-surface-muted hover:text-ink"
    >
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>

        @if ($unreadCount > 0)
            <span class="absolute right-0.5 top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold leading-none text-white">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-cloak
        class="absolute right-0 z-50 mt-2 w-80 rounded-md border border-line bg-surface shadow-lg"
    >
        <div class="flex items-center justify-between border-b border-line px-4 py-2.5">
            <span class="text-sm font-semibold text-ink">{{ __('notifications.center.heading') }}</span>

            @if ($unreadCount > 0)
                <button
                    type="button"
                    wire:click="markAllAsRead"
                    wire:loading.attr="disabled"
                    wire:target="markAllAsRead"
                    class="text-xs font-medium text-brand-700 hover:text-brand-800 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="markAllAsRead">{{ __('notifications.center.mark_all_read_button') }}</span>
                    <span wire:loading wire:target="markAllAsRead" class="inline-flex items-center gap-1">
                        <span class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-brand-400 border-t-transparent" aria-hidden="true"></span>
                        {{ __('notifications.center.mark_all_read_button') }}
                    </span>
                </button>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto">
            @forelse ($notifications as $notification)
                @php $isUnread = $notification->read_at === null; @endphp
                <button
                    type="button"
                    wire:click="markAsRead('{{ $notification->id }}')"
                    wire:loading.attr="disabled"
                    wire:target="markAsRead('{{ $notification->id }}')"
                    class="block w-full border-b border-line px-4 py-3 text-left text-sm last:border-b-0 hover:bg-surface-muted disabled:cursor-not-allowed disabled:opacity-50 {{ $isUnread ? 'bg-brand-50/70' : '' }}"
                >
                    <div
                        wire:loading.remove
                        wire:target="markAsRead('{{ $notification->id }}')"
                        class="flex items-start gap-2"
                    >
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full {{ $isUnread ? 'bg-brand-600' : 'bg-transparent' }}" aria-hidden="true"></span>
                        <span class="flex-1">
                            <span class="block {{ $isUnread ? 'font-medium text-ink' : 'text-ink-muted' }}">{{ $this->notificationText($notification) }}</span>
                            <span class="mt-0.5 block text-xs text-ink-muted">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                    </div>
                    <div
                        wire:loading
                        wire:target="markAsRead('{{ $notification->id }}')"
                        class="flex items-center gap-2 text-ink-muted"
                    >
                        <span class="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-brand-400 border-t-transparent" aria-hidden="true"></span>
                        <span>{{ $this->notificationText($notification) }}</span>
                    </div>
                </button>
            @empty
                <p class="px-4 py-8 text-center text-sm text-ink-muted">{{ __('notifications.center.empty') }}</p>
            @endforelse
        </div>
    </div>
</div>
