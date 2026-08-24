<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Http\AssetClassController;
use App\Contexts\MarketView\Http\CryptoDetailController;
use App\Contexts\MarketView\Http\InstrumentDetailController;
use App\Contexts\RealEstate\Http\PropertiesController;
use App\Contexts\RealEstate\Http\PropertyDetailController;
use App\Contexts\Wealth\Http\DashboardController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/pwa.php';

Route::get('/', DashboardController::class)->name('dashboard');

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

Route::get('/instruments/{id}', InstrumentDetailController::class)->name('instruments.show');
Route::get('/crypto/{id}', CryptoDetailController::class)->name('crypto.show');
Route::get('/properties', PropertiesController::class)->name('properties.index');
Route::get('/properties/{id}', PropertyDetailController::class)->name('properties.show');
