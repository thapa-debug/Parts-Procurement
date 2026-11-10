<div>
    <h1 class="text-2xl font-semibold text-ink">{{ __('vendor.inbox.heading') }}</h1>
    <p class="mt-1 text-sm text-ink-muted">{{ __('vendor.inbox.subheading') }}</p>

    {{-- Confirmed orders first and visually distinct: these are the ones
    where the vendor now has something to do (deliver + invoice). --}}
    @if ($confirmed->isNotEmpty())
        <div class="mt-6 rounded-lg border-2 border-green-300 bg-green-50 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-green-900">{{ __('vendor.inbox.confirmed_section') }}</h2>
            <p class="mt-0.5 text-xs text-green-800">{{ __('vendor.inbox.confirmed_help') }}</p>

            <div class="mt-3 space-y-2">
                @foreach ($confirmed as $request)
                    <a
                        href="{{ route('vendor.inbox.show', $request) }}"
                        class="block rounded-md border border-green-200 bg-surface p-3 text-sm shadow-sm hover:border-green-500"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span class="inline-flex items-center gap-1 rounded-full bg-green-600 px-2.5 py-0.5 text-xs font-semibold text-white">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0l-3.5-3.5a1 1 0 111.4-1.4l2.8 2.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd" />
                                </svg>
                                {{ __('vendor.inbox.confirmed_badge') }}
                            </span>
                            <span class="font-mono text-xs text-ink-muted">{{ $request->request_code }}</span>
                        </div>
                        <div class="mt-1.5 font-medium text-ink">{{ $request->maker->name }} {{ $request->car_model }} -- {{ $request->part_name }}</div>
                        <div class="mt-0.5 text-xs font-medium text-green-800">{{ __('vendor.inbox.confirmed_hint') }}</div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

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
