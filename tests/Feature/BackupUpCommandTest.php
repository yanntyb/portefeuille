<?php

use App\Console\Commands\BackupUpCommand;
use Database\Seeders\BackupSeeder;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * La commande est jouée à la main plutôt que par `$this->artisan()` : doubler la façade
 * `Artisan` remplace le noyau console, qui ne saurait plus la lancer. Rejouer réellement
 * `migrate:fresh` est de toute façon exclu sous `RefreshDatabase` — SQLite refuse un `vacuum`
 * dans une transaction.
 */
function runBackupUp(): int
{
    $command = app(BackupUpCommand::class);
    $command->setLaravel(app());

    return $command->run(new ArrayInput([]), new NullOutput);
}

it('est enregistrée sous le nom backup:up', function () {
    expect(Artisan::all())->toHaveKey('backup:up');
});

it('reconstruit le schéma puis rejoue le seul seeder de sauvegarde', function () {
    Artisan::spy();

    expect(runBackupUp())->toBe(BackupUpCommand::SUCCESS);

    Artisan::shouldHaveReceived('call')
        ->withArgs(fn (string $command): bool => $command === 'migrate:fresh')
        ->once();

    Artisan::shouldHaveReceived('call')
        ->withArgs(fn (string $command, array $parameters = []): bool => $command === 'db:seed'
            && ($parameters['--class'] ?? null) === BackupSeeder::class)
        ->once();
});

it('ne sème rien quand la migration échoue', function () {
    Artisan::spy()->shouldReceive('call')->andReturn(BackupUpCommand::FAILURE);

    expect(runBackupUp())->toBe(BackupUpCommand::FAILURE);

    Artisan::shouldHaveReceived('call')
        ->withArgs(fn (string $command): bool => $command === 'migrate:fresh')
        ->once();

    Artisan::shouldNotHaveReceived('call', ['db:seed', ['--class' => BackupSeeder::class]]);
});
