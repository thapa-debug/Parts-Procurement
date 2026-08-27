<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Settings extends Component
{
    public int $margin_rate;

    public int $margin_min_fee;

    /**
     * Vehicle/container are provisional fixed fees pending client
     * confirmation. DHL is deliberately NOT here -- it's per-request
     * (admin-entered at quote time, part_requests.shipping_fee), not a
     * single global number. See CONVENTIONS.md "Shipping fees are
     * provisional" and CLAUDE.md §14 Phase 4.
     */
    public int $shipping_fee_vehicle;

    public int $shipping_fee_container;

    public string $admin_sender_email;

    public bool $justSaved = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Setting::class);

        $this->margin_rate = (int) Setting::get('margin_rate', 20);
        $this->margin_min_fee = (int) Setting::get('margin_min_fee', 2000);
        $this->shipping_fee_vehicle = (int) Setting::get('shipping_fee_vehicle', 0);
        $this->shipping_fee_container = (int) Setting::get('shipping_fee_container', 0);
        $this->admin_sender_email = (string) Setting::get('admin_sender_email', '');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'margin_rate' => ['required', 'integer', 'min:0'],
            'margin_min_fee' => ['required', 'integer', 'min:0'],
            'shipping_fee_vehicle' => ['required', 'integer', 'min:0'],
            'shipping_fee_container' => ['required', 'integer', 'min:0'],
            'admin_sender_email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * Clears the "Saved" indicator as soon as the admin changes any field --
     * it should only ever describe the values currently on screen. Only
     * fires for wire:model-driven updates, so this never fights with the
     * `save()` method's own `justSaved = true` assignment.
     */
    public function updated(string $property): void
    {
        $this->justSaved = false;
    }

    public function save(): void
    {
        $this->authorize('update', Setting::class);

        $validated = $this->validate();

        Setting::set('margin_rate', $validated['margin_rate'], 'integer');
        Setting::set('margin_min_fee', $validated['margin_min_fee'], 'integer');
        Setting::set('shipping_fee_vehicle', $validated['shipping_fee_vehicle'], 'integer');
        Setting::set('shipping_fee_container', $validated['shipping_fee_container'], 'integer');
        Setting::set('admin_sender_email', $validated['admin_sender_email'], 'string');

        $this->justSaved = true;
    }

    public function render(): View
    {
        return view('livewire.admin.settings')->title(__('admin.settings.title'));
    }
}
