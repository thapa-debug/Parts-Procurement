<?php

namespace App\Livewire\Auth;

use App\Actions\RegisterBuyerAction;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $company_name = '';

    public string $default_destination_country = '';

    public string $default_yard = '';

    public string $phone = '';

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'company_name' => ['required', 'string', 'max:255'],
            'default_destination_country' => ['required', 'string', 'max:255'],
            'default_yard' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
        ];
    }

    public function register(RegisterBuyerAction $action): void
    {
        $validated = $this->validate();

        $result = $action->execute(
            $validated['name'],
            $validated['email'],
            $validated['password'],
            $validated['company_name'],
            $validated['default_destination_country'],
            $validated['default_yard'],
            $validated['phone'],
        );

        Auth::login($result['user']);

        $this->redirect('/');
    }

    public function render(): View
    {
        return view('livewire.auth.register')->title(__('auth.register.title'));
    }
}
