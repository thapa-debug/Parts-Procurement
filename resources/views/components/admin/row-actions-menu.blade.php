{{--
    Per-row "Actions" dropdown, shared by the vendor and buyer master lists.
    Every row looks identical regardless of how many actions it has -- no
    wrapping, no misalignment -- and it scales as more actions are added
    later. Callers supply the menu items via the slot.

    The panel is teleported to <body> and positioned with a manual
    getBoundingClientRect() calculation rather than plain CSS
    `absolute right-0`, because both lists wrap their table in an
    `overflow-x-auto` div: an explicit overflow-x forces the browser to
    compute overflow-y as `auto` too (CSS Overflow spec), so a plain
    absolutely-positioned panel gets silently clipped at the table
    container's bottom edge -- guaranteed to bite the last row or two in
    any list, not just a rare edge case.
--}}
<div
    x-data="{
        open: false,
        top: 0,
        left: 0,
        toggle() {
            if (this.open) { this.open = false; return }
            const rect = this.$refs.trigger.getBoundingClientRect()
            this.top = rect.bottom + window.scrollY + 4
            this.left = rect.right + window.scrollX - 192
            this.open = true
        },
    }"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
    class="inline-block text-left"
>
    <button
        type="button"
        x-ref="trigger"
        @click="toggle()"
        :aria-expanded="open"
        aria-haspopup="true"
        class="inline-flex items-center gap-1 rounded-md px-2 py-1.5 text-sm text-ink-muted hover:bg-surface-muted hover:text-ink"
    >
        {{ __('admin.row_actions.trigger') }}
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
        </svg>
    </button>

    <template x-teleport="body">
        <div
            x-show="open"
            x-transition
            role="menu"
            :style="`position: fixed; top: ${top}px; left: ${left}px;`"
            class="z-50 w-48 rounded-md border border-line bg-surface py-1 shadow-lg focus:outline-none"
        >
            {{ $slot }}
        </div>
    </template>
</div>
