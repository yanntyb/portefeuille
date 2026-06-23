<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/pwa.php';

// Le panel admin Filament servait auparavant la racine. Filament supprime : on
// sert une page d'accueil minimale pour eviter une boucle de redirection
// (le handler 404 redirige vers '/').
Route::view('/', 'welcome');
