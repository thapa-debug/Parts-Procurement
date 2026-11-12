{{-- The request lifecycle (CLAUDE.md §5) as a horizontal stepper, current
stage highlighted. Display only -- it reads the real status and never sets
one. procurement_failed has no step of its own: it shows in red on
"Order confirmed", the step it failed at. $quotesToReview adds the
derived "Quotes to review" caption under Vendor inquiry (a label over the
real status, not a status). $urlSourced marks Vendor inquiry as skipped
rather than done: a reference-URL request is priced straight from its URL
(SetUrlPriceAction) and never goes to vendors. --}}
@props(['status', 'quotesToReview' => false, 'urlSourced' => false])

@php
    $steps = [
        ['status' => \App\Enums\RequestStatus::New, 'label' => __('admin.request_board.status.new')],
        ['status' => \App\Enums\RequestStatus::VendorInquiry, 'label' => __('admin.request_board.status.vendor_inquiry')],
        ['status' => \App\Enums\RequestStatus::Quoted, 'label' => __('admin.request_board.status.quoted')],
        ['status' => \App\Enums\RequestStatus::AwaitingPayment, 'label' => __('admin.request_board.status.awaiting_payment')],
        ['status' => \App\Enums\RequestStatus::Paid, 'label' => __('admin.request_board.status.paid')],
        ['status' => \App\Enums\RequestStatus::OrderedToVendor, 'label' => __('admin.request_detail.stepper.order_confirmed')],
        ['status' => \App\Enums\RequestStatus::Shipped, 'label' => __('admin.request_board.status.shipped')],
        ['status' => \App\Enums\RequestStatus::Received, 'label' => __('admin.request_board.status.received')],
    ];

    $failed = $status === \App\Enums\RequestStatus::ProcurementFailed;
    $currentStatus = $failed ? \App\Enums\RequestStatus::OrderedToVendor : $status;
    $currentIndex = collect($steps)->search(fn ($step) => $step['status'] === $currentStatus);
@endphp

<nav aria-label="{{ __('admin.request_detail.stepper.aria_label') }}" {{ $attributes->class('overflow-x-auto rounded-lg border border-line bg-surface px-4 py-3 shadow-sm') }}>
    <ol class="flex min-w-max items-center gap-2 text-xs">
        @foreach ($steps as $index => $step)
            @php
                $isSkipped = $urlSourced && $step['status'] === \App\Enums\RequestStatus::VendorInquiry;
                $isDone = $index < $currentIndex && ! $isSkipped;
                $isCurrent = $index === $currentIndex;
                $isFailed = $isCurrent && $failed;
            @endphp

            @if ($index > 0)
                <li aria-hidden="true" @class([
                    'h-px w-6 shrink-0 sm:w-10',
                    'bg-brand-600' => $index <= $currentIndex,
                    'bg-line' => $index > $currentIndex,
                ])></li>
            @endif

            <li class="flex shrink-0 items-center gap-1.5" @if ($isCurrent) aria-current="step" @endif>
                <span @class([
                    'flex size-5 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold',
                    'bg-brand-600 text-white' => $isDone,
                    'border-2 border-brand-600 bg-surface text-brand-700' => $isCurrent && ! $isFailed,
                    'bg-red-600 text-white' => $isFailed,
                    'border border-dashed border-line bg-surface text-ink-muted' => $isSkipped,
                    'bg-surface-muted text-ink-muted' => ! $isDone && ! $isCurrent && ! $isSkipped,
                ])>
                    @if ($isSkipped)
                        <svg class="size-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4 10a1 1 0 0 1 1-1h10a1 1 0 1 1 0 2H5a1 1 0 0 1-1-1Z" clip-rule="evenodd"/></svg>
                    @elseif ($isDone)
                        <svg class="size-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                    @elseif ($isFailed)
                        <svg class="size-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.3 5.3a1 1 0 0 0-1.4 1.4L8.6 10l-3.7 3.3a1 1 0 1 0 1.4 1.4L10 11.4l3.3 3.3a1 1 0 0 0 1.4-1.4L11.4 10l3.3-3.3a1 1 0 0 0-1.4-1.4L10 8.6 6.3 5.3Z"/></svg>
                    @else
                        {{ $index + 1 }}
                    @endif
                </span>
                <span class="flex flex-col leading-tight">
                    <span @class([
                        'whitespace-nowrap',
                        'font-semibold text-ink' => $isCurrent && ! $isFailed,
                        'font-semibold text-red-700' => $isFailed,
                        'font-medium text-ink' => $isDone,
                        'text-ink-muted line-through' => $isSkipped,
                        'text-ink-muted' => ! $isDone && ! $isCurrent && ! $isSkipped,
                    ])>{{ $step['label'] }}</span>
                    @if ($isSkipped)
                        <span class="whitespace-nowrap text-[11px] text-indigo-700">{{ __('admin.request_detail.stepper.skipped_url_sourced') }}</span>
                    @elseif ($isFailed)
                        <span class="whitespace-nowrap text-[11px] text-red-600">{{ __('admin.request_board.status.procurement_failed') }}</span>
                    @elseif ($isCurrent && $quotesToReview && $step['status'] === \App\Enums\RequestStatus::VendorInquiry)
                        <span class="whitespace-nowrap text-[11px] text-amber-700">{{ __('admin.request_detail.quotes_to_review') }}</span>
                    @endif
                </span>
            </li>
        @endforeach
    </ol>
</nav>
