<div>
    <h1 class="text-2xl font-semibold text-ink">{{ __('buyer.request_list.heading') }}</h1>
    <p class="mt-1 text-sm text-ink-muted">{{ __('buyer.request_list.subheading') }}</p>

    <div class="mt-6 overflow-x-auto rounded-md border border-line">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-surface-muted">
                <tr class="text-left text-xs font-medium uppercase tracking-wide text-ink-muted">
                    <th class="px-4 py-2.5">{{ __('buyer.request_list.table.code') }}</th>
                    <th class="px-4 py-2.5">{{ __('buyer.request_list.table.details') }}</th>
                    <th class="px-4 py-2.5">{{ __('buyer.request_list.table.requested_at') }}</th>
                    <th class="px-4 py-2.5">{{ __('buyer.request_list.table.status') }}</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($requests as $request)
                    <tr>
                        <td class="px-4 py-2.5 font-mono text-xs text-ink">
                            <a href="{{ route('buyer.requests.show', $request) }}" class="text-brand-700 hover:underline">
                                {{ $request->request_code }}
                            </a>
                        </td>
                        <td class="px-4 py-2.5 text-ink">{{ $request->maker->name }} {{ $request->car_model }} / {{ $request->part_name }}</td>
                        <td class="px-4 py-2.5 text-ink-muted">{{ $request->created_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-2.5">
                            <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                                {{ __('buyer.request_list.status.'.$request->status->value) }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <a href="{{ route('buyer.requests.show', $request) }}" class="text-sm font-medium text-brand-700 hover:text-brand-800">
                                {{ __('buyer.request_list.view_link') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-ink-muted">{{ __('buyer.request_list.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
