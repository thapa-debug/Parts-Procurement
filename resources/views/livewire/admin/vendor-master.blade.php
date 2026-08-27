<div>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-ink">{{ __('admin.vendor_master.title') }}</h1>
            <p class="mt-1 text-sm text-ink-muted">{{ __('admin.vendor_master.subheading') }}</p>
        </div>

        <button
            type="button"
            wire:click="openCreateForm"
            class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
        >
            {{ __('admin.vendor_master.create_button') }}
        </button>
    </div>

    <div class="mt-6">
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('admin.vendor_master.search_placeholder') }}"
            class="w-full max-w-sm rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
        >
    </div>

    <div class="mt-4 overflow-x-auto rounded-lg border border-line bg-surface shadow-sm">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-surface-muted">
                <tr class="text-left text-xs font-medium uppercase tracking-wide text-ink-muted">
                    <th class="px-4 py-3">{{ __('admin.vendor_master.table.company') }}</th>
                    <th class="px-4 py-3">{{ __('admin.vendor_master.table.contact') }}</th>
                    <th class="px-4 py-3">{{ __('admin.vendor_master.table.email') }}</th>
                    <th class="px-4 py-3">{{ __('admin.vendor_master.table.status') }}</th>
                    <th class="px-4 py-3">{{ __('admin.verification.column') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('admin.vendor_master.table.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($vendors as $vendor)
                    <tr wire:key="vendor-{{ $vendor->id }}">
                        <td class="px-4 py-3 font-medium text-ink">
                            <a href="{{ route('admin.vendors.show', $vendor) }}" class="hover:text-brand-700 hover:underline">
                                {{ $vendor->company_name }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-ink-muted">{{ $vendor->contact_person }}</td>
                        <td class="px-4 py-3 text-ink-muted">{{ $vendor->user->email }}</td>
                        <td class="px-4 py-3">
                            @if ($vendor->status === \App\Enums\VendorStatus::Active)
                                <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
                                    {{ __('admin.vendor_master.status.active') }}
                                </span>
                            @else
                                <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-ink-muted">
                                    {{ __('admin.vendor_master.status.suspended') }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($vendor->user->hasVerifiedEmail())
                                <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
                                    {{ __('admin.verification.verified_badge') }}
                                </span>
                            @else
                                <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                                    {{ __('admin.verification.unverified_badge') }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <x-admin.row-actions-menu>
                                <a
                                    href="{{ route('admin.vendors.show', $vendor) }}"
                                    role="menuitem"
                                    class="block px-4 py-2 text-left text-sm text-ink-muted hover:bg-surface-muted hover:text-ink"
                                >
                                    {{ __('admin.profile_edit.edit_link') }}
                                </a>

                                @if ($vendor->status === \App\Enums\VendorStatus::Active)
                                    <button
                                        type="button"
                                        wire:click="suspend({{ $vendor->id }})"
                                        wire:confirm="{{ __('admin.vendor_master.suspend_confirm', ['company' => $vendor->company_name]) }}"
                                        role="menuitem"
                                        class="block w-full px-4 py-2 text-left text-sm text-ink-muted hover:bg-surface-muted hover:text-ink"
                                    >
                                        {{ __('admin.vendor_master.suspend_button') }}
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        wire:click="resume({{ $vendor->id }})"
                                        role="menuitem"
                                        class="block w-full px-4 py-2 text-left text-sm text-brand-700 hover:bg-surface-muted hover:text-brand-800"
                                    >
                                        {{ __('admin.vendor_master.resume_button') }}
                                    </button>
                                @endif

                                <button
                                    type="button"
                                    wire:click="resetPassword({{ $vendor->id }})"
                                    wire:confirm="{{ __('admin.vendor_master.reset_password_confirm', ['company' => $vendor->company_name]) }}"
                                    role="menuitem"
                                    class="block w-full px-4 py-2 text-left text-sm text-ink-muted hover:bg-surface-muted hover:text-ink"
                                >
                                    {{ __('admin.vendor_master.reset_password_button') }}
                                </button>

                                @unless ($vendor->user->hasVerifiedEmail())
                                    <span x-data="{ sent: false }" class="block">
                                        <button
                                            type="button"
                                            @click="$wire.resendVerification({{ $vendor->id }}).then(() => { sent = true; setTimeout(() => (sent = false), 2000) })"
                                            role="menuitem"
                                            class="block w-full px-4 py-2 text-left text-sm text-ink-muted hover:bg-surface-muted hover:text-ink"
                                        >
                                            <span x-show="!sent">{{ __('admin.verification.resend_button') }}</span>
                                            <span x-show="sent" style="display: none">{{ __('admin.verification.resent') }}</span>
                                        </button>
                                    </span>
                                @endunless
                            </x-admin.row-actions-menu>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-ink-muted">
                            {{ __('admin.vendor_master.empty') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $vendors->links() }}
    </div>

    @if ($showCreateForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-ink/40 px-4">
            <div class="w-full max-w-lg rounded-lg border border-line bg-surface p-6 shadow-lg">
                <h2 class="text-lg font-semibold text-ink">{{ __('admin.vendor_master.create_form.title') }}</h2>

                <form wire:submit="createVendor" class="mt-5 space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-ink">
                            {{ __('admin.vendor_master.create_form.name_label') }}
                        </label>
                        <input
                            id="name"
                            type="text"
                            wire:model="name"
                            class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        >
                        @error('name')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-ink">
                            {{ __('admin.vendor_master.create_form.email_label') }}
                        </label>
                        <input
                            id="email"
                            type="email"
                            wire:model="email"
                            class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        >
                        @error('email')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="company_name" class="block text-sm font-medium text-ink">
                                {{ __('admin.vendor_master.create_form.company_name_label') }}
                            </label>
                            <input
                                id="company_name"
                                type="text"
                                wire:model="company_name"
                                class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                            >
                            @error('company_name')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="contact_person" class="block text-sm font-medium text-ink">
                                {{ __('admin.vendor_master.create_form.contact_person_label') }}
                            </label>
                            <input
                                id="contact_person"
                                type="text"
                                wire:model="contact_person"
                                class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                            >
                            @error('contact_person')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="phone" class="block text-sm font-medium text-ink">
                                {{ __('admin.vendor_master.create_form.phone_label') }}
                            </label>
                            <input
                                id="phone"
                                type="text"
                                wire:model="phone"
                                class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                            >
                            @error('phone')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="notify_email" class="block text-sm font-medium text-ink">
                                {{ __('admin.vendor_master.create_form.notify_email_label') }}
                            </label>
                            <input
                                id="notify_email"
                                type="email"
                                wire:model="notify_email"
                                class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                            >
                            @error('notify_email')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            wire:click="cancelCreateForm"
                            class="rounded-md border border-line px-4 py-2 text-sm font-medium text-ink-muted hover:text-ink"
                        >
                            {{ __('admin.vendor_master.create_form.cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
                            wire:loading.attr="disabled"
                            wire:target="createVendor"
                        >
                            {{ __('admin.vendor_master.create_form.submit') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($revealedPassword)
        <x-admin.temporary-password-reveal
            :password="$revealedPassword"
            :for-company="$revealedForCompany"
            :heading="$revealedContext === 'reset' ? __('admin.vendor_master.reveal.reset_heading') : __('admin.vendor_master.reveal.created_heading')"
            :warning="__('admin.vendor_master.reveal.warning')"
        />
    @endif
</div>
