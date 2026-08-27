<?php

namespace App\Livewire\Auth;

use App\Actions\ChangePasswordAction;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class PasswordChange extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function update(ChangePasswordAction $action): void
    {
        $this->validate();

        /** @var User $user */
        $user = auth()->user();

        $action->execute($user, $this->password);

        session()->flash('status', __('auth.password_change.success'));

        $this->redirect('/');
    }

    public function render(): View
    {
        return view('livewire.auth.password-change')->title(__('auth.password_change.title'));
    }
}
