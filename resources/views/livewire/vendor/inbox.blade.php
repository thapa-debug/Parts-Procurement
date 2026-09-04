<div>
    <h1 class="text-2xl font-semibold text-ink">{{ __('vendor.inbox.heading') }}</h1>
    <p class="mt-1 text-sm text-ink-muted">{{ __('vendor.inbox.subheading') }}</p>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-lg border border-line bg-surface p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-ink">{{ __('vendor.inbox.pending_section') }}</h2>

            <div class="mt-3 space-y-2">
                @forelse ($pending as $request)
                    <a
                        href="{{ route('vendor.inbox.show', $request) }}"
                        class="block rounded-md border border-line p-3 text-sm shadow-sm hover:border-brand-500"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-xs text-ink-muted">{{ $request->request_code }}</span>
                            <span class="text-xs text-ink-muted">{{ $request->created_at->format('Y-m-d') }}</span>
                        </div>
                        <div class="mt-1 font-medium text-ink">{{ $request->maker->name }} {{ $request->car_model }} -- {{ $request->part_name }}</div>
                        <div class="mt-0.5 text-xs text-ink-muted">{{ $request->oem_part_number ?? '—' }}</div>
                    </a>
                @empty
                    <p class="py-4 text-center text-sm text-ink-muted">{{ __('vendor.inbox.pending_empty') }}</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-lg border border-line bg-surface p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-ink">{{ __('vendor.inbox.responded_section') }}</h2>

            <div class="mt-3 space-y-2">
                @forelse ($responded as $request)
                    <a
                        href="{{ route('vendor.inbox.show', $request) }}"
                        class="block rounded-md border border-line p-3 text-sm shadow-sm hover:border-brand-500"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-xs text-ink-muted">{{ $request->request_code }}</span>
                            <span class="text-xs text-ink-muted">{{ $request->created_at->format('Y-m-d') }}</span>
                        </div>
                        <div class="mt-1 font-medium text-ink">{{ $request->maker->name }} {{ $request->car_model }} -- {{ $request->part_name }}</div>
                        <div class="mt-0.5 text-xs text-ink-muted">{{ $request->oem_part_number ?? '—' }}</div>
                    </a>
                @empty
                    <p class="py-4 text-center text-sm text-ink-muted">{{ __('vendor.inbox.responded_empty') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
