<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Http\StartSyncController;
use App\Contexts\MarketView\Http\AssetClassController;
use App\Contexts\MarketView\Http\AssetController;
use App\Contexts\Portfolio\Http\DeleteTransactionController;
use App\Contexts\Portfolio\Http\StoreTransactionController;
use App\Contexts\Portfolio\Http\UpdateTransactionController;
use App\Contexts\RealEstate\Http\PropertiesController;
use App\Contexts\RealEstate\Http\PropertyDetailController;
use App\Contexts\Wealth\Http\DashboardController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/pwa.php';

Route::get('/', DashboardController::class)->name('dashboard');

/** Ne fait que mettre un job en file : aucun corps, donc aucune validation. */
Route::post('/synchronisation', StartSyncController::class)->name('sync.start');

/**
 * Les trois routes d'écriture de l'application, et la seule saisie qu'elle expose. Le corps est
 * validé par `TransactionRequest` ; chacune répond par une redirection, que le client fait partielle.
 *
 * `whereNumber` n'est pas décoratif : `bootstrap/app.php` renvoie les URL non matchées vers
 * l'accueil, si bien qu'un identifiant non numérique donnerait une redirection silencieuse plutôt
 * qu'un 404 lisible.
 */
Route::post('/transactions', StoreTransactionController::class)->name('transactions.store');
Route::put('/transactions/{id}', UpdateTransactionController::class)
    ->whereNumber('id')
    ->name('transactions.update');
Route::delete('/transactions/{id}', DeleteTransactionController::class)
    ->whereNumber('id')
    ->name('transactions.destroy');

/**
 * Une route par exposition, engendrée depuis l'enum : ajouter une classe d'actif n'est jamais
 * ajouter une route à la main. Routes explicites plutôt qu'un `/{exposition}` attrape-tout — la
 * racine porte déjà `/asset`, `/properties`, `/hors-ligne`, `/instantane`, `/manifest.json`.
 */
foreach (AssetClass::cases() as $assetClass) {
    Route::get($assetClass->slug(), AssetClassController::class)
        ->defaults('exposure', $assetClass->value)
        ->name("classes.{$assetClass->value}");
}

Route::get('/asset/{id}', AssetController::class)->name('assets.show');
Route::get('/properties', PropertiesController::class)->name('properties.index');
Route::get('/properties/{id}', PropertyDetailController::class)->name('properties.show');
