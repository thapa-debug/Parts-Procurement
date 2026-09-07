{{--
    Shared photo carousel + lightbox, used by both the admin quote-comparison
    view and the buyer quote view (a vendor response can carry up to 10
    photos -- config/vendor.php's VENDOR_MAX_RESPONSE_PHOTOS). Takes a plain
    array of photo URL strings -- never a model or Collection -- so it stays
    domain-agnostic: the admin side maps its ResponsePhoto collection to
    ->url() strings before passing in, the buyer side already builds plain
    URL arrays in RequestDetail::presentedQuoteOptions() for isolation
    reasons. All navigation/zoom state is local Alpine state -- no
    server round-trip per image change.
--}}
@props(['photos' => []])

@php
    $photoUrls = array_values($photos instanceof \Illuminate\Support\Collection ? $photos->all() : $photos);
@endphp

<div
    x-data="{
        photos: @js($photoUrls),
        active: 0,
        lightboxOpen: false,
        zoomed: false,
        touchStartX: null,
        altLabel: @js(__('app.photo_gallery.alt')),
        counterTemplate: @js(__('app.photo_gallery.counter')),
        get count() { return this.photos.length },
        counterText() {
            return this.counterTemplate.replace(':current', this.active + 1).replace(':total', this.count)
        },
        altText(index) {
            return `${this.altLabel} ${index + 1}`
        },
        next() { this.active = (this.active + 1) % this.count; this.zoomed = false },
        prev() { this.active = (this.active - 1 + this.count) % this.count; this.zoomed = false },
        select(index) { this.active = index; this.zoomed = false },
        open(index) { this.active = index; this.lightboxOpen = true; this.zoomed = false },
        close() { this.lightboxOpen = false; this.zoomed = false },
        toggleZoom() { this.zoomed = !this.zoomed },
        handleTouchStart(event) { this.touchStartX = event.changedTouches[0].clientX },
        handleTouchEnd(event) {
            if (this.touchStartX === null) return
            const delta = event.changedTouches[0].clientX - this.touchStartX
            if (Math.abs(delta) > 40) { delta < 0 ? this.next() : this.prev() }
            this.touchStartX = null
        },
    }"
>
    <template x-if="count === 0">
        <div class="flex h-40 w-full flex-col items-center justify-center gap-2 rounded-md border border-dashed border-line bg-surface-muted text-sm text-ink-muted">
            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3 16.5h18M4.5 19.5h15a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5h-15A1.5 1.5 0 003 6v12a1.5 1.5 0 001.5 1.5z" />
            </svg>
            <span>{{ __('app.photo_gallery.no_photos') }}</span>
        </div>
    </template>

    <template x-if="count > 0">
        <div>
            <div
                class="relative overflow-hidden rounded-md border border-line bg-surface-muted"
                x-on:touchstart.passive="handleTouchStart($event)"
                x-on:touchend.passive="handleTouchEnd($event)"
            >
                <button type="button" class="block h-48 w-full cursor-zoom-in sm:h-64" x-on:click="open(active)">
                    <template x-for="(photo, index) in photos" :key="index">
                        <img :src="photo" :alt="altText(index)" x-show="index === active" class="h-full w-full object-contain">
                    </template>
                </button>

                <template x-if="count > 1">
                    <div>
                        <button
                            type="button"
                            x-on:click.stop="prev()"
                            aria-label="{{ __('app.photo_gallery.prev') }}"
                            class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-surface/90 p-1.5 text-ink shadow hover:bg-surface"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            x-on:click.stop="next()"
                            aria-label="{{ __('app.photo_gallery.next') }}"
                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-surface/90 p-1.5 text-ink shadow hover:bg-surface"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <span class="absolute bottom-2 right-2 rounded-full bg-ink/70 px-2 py-0.5 text-xs font-medium text-white" x-text="counterText()"></span>
                    </div>
                </template>
            </div>

            <template x-if="count > 1">
                <div class="mt-2 flex gap-2 overflow-x-auto">
                    <template x-for="(photo, index) in photos" :key="index">
                        <button
                            type="button"
                            x-on:click="select(index)"
                            class="h-14 w-14 shrink-0 overflow-hidden rounded-md border-2"
                            :class="index === active ? 'border-brand-500' : 'border-line'"
                        >
                            <img :src="photo" :alt="altText(index)" class="h-full w-full object-cover">
                        </button>
                    </template>
                </div>
            </template>
        </div>
    </template>

    <template x-teleport="body">
        <div
            x-show="lightboxOpen"
            x-on:keydown.escape.window="if (lightboxOpen) close()"
            x-on:keydown.arrow-right.window="if (lightboxOpen) next()"
            x-on:keydown.arrow-left.window="if (lightboxOpen) prev()"
            x-on:click.self="close()"
            x-transition.opacity
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
        >
            <button
                type="button"
                x-on:click="close()"
                aria-label="{{ __('app.photo_gallery.close') }}"
                class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20"
            >
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                </svg>
            </button>

            <template x-if="count > 1">
                <button
                    type="button"
                    x-on:click.stop="prev()"
                    aria-label="{{ __('app.photo_gallery.prev') }}"
                    class="absolute left-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-2 text-white hover:bg-white/20"
                >
                    <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                    </svg>
                </button>
            </template>

            <template x-if="count > 1">
                <button
                    type="button"
                    x-on:click.stop="next()"
                    aria-label="{{ __('app.photo_gallery.next') }}"
                    class="absolute right-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-2 text-white hover:bg-white/20"
                >
                    <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                    </svg>
                </button>
            </template>

            <div
                class="max-h-full max-w-full overflow-auto"
                x-on:click.stop
                x-on:touchstart.passive="handleTouchStart($event)"
                x-on:touchend.passive="handleTouchEnd($event)"
            >
                <template x-for="(photo, index) in photos" :key="index">
                    <img
                        :src="photo"
                        :alt="altText(index)"
                        x-show="index === active"
                        x-on:click="toggleZoom()"
                        class="mx-auto transition-transform duration-200"
                        :class="zoomed ? 'max-w-none scale-150 cursor-zoom-out' : 'max-h-[85vh] max-w-full cursor-zoom-in object-contain'"
                    >
                </template>
            </div>

            <template x-if="count > 1">
                <span class="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-white/10 px-3 py-1 text-sm text-white" x-text="counterText()"></span>
            </template>
        </div>
    </template>
</div>
