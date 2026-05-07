<?php

use App\Domains\User\Filament\Pages\EditAccount;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('renders edit account page', function () {
    $user = User::factory()->create(['email' => 'john@example.com', 'name' => 'John Doe']);
    $this->actingAs($user);

    livewire(EditAccount::class)
        ->assertOk()
        ->assertSeeText('Mon Compte');
});

it('prefills form with current user data', function () {
    $user = User::factory()->create(['email' => 'john@example.com', 'name' => 'John Doe']);
    $this->actingAs($user);

    $page = livewire(EditAccount::class);

    expect($page->instance()->data['name'])->toBe('John Doe')
        ->and($page->instance()->data['email'])->toBe('john@example.com');
});

it('updates user name', function () {
    $user = User::factory()->create(['name' => 'John Doe']);
    $this->actingAs($user);

    livewire(EditAccount::class)
        ->fillForm(['name' => 'Jane Doe'])
        ->call('save');

    expect($user->fresh()->name)->toBe('Jane Doe');
});

it('updates user email', function () {
    $user = User::factory()->create(['email' => 'john@example.com']);
    $this->actingAs($user);

    livewire(EditAccount::class)
        ->fillForm(['email' => 'jane@example.com'])
        ->call('save');

    expect($user->fresh()->email)->toBe('jane@example.com');
});

it('validates email uniqueness', function () {
    $user1 = User::factory()->create(['email' => 'john@example.com']);
    $user2 = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user1);

    livewire(EditAccount::class)
        ->fillForm(['email' => 'jane@example.com'])
        ->call('save')
        ->assertHasFormErrors(['email']);
});

it('allows same email for current user', function () {
    $user = User::factory()->create(['email' => 'john@example.com']);
    $this->actingAs($user);

    livewire(EditAccount::class)
        ->fillForm(['email' => 'john@example.com'])
        ->call('save');

    expect($user->fresh()->email)->toBe('john@example.com');
});

it('updates password when provided', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    livewire(EditAccount::class)
        ->fillForm([
            'password' => 'NewPassword123!',
            'passwordConfirmation' => 'NewPassword123!',
        ])
        ->call('save');

    $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPassword123!', $user->fresh()->password));
});

it('validates password confirmation matches', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    livewire(EditAccount::class)
        ->fillForm([
            'password' => 'NewPassword123!',
            'passwordConfirmation' => 'DifferentPassword123!',
        ])
        ->call('save')
        ->assertHasFormErrors(['passwordConfirmation']);
});

it('validates password strength', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    livewire(EditAccount::class)
        ->fillForm([
            'password' => 'weak',
            'passwordConfirmation' => 'weak',
        ])
        ->call('save')
        ->assertHasFormErrors(['password']);
});

it('allows save without changing password', function () {
    $user = User::factory()->create(['name' => 'John']);
    $originalPassword = $user->password;
    $this->actingAs($user);

    livewire(EditAccount::class)
        ->fillForm(['name' => 'Jane'])
        ->call('save');

    expect($user->fresh()->password)->toBe($originalPassword);
});
