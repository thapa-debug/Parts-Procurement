@props(['heading', 'body'])

{{--
    Shared persistent paid-status indicator (buyer + admin request-detail
    pages) -- a status, not a dismissible flash, so there is deliberately no
    close button. Renders full-size in normal flow at the top of the page;
    once scrolled out of view, a compact single-line version (heading and
    body both, truncated as one line rather than dropping the body
    entirely -- still a clear picture of the paid status, not just "paid")
    pins itself to the very top of the viewport instead, so the order's
    status stays visible regardless of scroll position without permanently
    eating screen space the way a fully sticky full-size banner would.

    IntersectionObserver on the full banner itself drives this -- no scroll
    listener needed, and it stays correct if the banner's own height or
    position ever changes.
--}}
<div x-data="{ compact: false }" x-init="
    const observer = new IntersectionObserver(([entry]) => { compact = ! entry.isIntersecting; }, { threshold: 0 });
    observer.observe($el);
">
    <div class="mt-4 flex items-start gap-3 rounded-lg border border-indigo-200 bg-indigo-50 p-4">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
        <div>
            <p class="text-sm font-semibold text-indigo-800">{{ $heading }}</p>
            <p class="mt-0.5 text-sm text-indigo-700">{{ $body }}</p>
        </div>
    </div>

    <div
        x-show="compact"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
        class="fixed inset-x-0 top-0 z-30 border-b border-indigo-200 bg-indigo-50 shadow-sm"
    >
        <div class="mx-auto flex max-w-6xl items-center gap-2 px-6 py-2">
            <svg class="h-4 w-4 shrink-0 text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <p class="min-w-0 truncate text-sm text-indigo-800">
                <span class="font-semibold">{{ $heading }}</span>
                <span class="text-indigo-700">-- {{ $body }}</span>
            </p>
        </div>
    </div>
</div>
