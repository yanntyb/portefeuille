<?php

namespace App\Console\Commands;

use Database\Seeders\BackupSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Reconstruit la base à vide puis y rejoue le snapshot de production.
 *
 * `BackupSeeder` n'est pas branché sur `DatabaseSeeder` : un `migrate:fresh --seed` peuplerait
 * la base des données de démonstration synthétiques. Cette commande enchaîne donc la
 * reconstruction du schéma et ce seul seeder — l'état de travail habituel après avoir modifié
 * une migration déjà jouée.
 */
class BackupUpCommand extends Command
{
    protected $signature = 'backup:up';

    protected $description = 'Reconstruit la base et y rejoue le snapshot de production (BackupSeeder)';

    public function handle(): int
    {
        $migrated = (int) Artisan::call('migrate:fresh', [], $this->output);

        if ($migrated !== self::SUCCESS) {
            return $migrated;
        }

        return (int) Artisan::call('db:seed', ['--class' => BackupSeeder::class], $this->output);
    }
}
