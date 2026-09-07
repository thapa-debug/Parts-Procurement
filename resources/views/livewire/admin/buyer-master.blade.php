<div>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-ink">{{ __('admin.buyer_master.title') }}</h1>
            <p class="mt-1 text-sm text-ink-muted">{{ __('admin.buyer_master.subheading') }}</p>
        </div>

        <button
            type="button"
            wire:click="openCreateForm"
            class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
        >
            {{ __('admin.buyer_master.create_button') }}
        </button>
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-4">
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('admin.buyer_master.search_placeholder') }}"
            class="w-full max-w-sm rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
        >

        <label class="flex items-center gap-2 text-sm text-ink-muted">
            <input
                type="checkbox"
                wire:model.live="pendingOnly"
                class="rounded border-line text-brand-600 focus:ring-1 focus:ring-brand-500"
            >
            {{ __('admin.buyer_master.approval.pending_only_label') }}
        </label>
    </div>

    <div class="mt-4 overflow-x-auto rounded-lg border border-line bg-surface shadow-sm">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-surface-muted">
                <tr class="text-left text-xs font-medium uppercase tracking-wide text-ink-muted">
                    <th class="px-4 py-3">{{ __('admin.buyer_master.table.company') }}</th>
                    <th class="px-4 py-3">{{ __('admin.buyer_master.table.member_code') }}</th>
                    <th class="px-4 py-3">{{ __('admin.buyer_master.table.contact') }}</th>
                    <th class="px-4 py-3">{{ __('admin.buyer_master.table.email') }}</th>
                    <th class="px-4 py-3">{{ __('admin.verification.column') }}</th>
                    <th class="px-4 py-3">{{ __('admin.buyer_master.approval.column') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('admin.buyer_master.table.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($buyers as $buyer)
                    <tr wire:key="buyer-{{ $buyer->id }}">
                        <td class="px-4 py-3 font-medium text-ink">
                            <a href="{{ route('admin.buyers.show', $buyer) }}" class="hover:text-brand-700 hover:underline">
                                {{ $buyer->company_name }}
                            </a>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-ink-muted">{{ $buyer->member_code }}</td>
                        <td class="px-4 py-3 text-ink-muted">{{ $buyer->user->name }}</td>
                        <td class="px-4 py-3 text-ink-muted">{{ $buyer->user->email }}</td>
                        <td class="px-4 py-3">
                            @if ($buyer->user->hasVerifiedEmail())
                                <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
                                    {{ __('admin.verification.verified_badge') }}
                                </span>
                            @else
                                <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                                    {{ __('admin.verification.unverified_badge') }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($buyer->isApproved())
                                <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
                                    {{ __('admin.buyer_master.approval.approved_badge') }}
                                </span>
                            @else
                                <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                                    {{ __('admin.buyer_master.approval.pending_badge') }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <x-admin.row-actions-menu>
                                <a
                                    href="{{ route('admin.buyers.show', $buyer) }}"
                                    role="menuitem"
                                    class="block px-4 py-2 text-left text-sm text-ink-muted hover:bg-surface-muted hover:text-ink"
                                >
                                    {{ __('admin.profile_edit.edit_link') }}
                                </a>

                                <button
                                    type="button"
                                    wire:click="resetPassword({{ $buyer->id }})"
                                    wire:confirm="{{ __('admin.buyer_master.reset_password_confirm', ['company' => $buyer->company_name]) }}"
                                    role="menuitem"
                                    class="block w-full px-4 py-2 text-left text-sm text-ink-muted hover:bg-surface-muted hover:text-ink"
                                >
                                    {{ __('admin.buyer_master.reset_password_button') }}
                                </button>

                                @unless ($buyer->isApproved())
                                    <button
                                        type="button"
                                        wire:click="approveBuyer({{ $buyer->id }})"
                                        role="menuitem"
                                        class="block w-full px-4 py-2 text-left text-sm text-brand-700 hover:bg-surface-muted hover:text-brand-800"
                                    >
                                        {{ __('admin.buyer_master.approval.approve_button') }}
                                    </button>
                                @endunless

                                @unless ($buyer->user->hasVerifiedEmail())
                                    <span x-data="{ sent: false }" class="block">
                                        <button
                                            type="button"
                                            @click="$wire.resendVerification({{ $buyer->id }}).then(() => { sent = true; setTimeout(() => (sent = false), 2000) })"
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
                        <td colspan="7" class="px-4 py-8 text-center text-ink-muted">
                            {{ __('admin.buyer_master.empty') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $buyers->links() }}
    </div>

    @if ($showCreateForm)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-ink/40 px-4">
            <div class="w-full max-w-lg rounded-lg border border-line bg-surface p-6 shadow-lg">
                <h2 class="text-lg font-semibold text-ink">{{ __('admin.buyer_master.create_form.title') }}</h2>

                <form wire:submit="createBuyer" class="mt-5 space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-ink">
                            {{ __('admin.buyer_master.create_form.name_label') }} <x-required-mark />
                        </label>
                        <input
                            id="name"
                            type="text"
                            wire:model="name"
                            placeholder="{{ __('admin.buyer_master.create_form.name_placeholder') }}"
                            class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        >
                        @error('name')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-ink">
                            {{ __('admin.buyer_master.create_form.email_label') }} <x-required-mark />
                        </label>
                        <input
                            id="email"
                            type="email"
                            wire:model="email"
                            placeholder="{{ __('admin.buyer_master.create_form.email_placeholder') }}"
                            class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        >
                        @error('email')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="company_name" class="block text-sm font-medium text-ink">
                            {{ __('admin.buyer_master.create_form.company_name_label') }} <x-required-mark />
                        </label>
                        <input
                            id="company_name"
                            type="text"
                            wire:model="company_name"
                            placeholder="{{ __('admin.buyer_master.create_form.company_name_placeholder') }}"
                            class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        >
                        @error('company_name')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-ink">
                            {{ __('admin.buyer_master.create_form.phone_label') }} <x-required-mark />
                        </label>
                        <input
                            id="phone"
                            type="text"
                            wire:model="phone"
                            placeholder="{{ __('admin.buyer_master.create_form.phone_placeholder') }}"
                            class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        >
                        @error('phone')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="country_id" class="block text-sm font-medium text-ink">
                            {{ __('admin.buyer_master.create_form.country_label') }} <x-required-mark />
                        </label>
                        <select
                            id="country_id"
                            wire:model="country_id"
                            class="mt-1.5 block w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        >
                            <option value="">{{ __('admin.buyer_master.create_form.country_placeholder_option') }}</option>
                            @foreach ($activeCountries as $country)
                                <option value="{{ $country->id }}">{{ $country->name }}</option>
                            @endforeach
                        </select>
                        @error('country_id')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex items-start gap-2 text-sm text-ink">
                        <input
                            type="checkbox"
                            wire:model="approve_immediately"
                            class="mt-0.5 rounded border-line text-brand-600 focus:ring-1 focus:ring-brand-500"
                        >
                        <span>
                            {{ __('admin.buyer_master.create_form.approve_immediately_label') }}
                            <span class="block text-xs text-ink-muted">
                                {{ __('admin.buyer_master.create_form.approve_immediately_help') }}
                            </span>
                        </span>
                    </label>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            wire:click="cancelCreateForm"
                            class="rounded-md border border-line px-4 py-2 text-sm font-medium text-ink-muted hover:text-ink"
                        >
                            {{ __('admin.buyer_master.create_form.cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
                            wire:loading.attr="disabled"
                            wire:target="createBuyer"
                        >
                            {{ __('admin.buyer_master.create_form.submit') }}
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
            :heading="$revealedContext === 'reset' ? __('admin.buyer_master.reveal.reset_heading') : __('admin.buyer_master.reveal.created_heading')"
            :warning="__('admin.buyer_master.reveal.warning')"
            :verification-email="$revealedVerificationEmail"
        />
    @endif
</div>
