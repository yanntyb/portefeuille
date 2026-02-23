<?php

use App\Filament\Pages\EditAccount;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Livewire\livewire;

it('can render the edit account page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(EditAccount::getUrl())
        ->assertSuccessful();
});

it('fills the form with the authenticated user data', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    livewire(EditAccount::class)
        ->assertFormSet([
            'name' => $user->name,
            'email' => $user->email,
        ]);
});

it('can update name and email', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    livewire(EditAccount::class)
        ->fillForm([
            'name' => 'Nouveau Nom',
            'email' => 'nouveau@example.com',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->name)->toBe('Nouveau Nom')
        ->and($user->email)->toBe('nouveau@example.com');
});

it('can update the password', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    livewire(EditAccount::class)
        ->fillForm([
            'password' => 'NewPassword123!',
            'passwordConfirmation' => 'NewPassword123!',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect(Hash::check('NewPassword123!', $user->password))->toBeTrue();
});

it('does not update the password when left empty', function () {
    $user = User::factory()->create([
        'password' => 'OriginalPassword123!',
    ]);

    $originalHash = $user->password;

    $this->actingAs($user);

    livewire(EditAccount::class)
        ->fillForm([
            'name' => 'Updated Name',
            'password' => '',
            'passwordConfirmation' => '',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->name)->toBe('Updated Name')
        ->and($user->password)->toBe($originalHash);
});
