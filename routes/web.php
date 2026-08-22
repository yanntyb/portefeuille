<?php

use App\Contexts\MarketView\Http\CryptoController;
use App\Contexts\MarketView\Http\CryptoDetailController;
use App\Contexts\MarketView\Http\InstrumentDetailController;
use App\Contexts\MarketView\Http\InstrumentsController;
use App\Contexts\RealEstate\Http\PropertiesController;
use App\Contexts\RealEstate\Http\PropertyDetailController;
use App\Contexts\Wealth\Http\DashboardController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/pwa.php';

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('/instruments', InstrumentsController::class)->name('instruments.index');
Route::get('/instruments/{id}', InstrumentDetailController::class)->name('instruments.show');
Route::get('/crypto', CryptoController::class)->name('crypto.index');
Route::get('/crypto/{id}', CryptoDetailController::class)->name('crypto.show');
Route::get('/properties', PropertiesController::class)->name('properties.index');
Route::get('/properties/{id}', PropertyDetailController::class)->name('properties.show');
