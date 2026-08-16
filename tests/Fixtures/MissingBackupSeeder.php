<?php

namespace Tests\Fixtures;

use Database\Seeders\BackupSeeder;

/**
 * Pointe volontairement un dump inexistant, pour couvrir la dégradation gracieuse.
 */
class MissingBackupSeeder extends BackupSeeder
{
    protected function dumpPath(): string
    {
        return base_path('tests/Fixtures/dump-absent.sql');
    }
}
