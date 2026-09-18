<div>
    <div>
        <h1 class="text-2xl font-semibold text-ink">{{ __('admin.request_board.title') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('admin.request_board.subheading') }}</p>
    </div>

    {{-- No flex-wrap -- a wrapped second row (e.g. "Completed" falling
    alone onto its own line) reads as broken, not responsive. Compact
    padding/gaps keep every tab on one line at ordinary widths; overflow-x
    is the deliberate fallback for whatever's left too narrow to fit
    (mobile), so the row scrolls horizontally instead of wrapping. --}}
    <div class="mt-6 flex gap-1.5 overflow-x-auto border-b border-line pb-3 text-sm font-medium">
        @foreach (['all', 'new', 'inquiring', 'quoted', 'order_confirmed', 'shipped', 'completed'] as $key)
            <button
                type="button"
                wire:click="$set('tab', '{{ $key }}')"
                @class([
                    'flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg border px-2.5 py-1.5 transition duration-150',
                    $tabBadgeClasses[$key],
                    'border-current font-semibold' => $tab === $key,
                    'border-transparent hover:border-line' => $tab !== $key,
                ])
            >
                {{ __('admin.request_board.tabs.'.$key) }}
                <span class="rounded-full bg-surface-muted px-1.5 py-0.5 text-xs text-ink-muted">{{ $tabCounts[$key] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    <div class="mt-4">
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('admin.request_board.search_placeholder') }}"
            class="w-full max-w-sm rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
        >
    </div>

    <div class="mt-4 overflow-x-auto rounded-lg border border-line bg-surface shadow-sm">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-surface-muted">
                <tr class="text-left text-xs font-medium uppercase tracking-wide text-ink-muted">
                    <th class="px-4 py-3">{{ __('admin.request_board.table.code') }}</th>
                    <th class="px-4 py-3">{{ __('admin.request_board.table.buyer') }}</th>
                    <th class="px-4 py-3">{{ __('admin.request_board.table.part_type') }}</th>
                    <th class="px-4 py-3">{{ __('admin.request_board.table.details') }}</th>
                    <th class="px-4 py-3">{{ __('admin.request_board.table.requested_at') }}</th>
                    <th class="px-4 py-3">{{ __('admin.request_board.table.status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('admin.request_board.table.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($requests as $request)
                    <tr wire:key="request-{{ $request->id }}">
                        <td class="px-4 py-3 font-mono text-xs font-medium text-ink">
                            <a href="{{ route('admin.requests.show', $request) }}" class="hover:text-brand-700 hover:underline">
                                {{ $request->request_code }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-ink-muted">{{ $request->buyer->company_name }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full bg-surface-muted px-2 py-0.5 text-xs font-medium text-ink-muted">
                                {{ __('admin.request_board.part_type.'.$request->part_type->value) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-ink-muted">{{ $request->car_model }} / {{ $request->part_name }}</td>
                        <td class="px-4 py-3 text-ink-muted">{{ $request->created_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $request->status->badgeClasses() }}">
                                {{ __('admin.request_board.status.'.$request->status->value) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.requests.show', $request) }}" class="text-sm text-ink-muted underline hover:text-ink">
                                {{ __('admin.request_board.view_link') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-ink-muted">
                            {{ $tab === 'all' ? __('admin.request_board.empty_all') : __('admin.request_board.empty') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $requests->links() }}
    </div>
</div>
