<?php

namespace App\Livewire;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Validate;
use Livewire\Component;

class InvitationRegistration extends Component
{
    public ?Invitation $invitation = null;

    public bool $tokenInvalid = false;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|email|max:255|unique:users,email')]
    public string $email = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation) {
            abort(404);
        }

        $this->invitation = $invitation;

        if (! $invitation->isValid()) {
            $this->tokenInvalid = true;
        }
    }

    public function submit(): void
    {
        if ($this->tokenInvalid) {
            return;
        }

        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'role' => Role::User,
        ]);

        $this->invitation->update(['used_at' => now()]);

        Auth::login($user);

        $this->redirect('/admin', navigate: false);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.invitation-registration')
            ->layout('layouts.app');
    }
}
