<?php

use App\Contexts\Identity\Enums\Role;
use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Infrastructure\Python\YahooScript;
use App\Shared\Python\FakePythonRunner;
use App\Shared\Python\PythonResult;
use App\Shared\Python\PythonRunner;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

/**
 * Le seeder délègue aux commandes market:sync-prices et market:sync-sectors : sans double des
 * scripts Python, chaque appel partirait chercher les cotations sur le réseau.
 */
beforeEach(function () {
    app()->instance(PythonRunner::class, (new FakePythonRunner)
        ->withResult(YahooScript::PricesBulk->path(), new PythonResult('ok', []))
        ->withResult(YahooScript::Sectors->path(), new PythonResult('ok', [])));
});

it('crée un administrateur de test', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::query()->where('email', 'test@example.com')->sole();

    expect($user->name)->toBe('Test User')
        ->and($user->role)->toBe(Role::Admin)
        ->and(Hash::check('password', $user->password))->toBeTrue();
});

it('peut être rejoué sans dupliquer ni échouer sur la contrainte unique', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'test@example.com')->count())->toBe(1);
});
