<?php

namespace App\Livewire\Auth;

use App\Actions\RegisterBuyerAction;
use App\Models\Country;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $company_name = '';

    public string $country_id = '';

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
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')->where('is_active', true)],
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
            (int) $validated['country_id'],
            $validated['default_yard'],
            $validated['phone'],
        );

        Auth::login($result['user']);

        $this->redirect('/');
    }

    public function render(): View
    {
        return view('livewire.auth.register', [
            'activeCountries' => Country::query()->active()->orderBy('name')->get(),
        ])->title(__('auth.register.title'));
    }
}
