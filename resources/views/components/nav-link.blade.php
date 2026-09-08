@props(['href', 'active' => false])

{{--
    Active state (UX brush-up): the caller decides "active" via
    request()->routeIs(...) -- kept out of this component since the right
    route pattern differs per link (a wildcard for an index/show pair, an
    exact match for a single page). Single place defining what "active"
    LOOKS like, reused by every portal's header nav.
--}}
<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->class([
        'rounded-md px-2.5 py-1.5 text-sm transition duration-150',
        'bg-brand-50 font-semibold text-brand-700' => $active,
        'font-medium text-ink-muted hover:bg-surface-muted hover:text-ink' => ! $active,
    ]) }}
>
    {{ $slot }}
</a>
