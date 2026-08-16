<?php

namespace Tests\Fixtures;

use Database\Seeders\BackupSeeder;

/**
 * Rejoue `tests/Fixtures/backup-sample.sql` plutôt que le dump complet, dont les 29 152 prix
 * n'apprendraient rien de plus à la suite de tests.
 */
class SampleBackupSeeder extends BackupSeeder
{
    protected function dumpPath(): string
    {
        return base_path('tests/Fixtures/backup-sample.sql');
    }
}
