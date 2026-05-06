<?php

use App\Extensions\TablePersistence;

it('has the correct plugin id', function () {
    $extension = TablePersistence::make();

    expect($extension->getId())->toBe('table-persistence');
});

it('is registered as a plugin in the admin panel provider', function () {
    $content = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($content)
        ->toContain('TablePersistence::make()');
});
